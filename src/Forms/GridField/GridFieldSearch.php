<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use InvalidArgumentException;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;

/**
 * Toolbar-compatible, stateful partial-match search over explicitly configured ORM fields.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldSearch extends AbstractGridFieldComponent implements GridField_HTMLProvider, GridField_ActionProvider, GridField_DataManipulator
{
    /** Native action and state namespace are independent of core filter headers. */
    public const ACTION = 'gridfield_search';
    /** Persist only the submitted term, not the current input draft. */
    public const STATE = 'GridFieldSearch';
    /** POST field relative to the GridField name. */
    public const QUERY = 'SearchQuery';
    /** Bounds user input without changing the configured field scope. */
    public const MAX_LENGTH = 200;

    private array $searchFields = [];
    private string $targetFragment;

    /** Accepts a list of field paths or a map of paths to translated display labels. */
    public function __construct(array $searchFields, string $targetFragment = 'buttons-before-left')
    {
        if ($searchFields === []) {
            throw new InvalidArgumentException('GridFieldSearch requires at least one search field.');
        }
        foreach ($searchFields as $key => $value) {
            $this->addSearchField($key, $value);
        }
        $this->targetFragment = $targetFragment;
    }

    /** Keeps field paths and optional translated labels strictly server-controlled. */
    private function addSearchField(int|string $key, mixed $value): void
    {
        $path = is_int($key) ? $value : $key;
        if (!is_string($path) || !preg_match('/^[A-Za-z_]\w*(?:\.[A-Za-z_]\w*)*$/D', $path)) {
            throw new InvalidArgumentException('Search fields must be database field paths without filter modifiers.');
        }
        if (!is_int($key) && (!is_string($value) || trim($value) === '')) {
            throw new InvalidArgumentException('Explicit search field labels must be non-empty strings.');
        }
        $this->searchFields[$path] = is_int($key) ? null : $value;
    }

    /** Returns the explicit, server-controlled database field paths. */
    public function getSearchFields(): array
    {
        return array_keys($this->searchFields);
    }

    /** Rejects unsupported lists and field paths rather than silently broadening the search. */
    private function validateFields(GridField $grid): void
    {
        $list = $grid->getList();
        if (!$list instanceof DataList) {
            throw new InvalidArgumentException('GridFieldSearch requires an ORM DataList or RelationList.');
        }
        foreach ($this->getSearchFields() as $path) {
            if (!$list->canFilterBy($path)) {
                throw new InvalidArgumentException('Invalid GridFieldSearch database field: ' . $path);
            }
        }
    }

    /** Resolves relation field labels using the model at the end of the configured path. */
    private function fieldLabel(GridField $grid, string $path): string
    {
        $model = DataObject::singleton($grid->getModelClass());
        $parts = explode('.', $path);
        $field = array_pop($parts);
        foreach ($parts as $relation) {
            $model = DataObject::singleton($model->getRelationClass($relation));
        }
        return $model->fieldLabel($field);
    }

    /** Produces localized list punctuation for one, two or more translated field names. */
    public function getPlaceholder(GridField $grid): string
    {
        $this->validateFields($grid);
        $labels = [];
        foreach ($this->searchFields as $path => $label) {
            $labels[] = $label ?? $this->fieldLabel($grid, $path);
        }
        $last = array_pop($labels);
        $fields = $labels === [] ? $last : _t(__CLASS__ . '.FIELD_LIST', '{fields} and {last}', [
            'fields' => implode(', ', $labels), 'last' => $last,
        ]);
        return _t(__CLASS__ . '.PLACEHOLDER', 'Search in {fields}', ['fields' => $fields]);
    }

    /** Restores the applied query independently of any unsubmitted input draft. */
    public function getQuery(GridField $grid): string
    {
        return $this->validateQuery($grid->State->{self::STATE}->query ?? '');
    }

    /** Invalid state and malformed requests are explicit client errors. */
    private function validateQuery(mixed $query): string
    {
        if (!is_string($query) || mb_strlen($query) > self::MAX_LENGTH) {
            throw new HTTPResponse_Exception('Invalid search query.', 400);
        }
        return trim($query);
    }

    /** Renders one input and a clear control; Enter navigates using native URL state. */
    public function getHTMLFragments($gridField): array
    {
        $placeholder = $this->getPlaceholder($gridField);
        Requirements::javascript('clesson-de/silverstripe-gridfield-pro:client/dist/js/silverstripe-gridfield-pro.js');
        Requirements::css('clesson-de/silverstripe-gridfield-pro:client/dist/css/silverstripe-gridfield-pro.css');
        return [$this->targetFragment => ArrayData::create([
            'InputName' => $gridField->getName() . '[' . self::QUERY . ']',
            'InputID' => $gridField->ID() . '_Search', 'Query' => $this->getQuery($gridField),
            'Placeholder' => $placeholder,
            'StateKey' => $gridField->getStateManager()->getStateKey($gridField),
            'HasPaginator' => $gridField->getConfig()->getComponentByType(GridFieldPaginator::class) ? 'true' : 'false',
            'MaxLength' => self::MAX_LENGTH,
            'Clear' => _t(__CLASS__ . '.CLEAR', 'Clear search'),
        ])->renderWith(self::class)];
    }

    /** Declares the component's single native action. */
    public function getActions($gridField): array
    {
        return [self::ACTION];
    }

    /** Only explicit search submissions change the applied query and reset paging. */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data): void
    {
        if ($actionName !== self::ACTION) {
            return;
        }
        $query = $this->validateQuery($data[$gridField->getName()][self::QUERY] ?? null);
        $gridField->State->{self::STATE}->query = $query;
        $paginator = $gridField->getConfig()->getComponentByType(GridFieldPaginator::class);
        $paginator?->handleAction($gridField, 'paginate', 1, $data);
    }

    /** Adds an OR group to the existing restricted list; related missing records remain eligible. */
    public function getManipulatedData(GridField $gridField, SS_List $dataList): SS_List
    {
        $this->validateFields($gridField);
        $query = $this->getQuery($gridField);
        if ($query === '') {
            return $dataList;
        }
        $filters = [];
        foreach ($this->getSearchFields() as $field) {
            $filters[$field . ':PartialMatch:nocase'] = $query;
        }
        return $dataList->filterAny($filters);
    }
}
