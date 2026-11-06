<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use Clesson\Silverstripe\Constants\GridFieldToolbarLayout as Layout;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;

/**
 * Opt-in responsive button row with native legacy fragments and named slots.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldToolbar extends GridFieldButtonRow
{
    /** Registered slots retain their insertion order. */
    private array $items = [];
    private ?string $filterLabel = null;
    private ?string $actionLabel = null;

    /** Labels the two groups without assuming that every toolbar's left side contains filters. */
    public function setGroupLabels(string $filters, string $actions): static
    {
        $this->filterLabel = $filters;
        $this->actionLabel = $actions;
        return $this;
    }

    /** Limits toolbar roots to the core's above/below-table positions. */
    public function __construct(string $position = 'before')
    {
        if (!in_array($position, ['before', 'after'], true)) {
            throw new InvalidArgumentException('Toolbar position must be before or after.');
        }
        parent::__construct($position);
    }

    /** Replaces only the matching core button row, without touching handlers. */
    public static function install(GridFieldConfig $config, string $position = 'before'): static
    {
        $toolbar = static::create($position);
        $rows = array_filter(
            $config->getComponentsByType(GridFieldButtonRow::class)->toArray(),
            static fn ($row) => $row->targetFragment === $position
        );
        if (count($rows) === 1 && reset($rows) instanceof static) {
            return reset($rows);
        }
        static::removeCoreRows($config, $rows);
        $config->addComponent($toolbar);
        return $toolbar;
    }

    /** Validates all rows before making any change to a conflicting configuration. */
    private static function removeCoreRows(GridFieldConfig $config, array $rows): void
    {
        foreach ($rows as $row) {
            if (get_class($row) !== GridFieldButtonRow::class) {
                throw new LogicException('Replace the custom button row explicitly before installing a toolbar.');
            }
        }
        foreach ($rows as $row) {
            $config->removeComponent($row);
        }
    }

    /** Keeps explicitly chosen read-only providers and presentation during core transformation. */
    public static function preserveReadonly(GridField $grid, array $providerTypes = []): void
    {
        $types = [static::class, GridFieldActionMenu::class, ...$providerTypes];
        foreach ($providerTypes as $type) {
            if (!is_a($type, GridFieldMenuItemProvider::class, true)) {
                throw new InvalidArgumentException('Readonly providers must implement GridFieldMenuItemProvider.');
            }
        }
        $grid->setReadonlyComponents(array_values(array_unique([...$grid->getReadonlyComponents(), ...$types])));
    }

    /** Adds a stable, individually addressable fragment slot. */
    public function addItem(
        string $id,
        string $alignment = Layout::LEFT,
        string $width = Layout::CONTENT,
        int $row = 0,
    ): GridFieldToolbarItem {
        $id = strtolower($id);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $id) || isset($this->items[$id])) {
            throw new InvalidArgumentException('Toolbar item IDs must be unique fragment-safe identifiers.');
        }
        $this->validateRow($row, $width);
        return $this->items[$id] = new GridFieldToolbarItem(
            'toolbar-' . $this->targetFragment . '-' . $id, $row, $alignment, $width
        );
    }

    /** Appends a dedicated full-width row after all current rows. */
    public function addFullWidthItem(string $id): GridFieldToolbarItem
    {
        $row = max([0, ...array_map(static fn ($item) => $item->row, $this->items)]) + 1;
        return $this->addItem($id, width: Layout::FULL, row: $row);
    }

    /** Rejects row definitions that could place controls beside a full-width item. */
    private function validateRow(int $row, string $width): void
    {
        if ($width === Layout::FULL && $row === 0) {
            throw new InvalidArgumentException('Row zero is reserved for legacy left/right fragments.');
        }
        foreach ($this->items as $item) {
            if ($item->row === $row && ($width === Layout::FULL || $item->width === Layout::FULL)) {
                throw new InvalidArgumentException('A full-width toolbar item requires its own row.');
            }
        }
    }

    /** Renders definitions only; the GridField resolves the provider content. */
    public function getHTMLFragments($gridField): array
    {
        $this->validateRegistration($gridField->getConfig());
        Requirements::css('clesson-de/silverstripe-gridfield-pro:client/dist/css/silverstripe-gridfield-pro.css');
        return [$this->targetFragment => ArrayData::create([
            'Label' => _t(__CLASS__ . '.LABEL', 'List controls'),
            'Rows' => $this->rows(),
        ])->renderWith(self::class)];
    }

    /** Prevents duplicate definitions even when installed without the helper. */
    private function validateRegistration(GridFieldConfig $config): void
    {
        $matches = 0;
        foreach ($config->getComponentsByType(GridFieldButtonRow::class) as $row) {
            if ($row->targetFragment === $this->targetFragment) {
                $matches++;
            }
        }
        if ($matches > 1) {
            throw new LogicException('Only one button row or toolbar may define each position.');
        }
    }

    /** Builds rows in numerical order, preserving order within alignment groups. */
    private function rows(): ArrayList
    {
        $rows = [0 => $this->emptyRow()];
        foreach ($this->items as $item) {
            $rows[$item->row] ??= $this->emptyRow();
            $key = $item->width === Layout::FULL ? 'Full' : ucfirst($item->alignment);
            $rows[$item->row][$key]->push($item->forTemplate());
        }
        $rows[0]['LegacyLeft'] = $this->legacyFragment(Layout::LEFT);
        $rows[0]['LegacyRight'] = $this->legacyFragment(Layout::RIGHT);
        $rows[0]['FilterLabel'] = $this->filterLabel;
        $rows[0]['ActionLabel'] = $this->actionLabel;
        ksort($rows);
        return ArrayList::create(array_map(static fn ($row) => ArrayData::create($row), array_values($rows)));
    }

    /** Creates independent lists for each alignment group. */
    private function emptyRow(): array
    {
        return ['Left' => ArrayList::create(), 'Right' => ArrayList::create(), 'Full' => ArrayList::create()];
    }

    /** Keeps existing buttons-before/after-left/right integrations operational. */
    private function legacyFragment(string $alignment): DBField
    {
        return DBField::create_field(
            'HTMLText', '$DefineFragment(buttons-' . $this->targetFragment . '-' . $alignment . ')'
        );
    }
}
