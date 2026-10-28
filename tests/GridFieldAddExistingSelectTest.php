<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridFieldAddExistingSelect;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * Exercises candidate filtering and the normal CSRF-protected GridField write route.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldAddExistingSelectTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [SelectOwner::class, SelectRecord::class];

    /** Builds a real persisted relation and form for each test. */
    private function grid(): GridField
    {
        $this->logInWithPermission('GRIDFIELD_PRO_TEST_EDIT');
        $owner = SelectOwner::create(['Title' => 'Owner']);
        $owner->write();
        $component = GridFieldAddExistingSelect::create('before', ['Title:PartialMatch']);
        $grid = GridField::create('Items', 'Items', $owner->Items(), GridFieldConfig::create()->addComponent($component));
        $controller = Controller::create();
        $request = new HTTPRequest('GET', '/test');
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $controller->setRequest($request);
        $form = Form::create($controller, 'Test', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($owner);
        $form->setFormAction('/test');
        SecurityToken::enable();
        return $grid;
    }

    /** Creates one visible or hidden candidate. */
    private function candidate(string $title, bool $visible = true): SelectRecord
    {
        $record = SelectRecord::create(['Title' => $title, 'Visible' => $visible]);
        $record->write();
        return $record;
    }

    /** The component retains core search configuration but renders no extra visible button. */
    public function testCompactMarkup(): void
    {
        $grid = $this->grid();
        $html = $grid->FieldHolder();
        self::assertStringContainsString('gridfield-add-existing-select__input', $html);
        self::assertStringContainsString('no-chosen no-change-track', $html);
        self::assertStringContainsString('Items[ExistingID]', $html);
        self::assertStringContainsString('existing-search', $html);
        self::assertStringContainsString('hidden', $html);
        self::assertStringNotContainsString('action_gridfield_relationfind', $html);
    }

    /** Search is bounded and excludes linked, hidden and nonmatching candidates. */
    public function testAjaxSearch(): void
    {
        $grid = $this->grid();
        $linked = $this->candidate('Linked slide');
        $grid->getList()->add($linked);
        $expected = $this->candidate('<b>Reusable slide</b>');
        $this->candidate('Hidden slide', false);
        $this->candidate('Other');
        $request = new HTTPRequest('GET', '/', ['gridfield_relationsearch' => 'slide']);
        $response = $this->component($grid)->doSearch($grid, $request);
        $results = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([(int)$expected->ID], array_column($results, 'id'));
        self::assertSame('no-store', $response->getHeader('Cache-Control'));
        $this->component($grid)->setResultsLimit(1);
        $this->candidate('Another slide');
        self::assertCount(1, json_decode($this->component($grid)->doSearch($grid, $request)->getBody(), true));
    }

    /** Short searches never enumerate records, including calls made outside the picker. */
    public function testSearchRequiresTwoCharacters(): void
    {
        $grid = $this->grid();
        $this->candidate('Slide');
        foreach (['', 'S', ' S ', '   '] as $query) {
            $request = new HTTPRequest('GET', '/', ['gridfield_relationsearch' => $query]);
            self::assertSame('[]', $this->component($grid)->doSearch($grid, $request)->getBody());
        }
        $response = $grid->handleRequest(new HTTPRequest('GET', 'existing-search', ['gridfield_relationsearch' => 'Sl']));
        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR), $response->getBody());
    }

    /** A search list constrains both the offered results and tampered link IDs. */
    public function testSearchScopeRejectsForgedSelection(): void
    {
        $grid = $this->grid();
        $allowed = $this->candidate('Allowed');
        $excluded = $this->candidate('Excluded');
        $this->component($grid)->setSearchList(SelectRecord::get()->filter('ID', $allowed->ID));
        $this->post($grid, (int)$excluded->ID);
        $this->expectException(HTTPResponse_Exception::class);
        $this->component($grid)->handleAction($grid, 'addexistingselect', [], ['Items' => ['ExistingID' => $excluded->ID]]);
    }

    /** Uses the actual GridField action route, maintaining idempotent many-many links. */
    public function testLinkingThroughGridFieldAction(): void
    {
        $grid = $this->grid();
        $record = $this->candidate('Reusable');
        $grid->FieldHolder();
        foreach ([1, 2] as $attempt) {
            $request = $this->post($grid, (int)$record->ID);
            $grid->gridFieldAlterAction([], $grid->getForm(), $request);
            self::assertSame([(int)$record->ID], array_map('intval', $grid->getList()->column('ID')));
        }
    }

    /** Prepares a POST using the state-backed action name generated by Silverstripe. */
    private function post(GridField $grid, int $id, bool $validToken = true): HTTPRequest
    {
        $html = $grid->FieldHolder();
        preg_match('/name="(action_gridFieldAlterAction[^"]+)"/', $html, $match);
        $token = $grid->getForm()->getSecurityToken();
        $request = new HTTPRequest('POST', '/test', [], [
            html_entity_decode($match[1]) => '1',
            'Items' => ['ExistingID' => (string)$id],
            $token->getName() => $validToken ? $token->getValue() : 'invalid',
        ]);
        $request->setSession($grid->getForm()->getController()->getRequest()->getSession());
        $grid->getForm()->getController()->setRequest($request);
        return $request;
    }

    /** Rejects a write without a valid form security token. */
    public function testCsrfRejection(): void
    {
        $grid = $this->grid();
        $request = $this->post($grid, (int)$this->candidate('Test')->ID, false);
        $this->expectException(HTTPResponse_Exception::class);
        $grid->gridFieldAlterAction([], $grid->getForm(), $request);
    }

    /** State from the old autocomplete cannot mutate the relation during rendering or searching. */
    public function testForgedGridStateDoesNotLink(): void
    {
        $grid = $this->grid();
        $grid->State->GridFieldAddRelation = $this->candidate('Test')->ID;
        $this->component($grid)->getManipulatedData($grid, $grid->getList());
        self::assertCount(0, $grid->getList());
    }

    /** Direct searches and writes remain forbidden for readonly or unauthorized forms. */
    public function testReadonlyAndOwnerPermissions(): void
    {
        $grid = $this->grid();
        $component = $this->component($grid);
        $grid->setReadonly(true);
        self::assertSame([], $component->getHTMLFragments($grid));
        $grid->setReadonly(false);
        Security::setCurrentUser(null);
        self::assertSame([], $component->getHTMLFragments($grid));
        $this->expectException(HTTPResponse_Exception::class);
        $component->doSearch($grid, new HTTPRequest('GET', '/', ['gridfield_relationsearch' => '']));
    }

    /** Hidden candidates cannot be linked by a manually supplied ID. */
    public function testHiddenRecordCannotBeLinked(): void
    {
        $grid = $this->grid();
        $record = $this->candidate('Hidden', false);
        $request = $this->post($grid, (int)$record->ID);
        $this->expectException(HTTPResponse_Exception::class);
        $grid->gridFieldAlterAction([], $grid->getForm(), $request);
    }

    /** Retrieves the tested picker without relying on the core component's class name. */
    private function component(GridField $grid): GridFieldAddExistingSelect
    {
        return $grid->getConfig()->getComponentByType(GridFieldAddExistingSelect::class);
    }
}
