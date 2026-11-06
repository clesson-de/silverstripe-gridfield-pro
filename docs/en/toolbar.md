# Responsive GridField toolbar and action menus

## Installation and compatibility

Use the module's normal Composer installation, run `composer vendor-expose`,
and flush the Silverstripe manifest after upgrading. No new runtime package
or global GridField replacement is required.

`GridFieldToolbar` extends the core `GridFieldButtonRow`. Add it to the
GridField's own configuration, preferably using the idempotent installer:

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;

$toolbar = GridFieldToolbar::install($config); // Above the table.
$bottomToolbar = GridFieldToolbar::install($config, 'after');
```

Only the matching **core** button row is replaced. A custom subclass must be
removed explicitly, so its behaviour cannot be lost silently. Duplicate button
rows/toolbars at the same position produce a configuration error.

Legacy fragments remain available:

| Toolbar | Fragments |
| --- | --- |
| `before` | `buttons-before-left`, `buttons-before-right` |
| `after` | `buttons-after-left`, `buttons-after-right` |

Components remain registered directly in `$config`, preserving action/URL
handlers, data manipulation and `getComponentByType()`. Items in legacy groups
keep their provider registration order. A component's existing HTML wrapper
remains one block, rather than being parsed into separate buttons.

Components targeting `before` or `after` directly remain **outside** the toolbar.
Pass a toolbar fragment through their existing constructor or placement setter
to move them inside. The root fragment cannot also be consumed inside itself.
There is no generic DOM-move or all-interface proxy adapter for arbitrary
third-party components.

## Named slots and full-width rows

```php
use Clesson\Silverstripe\Constants\GridFieldToolbarLayout as Layout;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddExistingSelect;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

$toolbar = GridFieldToolbar::install($config);
$create = $toolbar->addItem('create');
$search = $toolbar->addItem('search', alignment: Layout::RIGHT, width: Layout::GROW);
$alphabet = $toolbar->addFullWidthItem('alphabet');

$config->removeComponentsByType([
    GridFieldAddNewButton::class,
    GridFieldAddExistingAutocompleter::class,
]);
$config->addComponent(GridFieldAddNewDropdown::create($create->getFragment()));
$config->addComponent(GridFieldAddExistingSelect::create($search->getFragment(), ['Title:PartialMatch']));
$config->addComponent(GridField_CharFilter::create($alphabet->getFragment(), 'Title', 'A-Z'));
```

Use the relation picker only with a saved, editable relation owner and keep a
`GridFieldDetailForm` for creation. Removing components by type is explicit;
installing the toolbar alone does not remove existing create/search controls.

`addItem($id, $alignment, $width, $row)` returns an immutable slot descriptor.
IDs are case-normalized, unique per toolbar and restricted to letters, digits,
hyphens and underscores; the first character must be alphanumeric.
`getFragment()` returns the stable native fragment for a provider.

Alignment is `Layout::LEFT` or `Layout::RIGHT`; width is `Layout::CONTENT` or
`Layout::GROW`. Rows are non-negative integers rendered in numerical order.
The default row zero also contains legacy left/right groups. Named slots follow
legacy contents within each group. Registration order determines slot order
within a group; right alignment does not reverse reading order.

`addFullWidthItem($id)` appends its own row after the current rows. Nothing else
may share that row. Additional rows can be assigned explicitly using `row:`.
Before and after toolbars have separate fragment namespaces.

For a filter/action layout, use `setGroupLabels($filterLabel, $actionLabel)`
with translated labels. These headings are optional: generic toolbars do not
assume that every left-hand component is a filter.

Component field labels inside the toolbar are visually hidden by default while
remaining available to screen readers. This does not depend on the CMS theme's
screen-reader utility classes. Labels outside the toolbar and labels wrapping
interactive controls are not affected; button captions and explicitly configured
group headings remain visible.

## Free-text search in a toolbar slot

Use `GridFieldSearch`, not the relation picker, to filter the rows already in a
GridField. It requires explicitly configured ORM field paths:

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;

$searchSlot = $toolbar->addItem('free-text', alignment: Layout::RIGHT, width: Layout::GROW);
$config->addComponent(GridFieldSearch::create(
    ['Title', 'Description'],
    $searchSlot->getFragment()
));
```

Remove `GridFieldFilterHeader` explicitly if this search should replace a core
search on that particular GridField; installing the toolbar does not remove it.
Do not install multiple `GridFieldSearch` instances on the same GridField.
Separate GridFields maintain independent search state.

The field has no submit button or automatic submission. Enter performs a full-page
navigation with the search in native URL GridState, including for short terms.
The X clears the input and immediately submits the empty search to remove the applied filter.

Configured fields are ORed, while existing GridField filters continue to apply.
Related fields, such as `Account.Email` or `Address.Summary`, work through ORM
filtering and obtain labels from the related model. An associative map can
provide explicit translated labels. The placeholder joins those labels using
localized commas and a final conjunction.

