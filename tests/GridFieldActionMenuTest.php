<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridFieldActionMenu;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuExportButton;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuItem;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Security\Security;

/**
 * Covers registered providers, native actions, authorization and file responses.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldActionMenuTest extends SapphireTest
{
    protected $usesDatabase = true;
    protected static $extra_dataobjects = [MenuRecord::class];

    /** Creates a grid with real DataList export and an explicitly registered action. */
    private function grid(): GridField
    {
        $config = GridFieldConfig::create()->addComponents(
            GridFieldToolbar::create(), MenuAction::create(), GridFieldDataColumns::create(),
            GridFieldActionMenu::create()->addProvider(MenuAction::class)
        );
        $grid = GridField::create('Records', 'Records', MenuRecord::get(), $config);
        $controller = Controller::create();
        $controller->setRequest(Controller::curr()->getRequest());
        $form = Form::create($controller, 'MenuTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        return $grid;
    }

    /** A menu icon cannot inject additional markup or arbitrary classes. */
    public function testInvalidIconIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GridFieldActionMenu::create()->setIcon('download" onclick="alert(1)');
    }

    /** Typed action controls retain their StateStore names and escaped labels. */
    public function testNativeActionsAndReadonlyFiltering(): void
    {
        $grid = $this->grid();
        $menu = $grid->getConfig()->getComponentByType(GridFieldActionMenu::class);
        $html = (string)$menu->getHTMLFragments($grid)['buttons-before-right'];
        self::assertStringContainsString('action_gridFieldAlterAction?StateID=', $html);
        self::assertStringContainsString('&lt;Report&gt;', $html);
        self::assertStringContainsString('data-confirm="Run report?"', $html);
        self::assertStringNotContainsString('no-ajax', $html);
        $grid->setReadonly(true);
        $html = (string)$menu->getHTMLFragments($grid)['buttons-before-right'];
        self::assertStringNotContainsString('action_gridFieldAlterAction', $html);
        self::assertStringContainsString('/help', $html);
        $grid->getConfig()->getComponentByType(MenuAction::class)->allowed = false;
        self::assertSame([], $menu->getHTMLFragments($grid));
    }

    /** Providers are resolved from the cloned configuration rather than old references. */
    public function testCloneAndReadonlyTransformation(): void
    {
        $grid = $this->grid();
        GridFieldToolbar::preserveReadonly($grid, [MenuAction::class]);
        $copy = $grid->performReadonlyTransformation();
        self::assertNotSame($grid->getConfig(), $copy->getConfig());
        $html = (string)$copy->FieldHolder();
        self::assertStringContainsString('/help', $html);
        self::assertStringNotContainsString('&lt;Report&gt;', $html);
        self::assertStringContainsString('gridfield-pro-toolbar', $html);
        self::assertStringNotContainsString('&lt;Report&gt;', (string)$copy->FieldHolder());
    }

    /** Missing providers are configuration errors, not silently empty menus. */
    public function testMissingProviderFails(): void
    {
        $grid = $this->grid();
        $grid->getConfig()->removeComponentsByType(MenuAction::class);
        $this->expectException(LogicException::class);
        $grid->getConfig()->getComponentByType(GridFieldActionMenu::class)->getHTMLFragments($grid);
    }

    /** First-matching core dispatch cannot steal an ambiguously registered action. */
    public function testDuplicateActionHandlerFails(): void
    {
        $grid = $this->grid();
        $grid->getConfig()->addComponent(MenuAction::create());
        $this->expectException(LogicException::class);
        $grid->getConfig()->getComponentByType(GridFieldActionMenu::class)->getHTMLFragments($grid);
    }

    /** A legitimate action uses the handler's POST and CSRF validation. */
    public function testPostActionAndPermissionRevocation(): void
    {
        $grid = $this->grid();
        $form = $grid->getForm();
        $token = $form->getSecurityToken();
        $request = new HTTPRequest('POST', '/test', [], [$token->getName() => $token->getValue()]);
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $form->getController()->setRequest($request);
        $grid->handleAlterAction('pro-report', [], []);
        $provider = $grid->getConfig()->getComponentByType(MenuAction::class);
        self::assertSame(1, $provider->calls);
        $provider->allowed = false;
        $this->expectException(HTTPResponse_Exception::class);
        $grid->handleAlterAction('pro-report', [], []);
    }

    /** A direct POST without the form's token never executes application work. */
    public function testMissingTokenFails(): void
    {
        $grid = $this->grid();
        $request = new HTTPRequest('POST', '/test');
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $grid->getForm()->getController()->setRequest($request);
        $this->expectException(HTTPResponse_Exception::class);
        $grid->handleAlterAction('pro-report', [], []);
    }

    /** Rendered native controls dispatch through StateStore and refresh the actual field. */
    public function testNativeMenuPostDispatch(): void
    {
        $grid = $this->grid();
        $html = (string)$grid->FieldHolder();
        self::assertSame(1, preg_match('/name="(action_gridFieldAlterAction\?StateID=[^"]+)"/', $html, $matches));
        $token = $grid->getForm()->getSecurityToken();
        $request = new HTTPRequest('POST', '/test', [], [
            html_entity_decode($matches[1]) => 'Run',
            $token->getName() => $token->getValue(),
        ]);
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $request->addHeader('X-Pjax', 'CurrentField');
        $grid->getForm()->getController()->setRequest($request);
        $response = $grid->gridFieldAlterAction([], $grid->getForm(), $request);
        self::assertSame(1, $grid->getConfig()->getComponentByType(MenuAction::class)->calls);
        self::assertStringContainsString('gridfield-pro-toolbar', (string)$response);
    }

    /** CSV responses remain downloads and do not remove the grid's paginator. */
    public function testExportPreservesConfigurationAndChecksPermission(): void
    {
        $grid = $this->grid();
        MenuRecord::create(['Title' => 'Exported record'])->write();
        $export = GridFieldMenuExportButton::create('after', ['Title' => 'Title']);
        $export->setPermissionChecker(static fn () => true);
        $paginator = GridFieldPaginator::create();
        $grid->getConfig()->addComponents($paginator, $export);
        self::assertSame([], $export->getHTMLFragments($grid));
        self::assertCount(1, $export->getMenuItems($grid));
        $response = $export->handleExport($grid);
        self::assertStringContainsString('attachment', $response->getHeader('Content-Disposition'));
        self::assertStringContainsString('Exported record', $response->getBody());
        self::assertSame($paginator, $grid->getConfig()->getComponentByType(GridFieldPaginator::class));
        $export->setPermissionChecker(static fn () => false);
        self::assertSame([], $export->getMenuItems($grid));
        $this->expectException(HTTPResponse_Exception::class);
        $export->handleExport($grid);
    }

    /** Unauthenticated users cannot invoke the default export permission. */
    public function testExportDefaultsToAdminOnly(): void
    {
        Security::setCurrentUser(null);
        $export = GridFieldMenuExportButton::create();
        self::assertSame([], $export->getMenuItems($this->grid()));
    }

    /** A GET download carries the current filters and exports only matching records. */
    public function testExportRetainsFilterState(): void
    {
        $grid = $this->grid();
        MenuRecord::create(['Title' => 'Keep record'])->write();
        MenuRecord::create(['Title' => 'Other record'])->write();
        $export = GridFieldMenuExportButton::create('after', ['Title' => 'Title']);
        $export->setPermissionChecker(static fn () => true);
        $grid->getConfig()->addComponents(GridFieldFilterHeader::create(), $export);
        $grid->getState()->GridFieldFilterHeader->Columns->Title = 'Keep';
        $item = $export->getMenuItems($grid)[0]->forTemplate($grid, 'test');
        self::assertStringContainsString('gridState', (string)$item->getField('URL'));
        $response = $export->handleExport($grid);
        self::assertStringContainsString('Keep record', $response->getBody());
        self::assertStringNotContainsString('Other record', $response->getBody());
    }

    /** Competing legacy export routes cannot bypass the adapter's authorization. */
    public function testDuplicateExportRouteFails(): void
    {
        $grid = $this->grid();
        $export = GridFieldMenuExportButton::create()->setPermissionChecker(static fn () => true);
        $grid->getConfig()->addComponents(GridFieldExportButton::create(), $export);
        $this->expectException(LogicException::class);
        $export->getMenuItems($grid);
    }

    /** The real URL handler returns a file response rather than GridField HTML. */
    public function testExportUrlDispatch(): void
    {
        $grid = $this->grid();
        MenuRecord::create(['Title' => 'Routed download'])->write();
        $export = GridFieldMenuExportButton::create('after', ['Title' => 'Title']);
        $export->setPermissionChecker(static fn () => true);
        $grid->getConfig()->addComponent($export);
        $request = new HTTPRequest('GET', 'export');
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $response = $grid->handleRequest($request);
        self::assertStringContainsString('attachment', $response->getHeader('Content-Disposition'));
        self::assertStringContainsString('Routed download', $response->getBody());
    }

    /** Two menus in one field cannot create identical controls and menu IDs. */
    public function testDuplicateMenuKeyFails(): void
    {
        $grid = $this->grid();
        $grid->getConfig()->addComponent(GridFieldActionMenu::create()->addProvider(MenuAction::class));
        $this->expectException(LogicException::class);
        $grid->getConfig()->getComponentByType(GridFieldActionMenu::class)->getHTMLFragments($grid);
    }

    /** JavaScript schemes cannot enter a link, even when labels are safe. */
    public function testUnsafeLinkFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GridFieldMenuItem::link('unsafe', 'Unsafe', 'javascript:alert(1)');
    }
}
