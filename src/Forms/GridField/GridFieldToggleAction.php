<?php

namespace Clesson\Silverstripe\Forms\GridField;

use Clesson\CodeGenerator\Forms\GridFieldAction_Toggle;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;

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
    private string $trueLabel;

    /**
     * @var string
     */
    private string $falseLabel;

    /**
     * Constructor.
     *
     * @param string $columnName Name of the boolean column to be toggled
     */
    public function __construct(string $columnName, string $trueLabel='Deactivate', string $falseLabel='Activate')
    {
        $this->columnName = $columnName;
        $this->trueLabel = $trueLabel;
        $this->falseLabel = $falseLabel;
    }

    /**
     * @param $gridField
     * @param $columns
     * @return void
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('ToggleBoolean', $columns)) {
            $columns[] = 'ToggleBoolean';
        }
    }

    /**
     * @inheritdoc
     */
    public function getColumnsHandled($gridField)
    {
        return ['ToggleBoolean'];
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
        $label = $currentValue ? $this->trueLabel : $this->falseLabel;
        $action = $currentValue ? static::DEACTIVATE : static::ACTIVATE;

        $button = GridField_FormAction::create(
            $gridField,
            "ToggleBoolean{$record->ID}",
            $label,
            'toggleBoolean',
            ['RecordID' => $record->ID, 'Action' => $action]
        );

        $button->addExtraClass('btn btn-secondary action-toggle-boolean');
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
        return ['class' => 'grid-field-toggle-boolean'];
    }

    /**
     * @param $gridField
     * @param $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        return ['title' => ucfirst($this->columnName)];
    }

    /**
     * @param $gridField
     * @return string[]
     */
    public function getActions($gridField)
    {
        return ['toggleBoolean'];
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
        if ($actionName === 'toggleboolean') {
            $record = $gridField->getList()->byID($arguments['RecordID']);
            if ($record && $record->canEdit()) {
                $record->{$this->columnName} = !$record->{$this->columnName};
                $record->write();


                Controller::curr()->getResponse()
                    ->setStatusCode(200)
                    ->addHeader('X-Status', _t(__CLASS__.'.ToggledFeedback', 'The value has been changed.'));

            }
        }
    }
}
