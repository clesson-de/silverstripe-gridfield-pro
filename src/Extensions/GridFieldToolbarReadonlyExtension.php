<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Extensions;

use Clesson\Silverstripe\Forms\GridField\GridFieldActionMenu;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;

/**
 * Preserves readonly intent for opt-in menus across the core render flag reset.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Extensions
 */
class GridFieldToolbarReadonlyExtension extends Extension
{
    /** Marks only menus retained by the field's explicit readonly whitelist. */
    protected function afterPerformReadonlyTransformation(GridField $copy): void
    {
        foreach ($copy->getConfig()->getComponentsByType(GridFieldActionMenu::class) as $menu) {
            $menu->setReadonlyContext(true);
        }
    }

    /** Captures explicit readonly/disabled state before FieldHolder resets it. */
    protected function onBeforeRenderHolder(GridField $grid): void
    {
        if (!$grid->isReadonly() && !$grid->isDisabled()) {
            return;
        }
        foreach ($grid->getConfig()->getComponentsByType(GridFieldActionMenu::class) as $menu) {
            $menu->setReadonlyContext(true);
        }
    }
}
