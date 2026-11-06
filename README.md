# Silverstripe GridField Pro Module

This module provides a number of useful grid field components:

* `GridFieldToggleAction` - toggle the value of a boolean column
* `GridFieldAddExistingSelect` - compact, searchable AJAX select that links a record immediately on selection
* `GridFieldAddNewDropdown` - compact "New" action with a menu of record types
* `GridFieldRecordDates` - creation and modification dates in one member-formatted column
* `GridFieldToolbar` - responsive left/right groups, named slots and full-width rows
* `GridFieldActionMenu` - native GridField actions, navigation and downloads in one menu
* `GridFieldSearch` - buttonless free-text filtering submitted explicitly with Enter

## Installation
```bash
composer require clesson-de/silverstripe-gridfield-pro:^1
```

Then run:

```bash
composer vendor-expose
```

Rebuild the Silverstripe manifest and flush caches:

```text
/dev/build?flush=all
```

See [docs/en/index.md](docs/en/index.md) for documentation and examples.

## Responsive toolbar and action menus

These components are opt-in. Existing GridFields do not change automatically.
See the [toolbar developer guide](docs/en/toolbar.md) for named slots, migration,
secure action providers, downloads, readonly support and mobile behaviour.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldActionMenu;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuExportButton;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use SilverStripe\Forms\GridField\GridFieldExportButton;

$toolbar = GridFieldToolbar::install($config);
$config->removeComponentsByType(GridFieldExportButton::class);
$config->addComponent(GridFieldMenuExportButton::create());
$config->addComponent(
    GridFieldActionMenu::create()->addProvider(GridFieldMenuExportButton::class)
);
```

`install()` replaces only the core button row at the selected position, leaving
other components and the other position unchanged. Existing components targeting
`buttons-before-left` / `buttons-before-right` flow in their corresponding groups.
The CSV adapter defaults to **ADMIN-only** permission; configure a checker for
other users. When replacing an existing export, retain its configured columns,
separator and enclosure settings as described in the guide.

## Toolbar free-text search

CMS GridField selects can opt into an always-searchable Chosen dropdown using
`no-chosen gridfield-searchable-dropdown` classes and an `aria-label`. The former
disables the CMS default initializer; the shared assets initialize only the
opt-in select, with substring matching and no minimum option count. Give its
wrapper an explicit responsive width. Typing filters options locally; the
existing select change handler still controls submission.

`GridFieldSearch` filters the current ORM-backed list using case-insensitive
partial matches in any of the explicitly configured fields. Related database
fields are supported. Existing list restrictions, relations and other filters
remain in effect. The component is opt-in and does not replace core search globally.

```php
use Clesson\Silverstripe\Constants\GridFieldToolbarLayout as Layout;
use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;

$toolbar = GridFieldToolbar::install($config);
$slot = $toolbar->addItem('search', Layout::RIGHT, Layout::GROW);
$search = GridFieldSearch::create(['Title', 'Description'], $slot->getFragment());
$config->addComponent($search);
```

The constructor requires a non-empty array of database field paths, followed by
an optional native fragment (default: `buttons-before-left`). No toolbar is
required when using a normal fragment. `DataList` and ORM relation lists are
supported; in-memory lists and unknown fields produce configuration errors.
Field paths cannot contain search-filter modifiers.

The placeholder uses translated model field labels and localized punctuation,
for example **Suche in Name**, **Suche in Name und Beschreibung**, or
**Suche in Name, Titel und Beschreibung**. Relation labels are resolved on the
related model. Supply a field-to-translated-label map for custom wording:

```php
$search = GridFieldSearch::create([
    'Name' => _t('App.ContactSearch.NAME', 'Name'),
    'Account.Email' => _t('App.ContactSearch.EMAIL', 'Email'),
    'Address.Summary' => _t('App.ContactSearch.ADDRESS', 'Address'),
], $slot->getFragment());
```

This is also the field scope used in the contact overview. In that application,
the linked member's email is its login identifier; it is not searched twice.

There is no visible search/submit button and no automatic submission.
Press **Enter** to apply any term, including short terms. The X control
clears and focuses the input and immediately submits the empty search.
Enter during IME composition does not submit.

Enter navigates to the current page with Silverstripe's native URL GridState.
There is no AJAX search request or custom DOM replacement. The complete page
reloads, so all toolbar components initialize normally. Searching does not submit
or save the enclosing CMS edit form. Search terms are visible in the URL.

Terms are limited to 200 characters. Each submitted search resets a present
`GridFieldPaginator` to page one. Other filter/sort state is preserved; unrelated
GridFields and URL parameters are not changed. The previous automatic-search
configuration and `setAutomaticSearch()` API have been removed.

## Combined record dates

Add the component to an existing GridField configuration without further setup:

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;

$config->addComponent(GridFieldRecordDates::create());
```

