<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridFieldMenuItem;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuItemProvider;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;

/**
 * Exercises the permission contract of an application-owned menu action.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class MenuAction extends AbstractGridFieldComponent implements GridFieldMenuItemProvider, GridField_ActionProvider, TestOnly
{
    public bool $allowed = true;
    public int $calls = 0;

    /** Offers a write action and a read-only navigation entry. */
    public function getMenuItems(GridField $grid): array
    {
        if (!$this->allowed) {
            return [];
        }
        return [
            GridFieldMenuItem::action('refresh-report', '<Report>', 'pro-report', confirmation: 'Run report?'),
            GridFieldMenuItem::link('help', 'Help', '/help'),
        ];
    }

    /** Registers the action separately from its menu presentation. */
    public function getActions($gridField): array
    {
        return ['pro-report'];
    }

    /** Rejects forged requests before invoking application work. */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data): void
    {
        if (!$this->allowed || $gridField->isReadonly() || $gridField->isDisabled()) {
            throw new HTTPResponse_Exception('Access denied.', 403);
        }
        $request = $gridField->getForm()->getController()->getRequest();
        if (!$request->isPOST() || !$gridField->getForm()->getSecurityToken()->checkRequest($request)
            || $actionName !== 'pro-report' || $arguments !== []) {
            throw new HTTPResponse_Exception('Invalid action request.', 400);
        }
        $this->calls++;
    }
}
