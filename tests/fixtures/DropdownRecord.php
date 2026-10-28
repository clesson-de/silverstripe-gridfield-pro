<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use SilverStripe\Security\Permission;

/**
 * Concrete candidate requiring creation permission and the correct relation context.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class DropdownRecord extends SelectRecord
{
    /** Verifies that a relation owner is supplied to creation permission checks. */
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('GRIDFIELD_PRO_CREATE', 'any', $member)
            && ($context['Parent'] ?? null) instanceof SelectOwner;
    }
}
