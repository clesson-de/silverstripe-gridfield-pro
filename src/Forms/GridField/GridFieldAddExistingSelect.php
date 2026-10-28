<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\View\Requirements;

/**
 * Compact AJAX-backed relation picker, retaining the core search configuration API.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldAddExistingSelect extends GridFieldAddExistingAutocompleter
{
    /** A distinct action avoids collisions with other GridField components. */
    private const ACTION = 'addexistingselect';

    /** Renders one searchable select; the GridField action is submitted automatically. */
    public function getHTMLFragments($gridField): array
    {
        if (!$this->canLink($gridField)) {
            return [];
        }
        Requirements::javascript('clesson-de/silverstripe-gridfield-pro:client/dist/js/silverstripe-gridfield-pro.js');
        Requirements::css('clesson-de/silverstripe-gridfield-pro:client/dist/css/silverstripe-gridfield-pro.css');
        return [$this->targetFragment => ArrayData::create([
            'Select' => $this->selectField($gridField),
            'Action' => $this->linkAction($gridField),
            'Error' => _t(__CLASS__ . '.ERROR', 'The record could not be loaded or linked. Please try again.'),
            'Loading' => _t(__CLASS__ . '.LOADING', 'Loading...'),
            'NoResults' => _t(__CLASS__ . '.NO_RESULTS', 'No matching records found.'),
            'MinLength' => _t(__CLASS__ . '.MIN_LENGTH', 'Enter at least two characters to search.'),
        ])->renderWith(self::class)];
    }

    /** Avoids CMS Chosen initialisation because this select uses remote Tom Select options. */
    private function selectField(GridField $gridField): DropdownField
    {
        /** @var DropdownField $selectField */
        $selectField = DropdownField::create($gridField->getName() . '[ExistingID]', '', []);
        $selectField->setEmptyString('');
        $selectField->addExtraClass('gridfield-add-existing-select__input no-chosen no-change-track');
        $selectField->setAttribute('data-search-url', $gridField->Link('existing-search'));
        $selectField->setAttribute('aria-label', $this->getPlaceholderText($gridField->getModelClass()));
        $selectField->setAttribute('placeholder', $this->getPlaceholderText($gridField->getModelClass()));
        return $selectField;
    }

    /** Uses Silverstripe's CSRF-protected GridField form action rather than a custom write URL. */
    private function linkAction(GridField $gridField): GridField_FormAction
    {
        /** @var GridField_FormAction $actionField */
        $actionField = GridField_FormAction::create(
            $gridField, self::ACTION, _t(__CLASS__ . '.LINK', 'Link existing'), self::ACTION, []
        );
        $actionField->addExtraClass('gridfield-add-existing-select__action no-change-track');
        $actionField->setAttribute('hidden', true);
        $actionField->setForm($gridField->getForm());
        return $actionField;
    }

    /** Exposes only the remote read endpoint, not the original autocomplete route. */
    public function getURLHandlers($gridField): array
    {
        return ['existing-search' => 'doSearch'];
    }

    /** Reuses core search limits, custom lists, labels and exclusion of linked records. */
    public function doSearch($gridField, $request): HTTPResponse
    {
        $this->requireLinkPermission($gridField);
        $query = $request->getVar('gridfield_relationsearch');
        if (!$request->isGET() || !is_string($query) || mb_strlen($query) > 200) {
            throw new HTTPResponse_Exception('Invalid search request.', 400);
        }
        if (mb_strlen(trim($query)) < 2) {
            return HTTPResponse::create('[]')
                ->addHeader('Content-Type', 'application/json')->addHeader('Cache-Control', 'no-store');
        }
        return parent::doSearch($gridField, $request)->addHeader('Cache-Control', 'no-store');
    }

    /** Registers the single automatic-link action. */
    public function getActions($gridField): array
    {
        return [self::ACTION];
    }

    /** Checks CSRF, owner permissions and the configured search scope before linking. */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data): void
    {
        $this->requireLinkPermission($gridField);
        $form = $gridField->getForm();
        $request = $form->getController()->getRequest();
        if (!$request->isPOST() || !$form->getSecurityToken()->checkRequest($request)) {
            throw new HTTPResponse_Exception('Invalid link request.', 400);
        }
        $id = $data[$gridField->getName()]['ExistingID'] ?? null;
        if ($actionName !== self::ACTION || !is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) {
            throw new HTTPResponse_Exception('Invalid record ID.', 400);
        }
        $gridField->getList()->add($this->linkableRecord($gridField, (int)$id));
    }

    /** Never accepts a link ID injected through the original component's GridState. */
    public function getManipulatedData(GridField $gridField, SS_List $dataList): SS_List
    {
        return $dataList;
    }

    /** Only links visible records inside the developer-supplied candidate list. */
    private function linkableRecord(GridField $gridField, int $id): DataObject
    {
        $record = ($this->searchList ?? DataObject::get($gridField->getModelClass()))->byID($id);
        if (!$record || !is_a($record, $gridField->getModelClass())) {
            throw new HTTPResponse_Exception('Record not found.', 404);
        }
        if (!$record->canView()) {
            throw new HTTPResponse_Exception('Access denied.', 403);
        }
        return $record;
    }

    /** Requires a persisted, editable owner and an editable ORM relation. */
    private function canLink(GridField $gridField): bool
    {
        $owner = $gridField->getForm()?->getRecord();
        return $owner instanceof DataObject && $owner->isInDB() && $owner->canEdit()
            && $gridField->getList() instanceof RelationList
            && !$gridField->isReadonly() && !$gridField->isDisabled();
    }

    /** Rejects direct requests even if the UI was not rendered. */
    private function requireLinkPermission(GridField $gridField): void
    {
        if (!$this->canLink($gridField)) {
            throw new HTTPResponse_Exception('Access denied.', 403);
        }
    }
}
