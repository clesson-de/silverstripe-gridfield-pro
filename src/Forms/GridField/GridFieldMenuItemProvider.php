<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldComponent;

/**
 * Explicitly exposes a component's supported global actions to a menu.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
interface GridFieldMenuItemProvider extends GridFieldComponent
{
    /**
     * Returns currently visible items; handlers must independently authorize requests.
     *
     * @return GridFieldMenuItem[]
     */
    public function getMenuItems(GridField $grid): array;
}
