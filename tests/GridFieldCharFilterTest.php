<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;
use DOMDocument;
use DOMXPath;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

/**
 * Covers the responsive character selector and its shared native action state.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldCharFilterTest extends SapphireTest
{
    /** Gives the filter a real form for native action names and select IDs. */
    private function grid(GridField_CharFilter $filter): GridField
    {
        $grid = GridField::create('Records', '', ArrayList::create(), GridFieldConfig::create()->addComponent($filter));
        $form = Form::create(Controller::curr(), 'CharTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        return $grid;
    }

    /** Selected state, reset and numeric characters agree in both representations. */
    public function testSelectMatchesNativeActions(): void
    {
        $filter = GridField_CharFilter::create('before', 'Title', 'A,B,0')->setSelectedChar('B');
        $html = (string)$filter->getHTMLFragments($this->grid($filter))['before'];
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        self::assertCount(4, $xpath->query('//select/option'));
        self::assertCount(4, $xpath->query('//button[@data-filter-char]'));
        self::assertSame('B', $xpath->query('//select/option[@selected]')->item(0)->getAttribute('value'));
        self::assertCount(1, $xpath->query('//button[@data-filter-char="B"][@aria-pressed="true"]'));
        self::assertCount(1, $xpath->query('//button[@data-filter-char="all"]'));
        self::assertStringContainsString('action_gridFieldAlterAction?StateID=', $html);
    }

    /** Both the numeric zero and the explicit reset retain their actual meaning. */
    public function testZeroAndReset(): void
    {
        $filter = GridField_CharFilter::create('before', 'Title', 'A,0');
        $grid = $this->grid($filter);
        $list = ArrayList::create([ArrayData::create(['Title' => '0zero']), ArrayData::create(['Title' => 'Alpha'])]);
        $filter->handleAction($grid, GridField_CharFilter::ACTION_NAME, ['char' => '0'], []);
        self::assertSame(['0zero'], $filter->getManipulatedData($grid, $list)->column('Title'));
        $filter->handleAction($grid, GridField_CharFilter::ACTION_NAME, ['char' => ''], []);
        self::assertSame($list, $filter->getManipulatedData($grid, $list));
    }

    /** Selecting, changing and clearing a character always starts at the first page. */
    public function testCharacterActionsResetPagination(): void
    {
        $filter = GridField_CharFilter::create('before', 'Title', 'A,B,0');
        $grid = $this->grid($filter);
        $paginator = GridFieldPaginator::create(2);
        $grid->getConfig()->addComponent($paginator);
        foreach (['A', 'B', '0', ''] as $char) {
            $paginator->handleAction($grid, 'paginate', 10, []);
            self::assertSame(10, $grid->State->GridFieldPaginator->currentPage);
            $filter->handleAction($grid, GridField_CharFilter::ACTION_NAME, ['char' => $char], []);
            self::assertSame(1, $grid->State->GridFieldPaginator->currentPage);
            self::assertSame($char, $filter->getSelectedChar());
        }
    }

    /** Other actions and incomplete requests must not disturb the current page. */
    public function testUnrelatedActionsPreservePagination(): void
    {
        $filter = GridField_CharFilter::create();
        $grid = $this->grid($filter);
        $paginator = GridFieldPaginator::create();
        $grid->getConfig()->addComponent($paginator);
        $paginator->handleAction($grid, 'paginate', 10, []);
        $filter->handleAction($grid, 'unrelated', ['char' => 'A'], []);
        $filter->handleAction($grid, GridField_CharFilter::ACTION_NAME, [], []);
        self::assertSame(10, $grid->State->GridFieldPaginator->currentPage);
    }
}
