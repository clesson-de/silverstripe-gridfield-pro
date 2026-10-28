<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;

/**
 * Retains a new record's selected type throughout the core form and save lifecycle.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldAddNewDropdownItemRequest extends GridFieldDetailForm_ItemRequest
{
    /** Uses the normal item URL once the selected concrete record has been saved. */
    public function Link($action = null): string
    {
        if ($this->record->isInDB()) {
            return parent::Link($action);
        }
        return Controller::join_links(
            $this->gridField->Link('new-class'),
            str_replace('\\', '-', get_class($this->record)),
            $action
        );
    }

    /** Prevents posted class fields from overriding the type validated by the creation route. */
    protected function saveFormIntoRecord($data, $form): DataObject
    {
        if (isset($data['ClassName']) && $data['ClassName'] !== get_class($this->record)) {
            throw new HTTPResponse_Exception('Invalid record type.', 400);
        }
        return parent::saveFormIntoRecord($data, $form);
    }
}
