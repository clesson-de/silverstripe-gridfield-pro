<?php

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Model\List\SS_List;
use SilverStripe\Model\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * A filter component for the GridField. This component displays a button bar, one or more of which can be selected to
 * limit the search result.
 *
 * ```php
 * use Clesson\Silverstripe\Forms\GridField\GridField_ButtonFilter;
 *
 * $gridField = $fields->fieldByName('Items');
 * $gridFieldConfig = $gridField->getConfig();
 *
 * // Create an instance of the ButtonFilter component
 * $component = new GridField_ButtonFilter("before", "Name", [
 *  "person" => _t(__CLASS__.".Person", "Person"),
 *  "company" => _t(__CLASS__.".Company", "Company"),
 *  "organisation" => _t(__CLASS__.".Organisation", "Organisation"),
 *  "club" => _t(__CLASS__.".Club", "Club"),
 * ]);
 *
 * // add the component to the GridField config
 * $gridFieldConfig->addComponent($component);
 * ```
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridField_ButtonFilter extends AbstractGridFieldComponent implements GridField_HTMLProvider, GridField_DataManipulator, GridField_ActionProvider
{

    public const ACTION_NAME = 'button_filter';

    /**
     * @var bool Specify whether multiple selection should be possible (true) or not (false)..
     */
    protected bool $_multiselect = false;

    /**
     * @var string The property to which the filter is to be applied.
     */
    protected string $_property = "";

    /**
     * @var array A list of the currently selected values. This list should only contain more than one value if multiselect is activated..
     */
    protected array $_selectedValues = [];

    /**
     * @var string The HTML fragment in which the UI is written..
     */
    protected string $_targetFragment = "before";

    /**
     * @var array A list of values from which the user can choose. An associative array must be specified here. The key contains the value to be filtered by and the value corresponds to the title shown to the user..
     */
    protected array $_values = [];

    /**
     * @param string $targetFragment
     */
    public function __construct(string $targetFragment = 'before', string $property = '', array $values = [])
    {
        $this->setTargetFragment($targetFragment);
        $this->setProperty($property);
        $this->setValues($values);
    }

    /**
     * Specify whether multiple selection should be possible (true) or not (false).
     * @param bool $multiselect
     * @return $this
     */
    public function setMultiselect(bool $multiselect): GridField_ButtonFilter
    {
        $this->_multiselect = $multiselect;
        return $this;
    }

    /**
     * Specify whether multiple selection should be possible (true) or not (false).
     * @return bool the multiselect
     */
    public function getMultiselect(): bool
    {
        return $this->_multiselect;
    }

    /**
     * The property to which the filter is to be applied
     * @param string $property
     * @return $this
     */
    public function setProperty(string $property): GridField_ButtonFilter
    {
        $this->_property = $property;
        return $this;
    }

    /**
     * The property to which the filter is to be applied
     * @return string the property
     */
    public function getProperty(): string
    {
        return $this->_property;
    }

    /**
     * A list of the currently selected values. This list should only contain more than one value if multiselect is activated.
     * @param array $selectedValues
     * @return $this
     */
    public function setSelectedValues(array $selectedValues): GridField_ButtonFilter
    {
        $this->_selectedValues = $selectedValues;
        return $this;
    }

    /**
     * A list of the currently selected values. This list should only contain more than one value if multiselect is activated.
     * @return array the selectedValues
     */
    public function getSelectedValues(): array
    {
        return $this->_selectedValues;
    }

    /**
     * The HTML fragment in which the UI is written.
     * @param string $targetFragment
     * @return $this
     */
    public function setTargetFragment(string $targetFragment): GridField_ButtonFilter
    {
        $this->_targetFragment = $targetFragment;
        return $this;
    }

    /**
     * The HTML fragment in which the UI is written.
     * @return string the targetFragment
     */
    public function getTargetFragment(): string
    {
        return $this->_targetFragment;
    }

    /**
     * A list of values from which the user can choose. An associative array must be specified here. The key contains the
     * value to be filtered by and the value corresponds to the title shown to the user.
     * @param array $values
     * @return $this
     */
    public function setValues(array $values): GridField_ButtonFilter
    {
        $this->_values = $values;
        return $this;
    }

    /**
     * A list of values from which the user can choose. An associative array must be specified here. The key contains the
     * value to be filtered by and the value corresponds to the title shown to the user.
     * @return array the values
     */
    public function getValues(): array
    {
        return $this->_values;
    }

    /**
     * @inheritDoc
     */
    public function getHTMLFragments($gridField)
    {
        $selectedValues = $this->getSelectedValues();
        $fields = new FieldList();
        foreach ($this->getValues() as $filterValue => $filterTitle) {
            $selected = in_array($filterValue, $selectedValues);
            $typeField = new GridField_FormAction(
                $gridField,
                'gridfield_buttonfilter-' . md5($filterValue),
                $filterTitle,
                static::ACTION_NAME,
                ['selected_classes' => $selected ? '' : $filterTitle]
            );
            $typeField->addExtraClass('action_gridfield_buttonfilter');
            if ($selected) {
                $typeField->addExtraClass('active');
            }
            $fields->push($typeField);
        }
        $forTemplate = new ArrayData([
            'Fields' => $fields
        ]);
        $template = SSViewer::get_templates_by_class($this, '', __CLASS__);
        return [
            $this->_targetFragment => $forTemplate->renderWith($template)
        ];
    }

    /**
     * @param GridField $gridField
     * @return string[]
     */
    public function getActions($gridField)
    {
        return [static::ACTION_NAME];
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
        if ($actionName === static::ACTION_NAME) {
            if (array_key_exists('selected_classes', $arguments)) {
                $this->setSelectedValues($arguments['selected_classes']);
            }
        }
    }

    /**
     * @param GridField $gridField
     * @param SS_List $dataList
     * @return SS_List
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        if ($selectedValues = $this->getSelectedValues()) {
            //echo 'type = "' . (string)$this->_selectedType.'"<br>';
            return $dataList->filter([$this->getProperty() => $selectedValues]);
        }
        return $dataList;
    }
}
