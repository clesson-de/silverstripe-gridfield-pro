<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Constants\GridFieldToolbarLayout as Layout;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Control\Controller;
use SilverStripe\Model\List\ArrayList;

/**
 * Covers native fragment resolution, position replacement and immutable slots.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldToolbarTest extends SapphireTest
{
    /** Attaches an empty grid to a real form without requiring ORM rows. */
    private function grid(GridFieldConfig $config): GridField
    {
        $grid = GridField::create('Items', 'Items', ArrayList::create(), $config);
        $form = Form::create(Controller::curr(), 'ToolbarTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        return $grid;
    }

    /** The installer is idempotent and preserves the other position and handlers. */
    public function testInstallPreservesComponents(): void
    {
        $bottom = GridFieldButtonRow::create('after');
        $button = GridFieldAddNewButton::create('buttons-before-left');
        $config = GridFieldConfig::create()->addComponents(GridFieldButtonRow::create(), $bottom, $button);
        $toolbar = GridFieldToolbar::install($config);
        self::assertSame($toolbar, GridFieldToolbar::install($config));
        self::assertSame($toolbar, $config->getComponentByType(GridFieldToolbar::class));
        self::assertSame($bottom, $config->getComponentsByType(GridFieldButtonRow::class)->first());
        self::assertSame($button, $config->getComponentByType(GridFieldAddNewButton::class));
        self::assertCount(3, $config->getComponents());
    }

    /** Generic toolbars have no assumed labels, while application labels remain escaped. */
    public function testGroupLabelsAreOptionalAndEscaped(): void
    {
        $toolbar = GridFieldToolbar::create();
        $grid = $this->grid(GridFieldConfig::create()->addComponent($toolbar));
        $html = (string)$toolbar->getHTMLFragments($grid)['before'];
        self::assertStringNotContainsString('gridfield-pro-toolbar__label', $html);
        $toolbar->setGroupLabels('<Filters>', 'Actions & tools');
        $html = (string)$toolbar->getHTMLFragments($grid)['before'];
        self::assertStringContainsString('&lt;Filters&gt;', $html);
        self::assertStringContainsString('Actions &amp; tools', $html);
    }

    /** Providers can render before the toolbar and each slot appears exactly once. */
    public function testFragmentsAndFullWidthRows(): void
    {
        $toolbar = GridFieldToolbar::create();
        $slot = $toolbar->addItem('Search', Layout::RIGHT, Layout::GROW);
        $full = $toolbar->addFullWidthItem('filters');
        $provider = new class ($slot->getFragment(), $full->getFragment()) extends AbstractGridFieldComponent implements GridField_HTMLProvider {
            /** Retains target fragments for the rendering fixture. */
            public function __construct(private string $slot, private string $full)
            {
            }

            /** Emits trusted fixture controls into native fragments. */
            public function getHTMLFragments($gridField): array
            {
                return [$this->slot => '<b>Search marker</b>', $this->full => '<b>Filter marker</b>',
                    'buttons-before-left' => '<b>Legacy marker</b>'];
            }
        };
        $grid = $this->grid(GridFieldConfig::create()->addComponents($provider, $toolbar));
        $html = (string)$grid->FieldHolder();
        self::assertSame('toolbar-before-search', $slot->getFragment());
        foreach (['Search marker', 'Filter marker', 'Legacy marker'] as $marker) {
            self::assertSame(1, substr_count($html, $marker));
        }
        self::assertStringContainsString('gridfield-pro-toolbar__item--full', $html);
        self::assertStringNotContainsString('$DefineFragment', $html);
    }

    /** Slots are immutable, so cloned configs can add slots independently. */
    public function testCloningDoesNotChangeOriginalToolbar(): void
    {
        $config = GridFieldConfig::create()->addComponent(GridFieldToolbar::create());
        $copy = clone $config;
        $copy->getComponentByType(GridFieldToolbar::class)->addItem('copy');
        $original = $config->getComponentByType(GridFieldToolbar::class)->getHTMLFragments($this->grid($config));
        self::assertStringNotContainsString('toolbar-before-copy', (string)$original['before']);
    }

    /** Normalization cannot create two slots with the same native fragment. */
    public function testDuplicateSlotIsRejected(): void
    {
        $toolbar = GridFieldToolbar::create();
        $toolbar->addItem('Search');
        $this->expectException(InvalidArgumentException::class);
        $toolbar->addItem('search');
    }

    /** Invalid identifiers cannot escape a fragment definition. */
    public function testInvalidSlotIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GridFieldToolbar::create()->addItem('bad)fragment');
    }

    /** Full-width rows cannot share their space with other items. */
    public function testFullWidthRowIsExclusive(): void
    {
        $toolbar = GridFieldToolbar::create();
        $full = $toolbar->addFullWidthItem('filter');
        $this->expectException(InvalidArgumentException::class);
        $toolbar->addItem('other', row: $full->row);
    }

    /** Two containers must not define the same legacy fragments. */
    public function testConflictingRowsFailExplicitly(): void
    {
        $config = GridFieldConfig::create()->addComponents(GridFieldToolbar::create(), GridFieldButtonRow::create());
        $this->expectException(LogicException::class);
        $config->getComponentByType(GridFieldToolbar::class)->getHTMLFragments($this->grid($config));
    }
}
