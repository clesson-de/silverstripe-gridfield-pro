<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;

/**
 * Covers the search's scoped ORM filtering, localized markup and native state lifecycle.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldSearchTest extends SapphireTest
{
    protected $usesDatabase = true;
    protected static $extra_dataobjects = [SearchRecord::class];

    /** Builds a real form and ORM-backed GridField. */
    private function grid(GridFieldSearch $search): GridField
    {
        $grid = GridField::create('Records', '', SearchRecord::get(), GridFieldConfig::create()->addComponent($search));
        $form = Form::create(Controller::curr(), 'SearchTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        return $grid;
    }

    /** A search term applies to any configured field, without widening the original source list. */
    public function testScopedRelationSearchAndMissingRelations(): void
    {
        $member = Member::create(['Email' => 'linked-needle@example.test']);
        $member->write();
        $direct = SearchRecord::create(['Title' => 'NEEDLE', 'Visible' => true]);
        $direct->write();
        $related = SearchRecord::create(['OwnerID' => $member->ID, 'Visible' => true]);
        $related->write();
        SearchRecord::create(['Title' => 'Needle excluded', 'Visible' => false])->write();
        SearchRecord::create(['Title' => 'Other', 'Visible' => true])->write();
        $search = GridFieldSearch::create(['Title', 'Owner.Email']);
        $grid = $this->grid($search);
        $grid->setList(SearchRecord::get()->filter('Visible', true));
        $search->handleAction($grid, GridFieldSearch::ACTION, [], ['Records' => ['SearchQuery' => ' needle ']]);
        $ids = $search->getManipulatedData($grid, $grid->getList())->sort('ID')->column('ID');
        self::assertSame([(int) $direct->ID, (int) $related->ID], array_map('intval', $ids));
        self::assertSame('needle', $search->getQuery($grid));
    }

    /** Submitted and cleared searches reset a paginator, while unrelated actions retain its state. */
    public function testSearchStateAndPagination(): void
    {
        $search = GridFieldSearch::create(['Title']);
        $grid = $this->grid($search);
        $paginator = GridFieldPaginator::create();
        $grid->getConfig()->addComponent($paginator);
        foreach (['ab', 'long term', ''] as $query) {
            $paginator->handleAction($grid, 'paginate', 5, []);
            $search->handleAction($grid, GridFieldSearch::ACTION, [], ['Records' => ['SearchQuery' => $query]]);
            self::assertSame(1, $grid->State->GridFieldPaginator->currentPage);
            self::assertSame($query, $search->getQuery($grid));
        }
        $paginator->handleAction($grid, 'paginate', 5, []);
        $search->handleAction($grid, 'unrelated', [], []);
        $search->getManipulatedData($grid, $grid->getList());
        self::assertSame(5, $grid->State->GridFieldPaginator->currentPage);
    }

    /** Applied terms survive fresh component construction and native GridState serialization. */
    public function testStateSurvivesReconstructionWithoutPaginator(): void
    {
        $search = GridFieldSearch::create(['Title']);
        $grid = $this->grid($search);
        $search->handleAction($grid, GridFieldSearch::ACTION, [], ['Records' => ['SearchQuery' => 'restored']]);
        $restored = $this->grid(GridFieldSearch::create(['Title']));
        $restored->getState(false)->setValue($grid->getState(false)->getValue());
        self::assertSame('restored', GridFieldSearch::create(['Title'])->getQuery($restored));
    }

    /** Full-page GET navigation restores the search and paginator through native URL state. */
    public function testSearchFromNavigationURL(): void
    {
        $search = GridFieldSearch::create(['Title'], 'before');
        $grid = $this->grid($search);
        $grid->getConfig()->addComponent(GridFieldPaginator::create());
        $key = $grid->getStateManager()->getStateKey($grid);
        $grid->setRequest(new HTTPRequest('GET', '/test', [
            $key => json_encode([
                GridFieldSearch::STATE => ['query' => 'navigation'],
                'GridFieldPaginator' => ['currentPage' => 1],
            ], JSON_THROW_ON_ERROR),
        ]));
        self::assertSame('navigation', $search->getQuery($grid));
        self::assertSame(1, $grid->State->GridFieldPaginator->currentPage);
        $html = (string) $search->getHTMLFragments($grid)['before'];
        self::assertStringContainsString('data-state-key="' . $key . '"', $html);
        self::assertStringContainsString('data-paginator="true"', $html);
        self::assertStringNotContainsString('data-automatic', $html);
    }

    /** Placeholder punctuation and labels adapt to one, two or three fields in both languages. */
    public function testLocalizedPlaceholders(): void
    {
        foreach ([
            [['Title' => 'Name'], 'Suche in Name'],
            [['Title' => 'Name', 'Description' => 'Beschreibung'], 'Suche in Name und Beschreibung'],
            [['Title' => 'Name', 'Owner.Email' => 'Titel', 'Description' => 'Beschreibung'], 'Suche in Name, Titel und Beschreibung'],
        ] as [$fields, $expected]) {
            $search = GridFieldSearch::create($fields);
            $grid = $this->grid($search);
            self::assertSame($expected, i18n::with_locale('de_DE', fn () => $search->getPlaceholder($grid)));
        }
        $search = GridFieldSearch::create(['Title', 'Owner.Email']);
        self::assertSame(
            'Search in Title and Email',
            i18n::with_locale('en_US', fn () => $search->getPlaceholder($this->grid($search)))
        );
    }

    /** There is no visible submit button, and user values are escaped in text and attributes. */
    public function testMarkupAndConfiguration(): void
    {
        $search = GridFieldSearch::create(['Title'], 'before');
        $grid = $this->grid($search);
        $search->handleAction($grid, GridFieldSearch::ACTION, [], ['Records' => ['SearchQuery' => '"><script>']]);
        $html = (string) $search->getHTMLFragments($grid)['before'];
        self::assertStringNotContainsString('<script>', $html);
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        self::assertCount(1, $xpath->query('//input[@type="text"]'));
        self::assertCount(1, $xpath->query('//label[@for]'));
        self::assertCount(0, $xpath->query('//button[@type="submit" and not(@hidden)]'));
        self::assertStringContainsString('no-change-track', $html);
    }

    /** Missing field configuration is a developer error. */
    public function testEmptyFieldsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GridFieldSearch::create([]);
    }

    /** Request data cannot specify arbitrary SQL fields or filter modifiers. */
    public function testUnknownFieldsAreRejected(): void
    {
        $search = GridFieldSearch::create(['Owner.DoesNotExist']);
        $this->expectException(InvalidArgumentException::class);
        $search->getPlaceholder($this->grid($search));
    }

    /** Malformed terms never become success-shaped empty searches. */
    public function testMalformedQueryIsRejected(): void
    {
        $search = GridFieldSearch::create(['Title']);
        $this->expectException(HTTPResponse_Exception::class);
        $search->handleAction($this->grid($search), GridFieldSearch::ACTION, [], ['Records' => ['SearchQuery' => []]]);
    }

    /** Overlong state cannot bypass the input's client-side limit. */
    public function testOverlongStateIsRejected(): void
    {
        $search = GridFieldSearch::create(['Title']);
        $grid = $this->grid($search);
        $grid->State->{GridFieldSearch::STATE}->query = str_repeat('a', GridFieldSearch::MAX_LENGTH + 1);
        $this->expectException(HTTPResponse_Exception::class);
        $search->getManipulatedData($grid, $grid->getList());
    }

    /** Field filter modifiers cannot turn a configured partial-match search into a different operation. */
    public function testFilterModifiersAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GridFieldSearch::create(['Title:ExactMatch']);
    }
}
