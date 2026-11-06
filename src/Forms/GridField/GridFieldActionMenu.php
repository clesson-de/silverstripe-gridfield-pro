<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use InvalidArgumentException;
use LogicException;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\View\Requirements;

/**
 * Presents explicitly selected, independently registered component actions.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldActionMenu extends AbstractGridFieldComponent implements GridField_HTMLProvider
{
    /** Provider types survive GridFieldConfig cloning without stale references. */
    private array $providerTypes = [];
    private ?string $title = null;
    private ?string $icon = null;
    private bool $readonlyContext = false;

    /** Gives each menu a stable local key and a normal GridField target fragment. */
    public function __construct(private string $fragment = 'buttons-before-right', private string $key = 'actions')
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $key)
            || !preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $fragment)) {
            throw new InvalidArgumentException('Menu keys and fragments must be lowercase fragment-safe identifiers.');
        }
    }

    /** Selects one registered instance of each provider class explicitly. */
    public function addProvider(string $type): static
    {
        if (!is_a($type, GridFieldMenuItemProvider::class, true) || in_array($type, $this->providerTypes, true)) {
            throw new InvalidArgumentException('Select each menu provider class only once.');
        }
        $this->providerTypes[] = $type;
        return $this;
    }

    /** Changes the menu label without affecting its stable key. */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /** Uses a translated default for the global action menu. */
    public function getTitle(): string
    {
        return $this->title ?? _t(__CLASS__ . '.ACTIONS', 'Actions');
    }

    /** Adds a CMS font icon while retaining a readable text label. */
    public function setIcon(string $icon): static
    {
        if (!preg_match('/^[a-z0-9-]+$/D', $icon)) {
            throw new InvalidArgumentException('Menu icons must be CMS font-icon identifiers.');
        }
        $this->icon = $icon;
        return $this;
    }

    /** Retains the form's readonly intent when the core resets its flag during rendering. */
    public function setReadonlyContext(bool $readonly): void
    {
        $this->readonlyContext = $readonly;
    }

    /** Produces no button when no currently visible entries remain. */
    public function getHTMLFragments($gridField): array
    {
        $this->validateMenus($gridField);
        $id = $gridField->ID() . '_Menu_' . $this->key;
        $items = $this->items($gridField, $id);
        if (!$items->exists()) {
            return [];
        }
        Requirements::javascript('clesson-de/silverstripe-gridfield-pro:client/dist/js/silverstripe-gridfield-pro.js');
        Requirements::css('clesson-de/silverstripe-gridfield-pro:client/dist/css/silverstripe-gridfield-pro.css');
        return [$this->fragment => ArrayData::create([
            'Title' => $this->getTitle(), 'MenuID' => $id, 'Items' => $items,
            'Icon' => $this->icon,
            'Error' => _t(__CLASS__ . '.ERROR', 'The action failed. Please try again.'),
        ])->renderWith(self::class)];
    }

    /** Rejects duplicate local keys before emitting conflicting DOM IDs. */
    private function validateMenus(GridField $grid): void
    {
        $keys = [];
        foreach ($grid->getConfig()->getComponentsByType(self::class) as $menu) {
            if (isset($keys[$menu->key])) {
                throw new LogicException('Action menus in one GridField require distinct keys.');
            }
            $keys[$menu->key] = true;
        }
    }

    /** Resolves providers against this config, including cloned configurations. */
    private function provider(GridField $grid, string $type): ?GridFieldMenuItemProvider
    {
        $providers = $grid->getConfig()->getComponentsByType($type);
        if (!$providers->exists() && ($this->readonlyContext || $grid->isReadonly() || $grid->isDisabled())) {
            return null;
        }
        if ($providers->count() !== 1) {
            throw new LogicException('A menu requires exactly one registered provider of type ' . $type . '.');
        }
        return $providers->first();
    }

    /** Filters readonly mutations without weakening the handler's authorization contract. */
    private function items(GridField $grid, string $id): ArrayList
    {
        $items = ArrayList::create();
        $seen = [];
        foreach ($this->providerTypes as $type) {
            $provider = $this->provider($grid, $type);
            $this->appendItems($items, $seen, $grid, $provider, $id);
        }
        return $items;
    }

    /** Keeps one shared item-ID namespace across the explicitly selected providers. */
    private function appendItems(ArrayList $items, array &$seen, GridField $grid, ?GridFieldMenuItemProvider $provider, string $id): void
    {
        if (!$provider) {
            return;
        }
        foreach ($provider->getMenuItems($grid) as $item) {
            $this->validateItem($grid, $provider, $item, $seen);
            $seen[$item->id] = true;
            if (!$item->mutating || (!$this->readonlyContext && !$grid->isReadonly() && !$grid->isDisabled())) {
                $items->push($item->forTemplate($grid, $id));
            }
        }
    }

    /** Requires unambiguous ownership in the core's first-matching action dispatch. */
    private function validateItem(GridField $grid, GridFieldMenuItemProvider $provider, mixed $item, array $seen): void
    {
        if (!$item instanceof GridFieldMenuItem || isset($seen[$item->id])) {
            throw new LogicException('Menu providers must return typed entries with distinct IDs.');
        }
        if ($item->actionName === null) {
            return;
        }
        $handlers = $this->actionHandlers($grid, $item->actionName);
        if (count($handlers) !== 1 || $handlers[0] !== $provider) {
            throw new LogicException('Menu actions require exactly one handler: their registered provider.');
        }
    }

    /** Compares names using the same case-insensitive rules as the core. */
    private function actionHandlers(GridField $grid, string $name): array
    {
        $handlers = [];
        foreach ($grid->getConfig()->getComponentsByType(GridField_ActionProvider::class) as $component) {
            if (in_array($name, array_map('strtolower', (array)$component->getActions($grid)), true)) {
                $handlers[] = $component;
            }
        }
        return $handlers;
    }
}
