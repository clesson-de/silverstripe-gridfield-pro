# Silverstripe GridField Pro Module

A collection of useful GridField components for Silverstripe CMS.

## Components

| Component | Description |
|---|---|
| `GridFieldToggleAction` | Toggle the value of a boolean column directly in the grid |
| `GridFieldTiles` | Display records as tiles/boxes instead of rows |
| `GridFieldCalendar` | Display records in an interactive calendar view (powered by FullCalendar) |
| `GridField_CharFilter` | Filter records by the first character of a property |
| `GridField_ButtonFilter` | Filter records using a set of clickable buttons |

## Requirements

- PHP ^8.1
- Silverstripe Framework ^6

## Installation

```bash
composer require clesson-de/silverstripe-gridfield-pro:^1
```

Then run:

```bash
composer vendor-expose
```

## Usage

### GridFieldToggleAction

Toggle the value of a boolean column right inside the grid, without having to open the detail view.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldToggleAction;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

$component = new GridFieldToggleAction(
    'Active',                                         // property name
    _t(__CLASS__.'.Active', 'Deactivate'),            // label when value is true
    _t(__CLASS__.'.Inactive', 'Activate')             // label when value is false
);

// Optionally enforce that only one record may be true at a time
$component->setUnique(true);

$gridFieldConfig->addComponent($component);
```

---

### GridFieldTiles

Display records as tiles. Full control over each tile via a callback or a template file.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldTiles;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

$tileRenderer = function($Item, $Index, $Total) {
    return <<<EOD
<div>
    <div>{$Index}/{$Total}</div>
    <div style="font-weight:bold;font-size:x-large">{$Item->Title}</div>
    <div>{$Item->Value}</div>
    <div>{$Item->Date}</div>
</div>
EOD;
};

$component = new GridFieldTiles('before', $tileRenderer, 200, 300, 15);

// Disable edit links if not needed
$component->setEditable(false);

$gridFieldConfig->addComponent($component);
```

---

### GridFieldCalendar

Display records in an interactive calendar powered by [FullCalendar](https://fullcalendar.io).

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldCalendar;
use SilverStripe\ORM\FieldType\DBDatetime;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

$calendar = new GridFieldCalendar('before');

// Initial view (optional, default: GridFieldCalendar::VIEW_DAYGRIDMONTH)
$calendar->setInitialView(GridFieldCalendar::VIEW_DAYGRIDMONTH);

// Fixed content height in px (0 = auto)
$calendar->setContentHeight(400);

// Use the "Title" property as the event title
$calendar->setTitleFormat('Title');

// Use the "Date" property as the start date
$calendar->setStartDateFormat('Date');

// End date (optional) — property name or callback
$calendar->setEndDateFormat(function ($record) {
    $field = $record->dbObject('Date');
    $field->modify('+ 1 day');
    return $field->Format(DBDatetime::ISO_DATETIME_NORMALISED);
});

// Full-day flag (optional) — property name or callback
$calendar->setFullDayFormat(function ($record) {
    return (bool) $record->FullDay;
});

// Event colour (optional) — property name or callback
$calendar->setColorFormat(function ($record) {
    return '#3498db';
});

$gridFieldConfig->addComponent($calendar);
```

**Available view constants:**

| Constant | Description |
|---|---|
| `VIEW_DAYGRIDMONTH` | Monthly grid (default) |
| `VIEW_DAYGRIDWEEK` | Weekly grid |
| `VIEW_DAYGRIDDAY` | Daily grid |
| `VIEW_TIMEGRIDWEEK` | Weekly timeline |
| `VIEW_TIMEGRIDDAY` | Daily timeline |
| `VIEW_LISTYEAR` | Yearly list |
| `VIEW_LISTMONTH` | Monthly list |
| `VIEW_LISTWEEK` | Weekly list |
| `VIEW_LISTDAY` | Daily list |

---

### GridField_CharFilter

Show a row of character buttons above the grid. Clicking a button limits the list to records whose property starts with that character.

```php
use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

// Filter the "Name" property; display buttons for a–z, A–Z and 0–9
$component = new GridField_CharFilter('before', 'Name', 'a-z|A-Z|0-9');

$gridFieldConfig->addComponent($component);
```

---

### GridField_ButtonFilter

Show a customisable set of filter buttons. Supports single-select and multi-select mode.

```php
use Clesson\Silverstripe\Forms\GridField\GridField_ButtonFilter;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

$component = new GridField_ButtonFilter('before', 'Type', [
    'person'       => _t(__CLASS__.'.Person', 'Person'),
    'company'      => _t(__CLASS__.'.Company', 'Company'),
    'organisation' => _t(__CLASS__.'.Organisation', 'Organisation'),
    'club'         => _t(__CLASS__.'.Club', 'Club'),
]);

// Allow multiple buttons to be active at the same time (optional)
$component->setMultiselect(true);

$gridFieldConfig->addComponent($component);
```

---

## Documentation

See [docs/en/index.md](docs/en/index.md) for extended documentation and examples.

## License

BSD-3-Clause © [Kai Feldmaier / clesson.de](https://clesson.de)

### Third-party licenses

The `GridFieldCalendar` component loads [FullCalendar](https://fullcalendar.io) v6 via CDN at runtime.  
FullCalendar v6 (core and standard plugins) is released under the **[MIT License](https://github.com/fullcalendar/fullcalendar/blob/main/LICENSE.txt)**.  
Only the freely available standard plugins (`dayGrid`, `timeGrid`, `list`, `interaction`) are used — no premium/commercial plugins are required.