Only a search submission resets pagination. Unsubmitted edits do not change the
applied query in GridState. Search performs no AJAX requests or custom toolbar
replacement and does not submit/save the enclosing CMS edit form.

See [the README](../../README.md#toolbar-free-text-search) for the complete API,
configuration, input limits and failure behaviour.

## Explicit action menus

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldActionMenu;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuExportButton;

$config->addComponent(GridFieldMenuExportButton::create());
$config->addComponent(
    GridFieldActionMenu::create('buttons-before-right', 'tools')
        ->setTitle(_t('App.Grid.TOOLS', 'Tools'))
        ->setIcon('cog')
        ->addProvider(GridFieldMenuExportButton::class)
);
```

The constructor accepts a native fragment and a stable menu key. Menu keys must
be unique inside the GridField. The menu can also target a named slot or a normal
core button row; it does not depend on `GridFieldToolbar`.

Providers implement `GridFieldMenuItemProvider` and are selected **by class**.
Exactly one matching instance must already be registered in the same config.
This keeps references correct after configuration cloning. Two instances of
the same provider type need distinct application subclasses to be selected
unambiguously. Providers are never automatically discovered from every action
in the GridField.

`getMenuItems(GridField $grid): array` returns currently visible entries:

```php
GridFieldMenuItem::action(
    'recalculate',
    _t('App.Grid.RECALCULATE', 'Recalculate'),
    'app-recalculate',
    arguments: [],
    confirmation: _t('App.Grid.CONFIRM', 'Recalculate this list?'),
    icon: 'sync'
);

GridFieldMenuItem::link('help', _t('App.Grid.HELP', 'Help'), $helpURL);
GridFieldMenuItem::link('download', _t('App.Grid.DOWNLOAD', 'Download'), $fileURL, download: true);
```

Import `GridFieldMenuItem` from `Clesson\Silverstripe\Forms\GridField`.
Entries can be `disabled: true`. IDs must be unique lowercase fragment-safe
identifiers. Labels and attributes are escaped. Empty providers produce no
menu button; a single visible action still uses the menu.

Action entries render actual `GridField_FormAction` controls, including core
StateStore names and arguments. Their provider must also be the only registered
`GridField_ActionProvider` for that action name. Use unique, prefixed names.
Link entries use CMS navigation; download entries use ordinary browser requests
so file responses are not interpreted as refreshed HTML.

## Application actions and services

Keep application work in a service and expose it through a normal component.
The provider's visibility check is only presentation. Its handler must repeat
authorization independently. The following illustrates the handler contract;
`ReportService`, `canRunReport()` and the action constant belong to your app:

```php
public function handleAction(GridField $grid, $actionName, $arguments, $data): void
{
    $this->requireAllowedAction($grid, $actionName, $arguments);
    $request = $grid->getForm()->getController()->getRequest();
    if (!$request->isPOST() || !$grid->getForm()->getSecurityToken()->checkRequest($request)) {
        throw new HTTPResponse_Exception('Invalid action request.', 400);
    }
    Injector::inst()->get(ReportService::class)->recalculate($grid->getList());
}

private function requireAllowedAction(GridField $grid, string $action, array $arguments): void
{
    if ($action !== self::ACTION || $arguments !== []) {
        throw new HTTPResponse_Exception('Invalid action.', 400);
    }
    if ($grid->isReadonly() || $grid->isDisabled() || !$this->canRunReport($grid)) {
        throw new HTTPResponse_Exception('Access denied.', 403);
    }
}
```

Import `GridField`, `HTTPResponse_Exception` and `Injector` from their normal
Silverstripe namespaces. The component extends `AbstractGridFieldComponent`
and implements `GridFieldMenuItemProvider` and `GridField_ActionProvider`.
Its `getActions()` returns `[self::ACTION]`; `getMenuItems()` returns no entries
when `canRunReport()` denies access, otherwise the typed action above.

`canRunReport()` must verify the authenticated member's business permission,
the relevant list/owner and, for relations, the saved owner's edit permission.
Validate all submitted arguments and record IDs against the allowed scope.
Do not treat hidden controls, action names or CSRF tokens as authorization.
Never mutate data from link/download GET endpoints.

Mutating menu entries are hidden for readonly/disabled contexts. Set
`mutating: false` only for genuinely read-only POST actions. The menu does not
repair security gaps in a legacy handler.

While a menu POST is pending, repeated menu actions in the same grid are blocked
without disabling or renaming the native control. Failed requests restore the
existing rows and show a translated inline error alongside the core error
notification. The request lock is released on both success and failure.

## Core CSV export adapter

Replace the old export component rather than adding a second competing route:

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuExportButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Security\Permission;

$config->removeComponentsByType(GridFieldExportButton::class);
$export = GridFieldMenuExportButton::create('after', ['Title' => 'Title']);
$export->setPermissionChecker(
    static fn (GridField $grid): bool => Permission::check('APP_EXPORT_RECORDS')
);
$config->addComponent($export);
```

Import `GridField` as in the previous example. Copy all intentional old export
settings (columns, CSV separator/enclosure, header option) to the replacement
using the inherited core API. The constructor's fragment is retained for core
compatibility but the adapter renders only through the menu.

Without a custom checker, export is **ADMIN-only**. The checker runs during
rendering and again at the route. Relation exports additionally require a saved,
viewable owner. Core per-record `canView()` filtering and spreadsheet sanitizing
remain in effect. The core file response is preserved. Export runs against a
cloned config so paginator removal does not mutate the visible grid.

## Readonly and disabled transformations

The core filters components by **exact class name** during readonly/disabled
transformation. Explicitly retain the toolbar, menu and suitable read-only
providers on the field:

```php
GridFieldToolbar::preserveReadonly($grid, [GridFieldMenuExportButton::class]);
```

The helper extends, rather than replaces, the existing whitelist. Do not retain
mutating-only providers unnecessarily. Missing providers removed by the core
are skipped in a readonly menu; missing providers in an editable menu are errors.

A small registered GridField extension preserves readonly intent for these
opt-in menus, because the SS6 `FieldHolder()` resets its own readonly flag during
rendering. It does not install toolbars, replace GridFields or change controls
on fields without a menu. Rendered readonly menus remain non-mutating across
repeated renders. Business authorization is still required on every handler.

## Mobile, keyboard and styling

Toolbar groups wrap based on their **container width**, including when CMS
sidebars reduce a tablet's available space. At 36rem and below, groups occupy
separate rows and the relation picker uses the available width. Full-width slots
always occupy their own row. Adjust spacing using `--gridfield-toolbar-gap`.

The toolbar has a subtle background, border and compact content-sized controls.
Adjust its background using `--gridfield-toolbar-background`. Buttons keep visible
borders; pressed filters expose `aria-pressed` as well as a selected appearance.
Creation controls use a plus icon, and action menus support an optional CMS font
icon via `setIcon('down-circled')`, always retaining their text label.
Filter and character buttons have no internal gaps and only the outer corners
are rounded. Adjacent creation/action menus in the right group share this joined
appearance. Inside the toolbar, creation buttons use the same neutral background
as secondary actions. The character filter has no visible heading; its mobile
selector retains an accessible label.

Controls are compact for mouse input; coarse-pointer devices get at least 44px
touch targets. The CharFilter remains visible below its legacy 992px breakpoint.
Its buttons never wrap: desktop/tablet use a single horizontally scrollable strip
when necessary. At container widths of 36rem or less, JavaScript replaces the
strip with an initial-character selector, including **All** to clear the filter.
It dispatches the same native GridField actions, retaining click-to-clear on
the desktop strip. Without enhancement the strip remains available.
Selecting, changing or clearing the character filter resets pagination to page 1,
both for the button strip and the mobile selector.
Outside a toolbar the legacy layout is retained; dropdowns share the improved
menu controller. Unrelated third-party controls are not globally restyled.

Enter/Space opens the menu. Arrow keys, Home and End navigate enabled entries;
Escape restores focus to the toggle; Tab leaves normally and closes on focus
exit. Outside clicks/outer scrolling close the menu. Internal touch scrolling
does not. Only one menu opens at a time.

Menus remain in the GridField DOM for CMS navigation and form ancestry.
Viewport positioning uses `visualViewport` when available, responding to
keyboard/viewport changes and closing if the anchor is no longer visible.
Entwine removes listeners when the field is refreshed.

Without JavaScript, the list of links/actions remains visible instead of being
hidden behind an inert toggle. Core GridField POST buttons still require the
CMS JavaScript; this is not a replacement non-JavaScript form submission system.

This feature does not reflow the actual data table. Tables may still need
horizontal scrolling independently of the toolbar. Bulk selection, automatic
overflow menus, nested menus and background jobs are outside this API.

## Development and acceptance

Use the module's existing Vite scripts (`npm test`, `npm run build`); generated
assets retain their published paths. DOM lifecycle tests use jsdom as a
development dependency only. Run module PHP tests from the application root:

```sh
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit silverstripe-gridfield-pro/tests
```

The flush includes newly introduced test fixtures in the test-specific manifest.
In DDEV, use `ddev exec env XDEBUG_MODE=off SS_PHPUNIT_FLUSH=1 ...` when a CLI
debugger would otherwise pause execution.

Browser acceptance must cover 320/375/390/768/1280px containers, tablet
orientations/CMS sidebars, 200% zoom, actual navigation/CSV downloads and failed
POST recovery. Check iOS/iPadOS Safari and Android Chrome on real devices where
available: jsdom and desktop emulation do not prove touch-keyboard behaviour.
