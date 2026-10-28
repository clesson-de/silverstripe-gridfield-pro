<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Candidate with explicit visibility for search and write permission tests.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class SelectRecord extends DataObject implements TestOnly
{
    private static string $table_name = 'GridFieldProTest_Record';
    private static array $db = ['Title' => 'Varchar(100)', 'Visible' => 'Boolean'];
    private static array $defaults = ['Visible' => true];

    /** Hidden candidates must never be returned or linked. */
    public function canView($member = null): bool
    {
        return (bool)$this->Visible;
    }
}
