<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use LogicException;
use ReflectionClass;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\View\Requirements;

/**
 * Compact class-aware creation action using only the core GridField detail form.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldAddNewDropdown extends AbstractGridFieldComponent implements GridField_HTMLProvider, GridField_URLHandler
{
    /** Allows the component's class-specific creation route. */
    private static array $allowed_actions = ['handleAdd'];

    protected ?array $classes = null;
    protected ?string $title = null;

    /** Places the action in any fragment supplied by the GridField. */
    public function __construct(protected string $fragment = 'before')
    {
    }

    /** Changes placement without changing the configured class choices. */
    public function setFragment(string $fragment): static
    {
        $this->fragment = $fragment;
        return $this;
    }

    /** Returns the configured GridField fragment. */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /** Overrides the translated default action label. */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /** Uses a short translated label independent of the model name. */
    public function getTitle(): string
    {
        return $this->title ?? _t(__CLASS__ . '.NEW', 'New');
    }

    /** Accepts class names or a map of class names to display labels. */
    public function setClasses(array $classes): static
    {
        $this->classes = $classes;
        return $this;
    }

    /** Filters configured or discovered classes by model compatibility and creation permission. */
    public function getClasses(GridField $grid): array
    {
        if (!$this->canManage($grid)) {
            return [];
        }
        $classes = $this->classes ?? array_values(ClassInfo::subclassesFor($grid->getModelClass()));
        $allowed = [];
        foreach ($classes as $class => $label) {
            $class = is_int($class) ? $label : $class;
            if ($this->canCreateClass($grid, $class)) {
                $allowed[$class] = $class === $label ? singleton($class)->i18n_singular_name() : $label;
            }
        }
        return $allowed;
    }

    /** Requires an editable grid and, for relations, an editable persisted owner. */
    private function canManage(GridField $grid): bool
    {
        if ($grid->isReadonly() || $grid->isDisabled()) {
            return false;
        }
        if (!$grid->getList() instanceof RelationList) {
            return true;
        }
        $owner = $grid->getForm()?->getRecord();
        return $owner instanceof DataObject && $owner->isInDB() && $owner->canEdit();
    }

    /** Rejects abstract, unrelated and non-creatable types before constructing a new record. */
    private function canCreateClass(GridField $grid, mixed $class): bool
    {
        if (!is_string($class) || !is_a($class, DataObject::class, true)
            || !is_a($class, $grid->getModelClass(), true) || (new ReflectionClass($class))->isAbstract()) {
            return false;
        }
        $owner = $grid->getForm()?->getRecord();
        $context = $grid->getList() instanceof RelationList && $owner instanceof DataObject ? ['Parent' => $owner] : [];
        return (bool)singleton($class)->canCreate(null, $context);
    }

    /** Renders a dropdown for multiple choices, or a direct link for a single choice. */
    public function getHTMLFragments($grid): array
    {
        $classes = $this->getClasses($grid);
        if (!$classes) {
            return [];
        }
        $this->detailForm($grid);
        Requirements::javascript('clesson-de/silverstripe-gridfield-pro:client/dist/js/silverstripe-gridfield-pro.js');
        Requirements::css('clesson-de/silverstripe-gridfield-pro:client/dist/css/silverstripe-gridfield-pro.css');
        return [$this->fragment => ArrayData::create([
            'Title' => $this->getTitle(),
            'MenuID' => $grid->ID() . '_NewDropdown',
            'Choices' => $this->choices($grid, $classes),
            'Multiple' => count($classes) > 1,
        ])->renderWith(self::class)];
    }

    /** Builds concrete links rather than relying on a separate select and submit button. */
    private function choices(GridField $grid, array $classes): ArrayList
    {
        $choices = ArrayList::create();
        foreach ($classes as $class => $label) {
            $choices->push(ArrayData::create([
                'Label' => $label,
                'Link' => Controller::join_links($grid->Link('new-class'), str_replace('\\', '-', $class)),
            ]));
        }
        return $choices;
    }

    /** Keeps unsaved forms on their class-specific route. */
    public function getURLHandlers($grid): array
    {
        return ['new-class/$ClassName!' => 'handleAdd'];
    }

    /** Resolves only allowlisted class tokens, rechecking permissions on every request. */
    public function handleAdd($grid, $request): GridFieldAddNewDropdownItemRequest
    {
        foreach ($this->getClasses($grid) as $class => $label) {
            if (str_replace('\\', '-', $class) === $request->param('ClassName')) {
                return $this->itemRequest($grid, $class);
            }
        }
        throw new HTTPResponse_Exception('Invalid or forbidden record type.', 403);
    }

    /** Retains core form validation and template configuration for the selected type. */
    private function itemRequest(GridField $grid, string $class): GridFieldAddNewDropdownItemRequest
    {
        $detail = clone $this->detailForm($grid);
        $record = $class::create();
        if (!$detail->getValidator()) {
            $detail->setValidator($record->getCMSCompositeValidator());
        }
        $handler = GridFieldAddNewDropdownItemRequest::create(
            $grid, $detail, $record, $grid->getForm()->getController(), 'new-class'
        );
        if ($detail->getTemplate()) {
            $handler->setTemplate($detail->getTemplate());
        }
        return $handler;
    }

    /** Fails explicitly if creation cannot be backed by a detail form. */
    private function detailForm(GridField $grid): GridFieldDetailForm
    {
        $detail = $grid->getConfig()->getComponentByType(GridFieldDetailForm::class);
        if (!$detail) {
            throw new LogicException(self::class . ' requires GridFieldDetailForm.');
        }
        return $detail;
    }
}