The component replaces visible `Created` and `LastEdited` columns with one
**Created** column at their first position. If neither is present, it appends the
column. Creation appears on the first line in normal text. Only when the raw
`LastEdited` timestamp differs at minute precision does a second, smaller line appear:
**Modified** followed by its date and time (**Geändert** in German).
For a change on the creation day, it instead shows **Modified at** followed
only by the time (**Geändert um** in German).
Date and time are separated by a comma. The German same-day line includes
the suffix **Uhr**, for example **Geändert um 15:06 Uhr**.

Both timestamps respect the signed-in member's date format, time format and
locale, but omit seconds and fractional seconds. Differences within the same
minute do not produce a modification line. Without a signed-in member, the core
locale formatter with short time applies. Missing timestamps remain empty.
The combined presentation column requires no frontend assets. To sort it by
the last modification timestamp, map it through the standard sortable header:

```php
use SilverStripe\Forms\GridField\GridFieldSortableHeader;

$header = $config->getComponentByType(GridFieldSortableHeader::class);
$header->setFieldSorting(array_merge(
    $header->getFieldSorting(),
    [GridFieldRecordDates::COLUMN => 'LastEdited']
));
```

This enables ascending/descending sorting without changing the displayed dates.
The GridField configuration must include `GridFieldSortableHeader`, as standard
record-editor configurations do.

Record values are HTML-escaped. The component does not change timestamps or
permissions and is intended for DataObject-backed GridFields.

## Compact relation picker

Replace the standard autocomplete in a relation editor configuration:

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldAddExistingSelect;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

$config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
$config->addComponent(
    GridFieldAddExistingSelect::create('buttons-before-right', ['Title:PartialMatch'])
);
```

The select loads up to 20 candidates after at least two non-padding characters
have been entered. Focusing it alone does not send a request. Selecting a record
immediately links it and refreshes the GridField using its normal POST/CSRF flow.
Already linked and non-viewable records are excluded. The owning record must
be saved and editable; readonly/disabled GridFields do not offer the control.
`setSearchFields()`, `setSearchList()`, `setResultsFormat()`,
`setResultsLimit()` and `setPlaceholderText()` retain the core component's API.
Custom search lists also restrict which submitted IDs can be linked.

For example, customise the placeholder and limit the available candidates:

```php
$picker = GridFieldAddExistingSelect::create('buttons-before-right', ['Title:PartialMatch']);
$picker->setPlaceholderText(_t('App.SlidePicker.SEARCH', 'Search existing slides...'));
$picker->setResultsLimit(10);
$picker->setSearchList(Slide::get()->filter('Enabled', true));
$config->addComponent($picker);
```

In this example, `Slide` represents your own DataObject class with `Title` and
`Enabled` fields; import that class in your configuration. Remove the standard
autocompleter as shown above before adding the picker.

Without a custom placeholder, Silverstripe generates the text from the model's
translated plural name and the labels of the configured search fields. The
component does not hardcode a model name and can be reused for other relations.
Before two characters are entered, it displays a translated minimum-length hint.
Leading and trailing whitespace do not count towards the minimum.
Search requests are throttled by 300 ms, obsolete requests are cancelled, and
results are replaced when the query changes.

The control uses bundled Tom Select (no CDN or runtime npm dependencies). Errors
are shown beside the control; failed linking restores the previous rows.
Removing rows still uses the relation editor's normal unlink action.

## Compact multi-class creation button

`GridFieldAddNewDropdown` provides a short **New** button (**Neu** in German).
With multiple permitted record types, the button has a caret and opens a menu.
With exactly one permitted type, it opens that type's creation form directly.
No action is rendered if no type is permitted, the GridField is readonly or
disabled, or a relation owner is unsaved or not editable.

The component uses only the Silverstripe core, **not**
`symbiote/silverstripe-gridfieldextensions`. It does not extend or require
`GridFieldAddNewMultiClass`, its handler or its frontend assets.

Add it to a GridField configuration that contains `GridFieldDetailForm`:

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;

$config = GridFieldConfig_RelationEditor::create();
$config->removeComponentsByType(GridFieldAddNewButton::class);
$config->addComponent(GridFieldAddNewDropdown::create('buttons-before-left'));
```

By default, the grid's model and its concrete subclasses are discovered
automatically. Only compatible DataObject classes with `canCreate()` permission
are offered; relation owners are passed in the permission context as `Parent`.
The default menu labels are the models' translated singular names.

Menu entries optionally show the model's `class_icon` before their label:

```php
/** Optional CSS icon for class-aware creation menus. */
private static string $class_icon = 'font-icon-torso';
```

Set this property inside the DataObject class, or configure `class_icon` under
its fully qualified class name in YAML. Normal Silverstripe config inheritance
and overrides apply. Use a single CSS class whose stylesheet is loaded in the
CMS (for example a built-in `font-icon-*`). Missing, `null` or empty values show
only the text; an empty override also suppresses an inherited icon. Icons are
decorative, so the readable label remains unchanged. The single-type **New**
button retains its plus icon.

Supply a list of classes or a class-to-label map to restrict the choices:

