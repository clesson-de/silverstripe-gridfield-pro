<?php

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;

/**
 * This class is a GridField component that toggles the value of a Boolean column.
 *
 * ```php
 * $gridField = $fields->fieldByName('Items');
 * $gridFieldConfig = $gridField->getConfig();
 * $gridFieldConfig->addComponent(new \Clesson\Silverstripe\Forms\GridField\GridFieldToggleAction('Active', 'Deactivate', 'Activate'));
 * ```
 */
class GridFieldToggleAction extends AbstractGridFieldComponent implements GridField_ColumnProvider, GridField_ActionProvider
{

    public const ACTION_NAME = 'toggle_action';

    /**
     * @var string
     */
    public const ACTIVATE = 'activate';

    /**
     * @var string
     */
    public const DEACTIVATE = 'deactivate';

    /**
     * @var string
     */
    private string $columnName;

    /**
     * @var string
     */
    private string $columnTitle;

    /**
     * @var string
     */
    private string $trueLabel;

    /**
     * @var string
     */
    private string $falseLabel;

    /**
     * @var bool Specifies that only one of the records in the specified column may be true
     */
    private bool $unique;

    /**
     * Constructor.
     *
     * @param string $columnName Name of the boolean column to be toggled
     */
    public function __construct(string $columnName, string $columnTitle, string $trueLabel = '', string $falseLabel = '', bool $unique = false)
    {
        $this->columnName = $columnName;
        $this->columnTitle = $columnTitle;
        $this->trueLabel = $trueLabel;
        $this->falseLabel = $falseLabel;
        $this->unique = $unique;
    }

    public function setUnique(bool $unique): GridFieldToggleAction
    {
        $this->unique = $unique;
        return $this;
    }

    /**
     * @param $gridField
     * @param $columns
     * @return void
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array($this->columnName, $columns)) {
            $columns[] = $this->columnName;
        }
    }

    /**
     * @inheritdoc
     */
    public function getColumnsHandled($gridField)
    {
        return [
            $this->columnName
        ];
    }

    /**
     * @param $gridField
     * @param $record
     * @param $columnName
     * @return string
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!$record->canEdit()) {
            return '';
        }

        $currentValue = $record->{$this->columnName};
        $extraClasses = ['btn', 'btn-secondary', 'action-toggle-boolean', 'icon'];
        if ($currentValue) {
            $label = $this->trueLabel;
            $action = static::DEACTIVATE;
            $extraClasses[] = 'font-icon-check-mark-circle';
        } else {
            $label = $this->falseLabel;
            $action = static::ACTIVATE;
            $extraClasses[] = 'font-icon-block';
        }

        $button = GridField_FormAction::create(
            $gridField,
            implode('-', [static::ACTION_NAME, $this->columnName, $record->ID]), // name
            $label,
            self::ACTION_NAME,
            [
                'record_id' => $record->ID,
                'action' => $action,
                'column_name' => $this->columnName,
                'unique' => $this->unique,
            ]
        );
        $button->addExtraClass(implode(' ', $extraClasses));
        return $button->Field();
    }

    /**
     * @param $gridField
     * @param $record
     * @param $columnName
     * @return string[]
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return [
            'class' => 'grid-field-toggle-boolean'
        ];
    }

    /**
     * @param $gridField
     * @param $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === $this->columnName) {
            return [
                'title' => $this->columnTitle
            ];
        }
        return parent::getColumnMetadata($gridField, $columnName);
    }

    /**
     * @param $gridField
     * @return string[]
     */
    public function getActions($gridField)
    {
        return [self::ACTION_NAME];
    }

    /**
     * @param GridField $gridField
     * @param $actionName
     * @param $arguments
     * @param $data
     * @return void
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === self::ACTION_NAME) {
            $recordID = $arguments['record_id'];
            $columnName = $arguments['column_name'];
            $unique = $arguments['unique'];
            $action = $arguments['action'];
            $value = $action === static::ACTIVATE ? true : false;
            $manipulated = 0;
            if ($unique) {
                if ($value && $records = $gridField->getList()) {
                    foreach ($records as $record) {
                        if ($this->toggleRecord($record, $columnName, ($record->ID === $recordID))) {
                            $manipulated++;
                        }
                    }
                }
            } else if ($record = $gridField->getList()->byID($recordID)) {
                if ($this->toggleRecord($record, $columnName, $value)) {
                    $manipulated++;
                }
            }

            if ($manipulated) {
                Controller::curr()->getResponse()
                    ->setStatusCode(200)
                    ->addHeader('X-Status', _t(__CLASS__ . '.ToggledFeedback', 'The value {columnTitle} has been changed.', ['columnTitle' => $this->columnTitle]));
            }
        }
    }

    /**
     * @param $record
     * @param string $columnName
     * @param bool $value
     * @return bool
     */
    protected function toggleRecord(DataObject $record, string $columnName, bool $value): bool
    {
        $manipulated = false;
        if ($record && $record->canEdit()) {
            $record->{$columnName} = $value;
            $record->write();
            $manipulated = true;
        }
        return $manipulated;
    }

}
