<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Minimal export record without application permission dependencies.
 *
 * @property string $Title
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class MenuRecord extends DataObject implements TestOnly
{
    private static $table_name = 'GridFieldProTest_MenuRecord';
    private static $db = ['Title' => 'Varchar(100)'];

    /** Makes CSV visibility independent of the current member's permissions. */
    public function canView($member = null): bool
    {
        return true;
    }
}