```php
$button = GridFieldAddNewDropdown::create('before');
$button->setClasses([
    FirstRecordType::class => _t('App.RecordTypes.FIRST', 'First record type'),
    SecondRecordType::class => _t('App.RecordTypes.SECOND', 'Second record type'),
]);
$button->setTitle(_t('App.RecordTypes.NEW', 'New'));
$config->addComponent($button);
```

Replace `FirstRecordType` and `SecondRecordType` with your own model classes.
When replacing an existing `GridFieldAddNewMultiClass`, remove that component
from the configuration as well to avoid duplicate creation actions.

The constructor's fragment argument or `setFragment()` controls placement.
`before` is the default; `after`, `buttons-before-left`, `buttons-before-right`,
`buttons-after-left` and `buttons-after-right` can also be used when the GridField
provides those fragments. For toolbar fragments, include the appropriate core
`GridFieldButtonRow` (for example `GridFieldButtonRow::create('after')` for bottom
toolbar buttons). `getFragment()` and `getTitle()` expose the current settings.

Choosing a type opens its standard GridField detail form; it does not immediately
write a record. Core validation, security tokens, save actions and relation
assignment are retained. The selected type is preserved through validation errors
and cannot be replaced by a forged type in the creation URL or submitted data.
A missing `GridFieldDetailForm` raises a configuration error.

The menu supports Enter/Space activation, Up/Down, Home/End, Escape and
outside-click dismissal. It is positioned against the viewport to avoid
GridField overflow clipping, while retaining the DOM ancestry required by CMS
navigation. It closes on outer scrolling or resizing. Event handlers are removed
when the GridField refreshes.

## Frontend development (Vite)

All module CSS and JavaScript are maintained in `client/src/`, including the
existing tile, calendar and filter assets. Vite builds one CMS-compatible
classic script and one stylesheet; both include the new relation picker.
Do not edit generated files in `client/dist/` manually. There is no separate Vite
development server or HMR integration: the CMS always loads the compiled files.

### Source files

| File | Purpose |
| --- | --- |
| `client/src/index.js` | Vite entry point; imports module JavaScript and CSS, including Tom Select styles |
| `client/src/styles.css` | Module styles for existing GridField components and the relation picker |
| `client/src/legacy.js` | Existing GridField JavaScript retained for backwards compatibility |
| `client/src/add-existing-select.js` | Tom Select initialisation, search lifecycle and automatic linking |
| `client/src/add-new-dropdown.js` | Multi-class creation menu, keyboard interaction, positioning and refresh cleanup |
| `client/src/dropdown-menu.js` | Shared accessible dropdown behaviour and mobile viewport positioning |
| `client/src/action-menu.js`, `client/src/action-request.js` | Action-menu lifecycle, native POST request locking and error recovery |
| `client/src/remote-options.js` | AJAX transport and response validation; resolves URLs against the document base |
| `client/src/tests/` | Node test runner tests, with jsdom for menu lifecycle tests |
| `vite.config.js` | IIFE build configuration and stable output filenames |

Add new CSS or JavaScript modules under `client/src/` and import them from
`index.js` (or another imported source file) so Vite includes them in the bundle.
Keep CMS initialisation compatible with jQuery/Entwine and GridField refreshes.
jQuery and Entwine are supplied by the CMS, not bundled by this module.

### Setup and build

Node **26.12.1** is pinned in `.nvmrc`. With nvm installed, run from the module directory:

```bash
nvm install
nvm use
npm ci
npm run build
```

During development, rebuild automatically whenever source files change:

```bash
npm run watch
```

Keep the watch process running and reload the CMS after a rebuild. This command
writes directly to `client/dist/`; it does not start a web server.

Run the JavaScript tests separately:

```bash
npm test
```

For initial dependency setup or deliberate dependency updates, use `npm install`.
The lockfile is included for reproducible builds. The compiled assets are shipped
with the module, so deployments do not need Node unless rebuilding assets.

### Generated assets and CMS integration

Existing module asset paths remain unchanged:

* `client/dist/js/silverstripe-gridfield-pro.js`
* `client/dist/css/silverstripe-gridfield-pro.css`

Vite empties `client/dist/` before a production build, so do not store additional
hand-maintained files there. Keep the generated assets and `package-lock.json`
with your changes so installations can use the module without running npm.

Existing `Requirements::javascript()` / `Requirements::css()` references remain
valid. `GridFieldAddExistingSelect` registers both assets automatically. Other
integrations can use:

```php
use SilverStripe\View\Requirements;

Requirements::javascript('clesson-de/silverstripe-gridfield-pro:client/dist/js/silverstripe-gridfield-pro.js');
Requirements::css('clesson-de/silverstripe-gridfield-pro:client/dist/css/silverstripe-gridfield-pro.css');
```

After rebuilding, run from the application root:

```bash
composer vendor-expose
vendor/bin/sake flush
```

Then fully reload the CMS page. If the project exposes resources by copying
rather than symlinking, repeat `composer vendor-expose` after subsequent builds
as well. No changes to existing PHP asset references are required.
