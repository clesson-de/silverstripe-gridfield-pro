<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Search fixture with a nullable relation and an independently restricted source list.
 *
 * @property string $Title
 * @property string $Description
 * @property bool $Visible
 * @property int $OwnerID
 * @method Member Owner()
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class SearchRecord extends DataObject implements TestOnly
{
    private static $table_name = 'GridFieldPro_SearchRecord';
    private static $db = [
        'Title' => 'Varchar(100)',
        'Description' => 'Text',
        'Visible' => 'Boolean',
    ];
    private static $has_one = ['Owner' => Member::class];
}
