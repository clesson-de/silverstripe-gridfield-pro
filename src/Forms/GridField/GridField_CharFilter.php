<?php

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;


/**
 * This class is a filter for the GridField component.
 *
 * ```php
 * use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;
 *
 * $gridField = $fields->fieldByName('Items');
 * $gridFieldConfig = $gridField->getConfig();
 *
 * // Create an instance of the CharFilter component
 * $component = new GridField_CharFilter("before", "Name", "a-z|A-Z|0-9");
 *
 * // add the component to the GridField config
 * $gridFieldConfig->addComponent($component);
 * ```
 */
class GridField_CharFilter extends AbstractGridFieldComponent implements GridField_HTMLProvider, GridField_ActionProvider, GridField_DataManipulator
{

    /**
     * @var array The list of all characters that are to be displayed as buttons.
     */
    protected array $_chars = [];

    /**
     * @var string The currently selected character. Default is "".
     */
    protected string $_selectedChar = "";

    /**
     * @var string The targetFragment value. Default is "before".
     */
    protected string $_targetFragment = "before";

    /**
     * @var string The property to which the filter is to be applied.. Default is "Name".
     */
    protected string $_property = "Name";

    /**
     * @param string $targetFragment
     * @param string $property
     * @param mixed $chars
     */
    public function __construct(string $targetFragment = 'before', string $property = 'Name', mixed $chars = null)
    {
        $this->setTargetFragment($targetFragment);
        $this->setProperty($property);
        $this->setChars($chars);
    }

    /**
     * Set the list of chars to be displayed as buttons.
     * There are two ways to specify characters:
     * 1. as an array with individual characters
     * 2. as a character string consisting of sets, ranges and individual characters:
     *      - Sets are separated by pipes, e.g. “a-z|A-Z|0-9”
     *      - Ranges are separated by a minus sign, e.g. “a-z”
     *      - Individual characters are separated by commas, e.g. “a,b,c”
     *
     * If you pass an empty string or an empty array, the default characters are displayed. Empty characters and
     * character strings with more than one character are filtered out.
     * @param array $chars
     * @return $this
     */
    public function setChars(mixed $chars): GridField_CharFilter
    {
        if (is_string($chars)) {
            $chars = $this->enumerateChars($chars);
        }
        $chars = array_map('trim', $chars);
        $chars = array_filter($chars, function($char){
            return strlen($char) === 1;
        });
        $this->_chars = $chars ? $chars : $this->defaultChars();
        return $this;
    }

    /**
     * Returns the list of characters to be displayed as buttons.
     * @return array the chars
     */
    public function getChars(): array
    {
        return $this->_chars;
    }

    /**
     * Set the selectedChar.
     * @param string $selectedChar
     * @return $this
     */
    public function setSelectedChar(string $selectedChar): GridField_CharFilter
    {
        if (mb_strlen($selectedChar) > 1) {
            user_error('Attention! The current selection has more than one character!');
            $selectedChar = mb_substr($selectedChar, 0, 1);
        }
        $this->_selectedChar = $selectedChar;
        return $this;
    }

    /**
     * Set the selectedChar. Default value is "".
     * @return string the selectedChar
     */
    public function getSelectedChar(): string
    {
        return $this->_selectedChar;
    }

    /**
     * Set the targetFragment.
     * @param string $targetFragment
     * @return $this
     */
    public function setTargetFragment(string $targetFragment): GridField_CharFilter
    {
        $this->_targetFragment = $targetFragment;
        return $this;
    }

    /**
     * Set the targetFragment. Default value is "before".
     * @return string the targetFragment
     */
    public function getTargetFragment(): string
    {
        return $this->_targetFragment;
    }

    /**
     * Set the property.
     * @param string $property
     * @return $this
     */
    public function setProperty(string $property): GridField_CharFilter
    {
        $this->_property = $property;
        return $this;
    }

    /**
     * Set the property. Default value is "Title".
     * @return string the property
     */
    public function getProperty(): string
    {
        return $this->_property;
    }

    /**
     * Decodes character strings and examines them for sets, ranges and lists. It is also possible for the function to be
     * called several times by itself. The result contains an array of individual characters. This array can also contain
     * invalid characters! It is therefore always advisable to validate the resulting list before using it.
     *
     * <code>$pattern</code> is as a character string consisting of sets, ranges and individual characters:
     *      - Sets are separated by pipes, e.g. “a-z|A-Z|0-9”
     *      - Ranges are separated by a minus sign, e.g. “a-z”
     *      - Individual characters are separated by commas, e.g. “a,b,c”
     * @param string $pattern
     * @return array
     */
    protected function enumerateChars(string $pattern):array
    {
        $chars = [];
        if (str_contains($pattern, '|')) {
            foreach (explode('|', $pattern) as $subpattern)
            {
                $chars = array_merge($chars, $this->enumerateChars($subpattern));
            }
        } else if (str_contains($pattern, '-')) {
            list($from,$to) = array_pad(explode('-', $pattern), 2, '');
            if (strlen($from) === 1 && strlen($to) === 1) {
                for ($char = ord($from); $char <= ord($to); ++$char) {
                    $chars[] = chr($char);
                }
            }
        } else {
            $chars = explode(',', $pattern);
        }
        return $chars;
    }

    /**
     * Returns the default set of individual characters.
     * @return array
     */
    protected function defaultChars(): array
    {
        return $this->enumerateChars('a-z|0-9');;
    }

    /**
     * @param $gridField
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        $dataClass = $gridField->getModelClass();

        $forTemplate = new ArrayData([]);
        $forTemplate->Fields = new FieldList();

        foreach ($this->getChars() as $key => $char) {
            $selected = $char == $this->getSelectedChar();
            $charField = new GridField_FormAction(
                $gridField,
                'gridfield_charfilter-' . $key,
                $char,
                'charfilter',
                ['char' => $selected ? '' : $char]
            );
            $charField->addExtraClass('action_gridfield_charfilter');

            if ($selected) {
                $charField->addExtraClass('active');
            }

            $forTemplate->Fields->push($charField);
        }

        if ($form = $gridField->getForm()) {
            $forTemplate->Fields->setForm($form);
        }

        $template = SSViewer::get_templates_by_class($this, '', __CLASS__);
        return [
            $this->_targetFragment => $forTemplate->renderWith($template)
        ];
    }

    /**
     * @param $gridField
     * @return string[]
     */
    public function getActions($gridField)
    {
        return ['charfilter'];
    }

    /**
     * @inheritdoc
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'charfilter') {
            if (isset($arguments['char'])) {
                $this->setSelectedChar($arguments['char']);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList): SS_List
    {
        if ($this->getSelectedChar()) {
            return $dataList->filter([$this->getProperty() . ':StartsWith' => (string)$this->getSelectedChar()]);
        }
        return $dataList;
    }

}
