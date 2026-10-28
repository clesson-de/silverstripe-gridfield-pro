<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Persisted relation owner for component integration tests.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class SelectOwner extends DataObject implements TestOnly
{
    private static string $table_name = 'GridFieldProTest_Owner';
    private static array $db = ['Title' => 'Varchar(100)'];
    private static array $many_many = ['Items' => SelectRecord::class];

    /** Test owners are editable only by members with the test permission. */
    public function canEdit($member = null): bool
    {
        return Permission::check('GRIDFIELD_PRO_TEST_EDIT', 'any', $member);
    }
}
