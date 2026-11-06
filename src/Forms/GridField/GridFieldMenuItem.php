<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use InvalidArgumentException;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Model\ArrayData;

/**
 * Typed menu entry rendered as a native action or an escaped link.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
final class GridFieldMenuItem
{
    /** Retains presentation independently of the registered action handler. */
    private function __construct(
        public readonly string $id,
        private readonly string $label,
        public readonly ?string $actionName = null,
        private readonly array $arguments = [],
        private readonly ?string $url = null,
        public readonly bool $mutating = false,
        private readonly bool $download = false,
        private readonly bool $disabled = false,
        private readonly ?string $confirmation = null,
        private readonly ?string $icon = null,
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $id)) {
            throw new InvalidArgumentException('Menu item IDs must be lowercase fragment-safe identifiers.');
        }
    }

    /** Defines a normal POST action dispatched by the existing GridField handler. */
    public static function action(
        string $id,
        string $label,
        string $actionName,
        array $arguments = [],
        bool $mutating = true,
        bool $disabled = false,
        ?string $confirmation = null,
        ?string $icon = null,
    ): self {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $actionName)) {
            throw new InvalidArgumentException('Menu action names must be lowercase identifiers.');
        }
        return new self($id, $label, $actionName, $arguments, mutating: $mutating,
            disabled: $disabled, confirmation: $confirmation, icon: $icon);
    }

    /** Defines navigation or a read-only download, never a mutating GET endpoint. */
    public static function link(
        string $id,
        string $label,
        string $url,
        bool $download = false,
        bool $disabled = false,
        ?string $icon = null,
    ): self {
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)
            || str_starts_with($url, '//') || str_contains($url, '\\')
            || (parse_url($url, PHP_URL_SCHEME) !== null && !preg_match('/^https?:\/\//i', $url))) {
            throw new InvalidArgumentException('Menu links must use a relative or HTTP(S) URL.');
        }
        return new self($id, $label, url: $url, download: $download, disabled: $disabled, icon: $icon);
    }

    /** Provides escaped link data or a trusted native FormAction to the template. */
    public function forTemplate(GridField $grid, string $menuID): ArrayData
    {
        return ArrayData::create([
            'Label' => $this->label,
            'URL' => $this->url,
            'Download' => $this->download,
            'Disabled' => $this->disabled,
            'Action' => $this->actionName === null ? null : $this->formAction($grid, $menuID),
            'Icon' => $this->icon,
        ]);
    }

    /** Preserves the core StateStore, form binding and click dispatch. */
    private function formAction(GridField $grid, string $menuID): GridField_FormAction
    {
        $action = GridField_FormAction::create(
            $grid, $menuID . '_' . $this->id, $this->label, $this->actionName, $this->arguments
        );
        $action->setForm($grid->getForm());
        $action->addExtraClass('dropdown-item gridfield-action-menu__action no-change-track');
        $action->setAttribute('role', 'menuitem');
        $action->setAttribute('data-menu-item', true);
        $action->setAttribute('aria-disabled', $this->disabled ? 'true' : 'false');
        $action->setDisabled($this->disabled);
        $action->setAttribute('data-confirm', $this->confirmation);
        $action->setIcon($this->icon);
        return $action;
    }
}
