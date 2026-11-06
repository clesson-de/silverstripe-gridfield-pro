<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use Closure;
use LogicException;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\Security\Permission;

/**
 * Core CSV export offered as an authorized read-only menu download.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldMenuExportButton extends GridFieldExportButton implements GridFieldMenuItemProvider
{
    /** Defaults to ADMIN rather than exposing every grid's export route. */
    private ?Closure $permissionChecker = null;

    /** Allows application-specific export permission, checked on every request. */
    public function setPermissionChecker(Closure $checker): static
    {
        $this->permissionChecker = $checker;
        return $this;
    }

    /** Suppresses the standalone button without removing the core URL handler. */
    public function getHTMLFragments($gridField): array
    {
        return [];
    }

    /** Generates a native download link rather than fetching a CSV as GridField HTML. */
    public function getMenuItems(GridField $grid): array
    {
        if (!$this->canExport($grid)) {
            return [];
        }
        $this->validateRoute($grid);
        return [GridFieldMenuItem::link(
            'csv-export', _t(__CLASS__ . '.EXPORT', 'Export to CSV'),
            $grid->addAllStateToUrl($grid->Link('export')), download: true, icon: 'down-circled'
        )];
    }

    /** Rechecks permission and preserves actual file responses from the core export. */
    public function handleExport($gridField, $request = null): HTTPResponse
    {
        if (!$this->canExport($gridField)) {
            throw new HTTPResponse_Exception('Export access denied.', 403);
        }
        $this->validateRoute($gridField);
        $response = parent::handleExport($gridField, $request);
        if (!$response instanceof HTTPResponse) {
            throw new HTTPResponse_Exception('The export produced no file.', 422);
        }
        return $response;
    }

    /** Keeps the core export's paginator removal local to its temporary config. */
    public function generateExportFileData($gridField): string
    {
        $copy = clone $gridField;
        return parent::generateExportFileData($copy);
    }

    /** Requires an authenticated export permission and a viewable saved relation owner. */
    private function canExport(GridField $grid): bool
    {
        $allowed = $this->permissionChecker
            ? (bool)($this->permissionChecker)($grid) : Permission::check('ADMIN');
        if (!$allowed || !$grid->getList() instanceof RelationList) {
            return (bool)$allowed;
        }
        $owner = $grid->getForm()?->getRecord();
        return $owner instanceof DataObject && $owner->isInDB() && $owner->canView();
    }

    /** Prevents a legacy exporter from shadowing the adapter's protected route. */
    private function validateRoute(GridField $grid): void
    {
        $handlers = [];
        foreach ($grid->getConfig()->getComponents() as $component) {
            if ($component instanceof GridField_URLHandler
                && array_key_exists('export', (array)$component->getURLHandlers($grid))) {
                $handlers[] = $component;
            }
        }
        if ($handlers !== [$this]) {
            throw new LogicException('Register the menu export adapter as the only export URL handler.');
        }
    }
}
