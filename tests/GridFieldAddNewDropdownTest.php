<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdownItemRequest;
use LogicException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Security;

/**
 * Covers class filtering, fragment rendering and core-only creation routing.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldAddNewDropdownTest extends SapphireTest
{
    protected $usesDatabase = true;
    protected static $extra_dataobjects = [
        SelectOwner::class, SelectRecord::class, DropdownRecord::class,
    ];

    /** Binds a persisted relation owner to the component's standard detail form. */
    private function grid(): GridField
    {
        $this->logInWithPermission('ADMIN');
        $owner = SelectOwner::create(['Title' => 'Owner']);
        $owner->write();
        $grid = GridField::create('Items', 'Items', $owner->Items(), GridFieldConfig::create()->addComponents(
            GridFieldAddNewDropdown::create(), GridFieldDetailForm::create()
        ));
        $controller = Controller::create();
        $controller->setRequest(Controller::curr()->getRequest());
        $form = Form::create($controller, 'Test', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        $form->loadDataFrom($owner);
        return $grid;
    }

    /** Reads the tested component from its real GridField configuration. */
    private function component(GridField $grid): GridFieldAddNewDropdown
    {
        return $grid->getConfig()->getComponentByType(GridFieldAddNewDropdown::class);
    }

    /** Single-class grids use a direct link and retain configurable title and placement. */
    public function testSingleClassAndFragments(): void
    {
        $grid = $this->grid();
        $component = $this->component($grid);
        $component->setClasses([SelectRecord::class])->setTitle('Create <record>');
        $grid->getConfig()->addComponents(GridFieldButtonRow::create('before'), GridFieldButtonRow::create('after'));
        foreach (['before', 'buttons-before-left', 'buttons-before-right', 'buttons-after-left', 'buttons-after-right'] as $fragment) {
            $component->setFragment($fragment);
            self::assertSame($fragment, $component->getFragment());
            $html = (string)$component->getHTMLFragments($grid)[$fragment];
            self::assertStringContainsString('gridfield-add-new-dropdown__direct', $html);
            self::assertStringContainsString('Create &lt;record&gt;', $html);
            self::assertStringNotContainsString('dropdown-toggle', $html);
            self::assertStringContainsString('/new-class/' . str_replace('\\', '-', SelectRecord::class), $html);
            self::assertSame(1, substr_count((string)$grid->FieldHolder(), 'gridfield-add-new-dropdown__direct'));
        }
    }

    /** Unsupported classes are never offered, and zero choices render no control. */
    public function testClassAllowlistAndPermissions(): void
    {
        $grid = $this->grid();
        $component = $this->component($grid);
        $component->setClasses([SelectOwner::class, 'MissingClass', Controller::class, SelectRecord::class]);
        self::assertSame([SelectRecord::class => SelectRecord::singleton()->i18n_singular_name()], $component->getClasses($grid));
        $component->setClasses([]);
        self::assertSame([], $component->getHTMLFragments($grid));
        $component->setClasses([SelectRecord::class]);
        $grid->setReadonly(true);
        self::assertSame([], $component->getClasses($grid));
        $grid->setReadonly(false)->setDisabled(true);
        self::assertSame([], $component->getClasses($grid));
        $grid->setDisabled(false);
        Security::setCurrentUser(null);
        self::assertSame([], $component->getClasses($grid));
    }

    /** Multiple allowed classes produce escaped menu labels and exclude forbidden types. */
    public function testMultipleClassesAndCreateContext(): void
    {
        $grid = $this->grid();
        $component = $this->component($grid);
        $component->setClasses([
            SelectRecord::class => 'Plain record',
            DropdownRecord::class => '<b>Special record</b>',
        ]);
        self::assertCount(2, $component->getClasses($grid));
        $html = (string)$component->getHTMLFragments($grid)['before'];
        self::assertStringContainsString('aria-haspopup="menu"', $html);
        self::assertStringContainsString('&lt;b&gt;Special record&lt;/b&gt;', $html);
        self::assertSame(2, substr_count($html, 'role="menuitem"'));
        $this->logInWithPermission('GRIDFIELD_PRO_TEST_EDIT');
        self::assertArrayNotHasKey(DropdownRecord::class, $component->getClasses($grid));
        $component->setClasses([DropdownRecord::class]);
        $request = new HTTPRequest('GET', 'new-class/' . str_replace('\\', '-', DropdownRecord::class));
        $request->match('new-class/$ClassName!', true);
        $this->expectException(HTTPResponse_Exception::class);
        $component->handleAdd($grid, $request);
    }

    /** The default button label follows the current CMS locale. */
    public function testTranslatedTitle(): void
    {
        $locale = i18n::get_locale();
        try {
            i18n::set_locale('de_DE');
            self::assertSame('Neu', GridFieldAddNewDropdown::create()->getTitle());
            i18n::set_locale('en_US');
            self::assertSame('New', GridFieldAddNewDropdown::create()->getTitle());
        } finally {
            i18n::set_locale($locale);
        }
    }

    /** Only an explicitly permitted token can open the selected record's creation form. */
    public function testCreationHandlerAndForgedToken(): void
    {
        $grid = $this->grid();
        $component = $this->component($grid);
        $token = str_replace('\\', '-', SelectRecord::class);
        $request = new HTTPRequest('GET', 'new-class/' . $token);
        $request->match('new-class/$ClassName!', true);
        $handler = $component->handleAdd($grid, $request);
        self::assertInstanceOf(GridFieldAddNewDropdownItemRequest::class, $handler);
        self::assertStringEndsWith('/new-class/' . $token . '/ItemEditForm', $handler->Link('ItemEditForm'));
        $request = new HTTPRequest('GET', 'new-class/' . str_replace('\\', '-', SelectOwner::class));
        $request->match('new-class/$ClassName!', true);
        $this->expectException(HTTPResponse_Exception::class);
        $component->handleAdd($grid, $request);
    }

    /** Missing core detail forms produce an explicit configuration error. */
    public function testRequiresDetailForm(): void
    {
        $grid = $this->grid();
        $grid->getConfig()->removeComponentsByType(GridFieldDetailForm::class);
        $this->expectException(LogicException::class);
        $this->component($grid)->getHTMLFragments($grid);
    }
}
