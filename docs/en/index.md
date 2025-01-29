Silverstripe GridField Pro
=====================

Toggle boolean values inside tables
-------------------

The `GridFieldToggleAction` component provides a more convinient solution to toggle the values of a boolean column right inside the grid, without having to open the detail view.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldToggleAction;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();
// Switch the boolean value of the "Active" property
$gridFieldConfig->addComponent(new GridFieldToggleAction('Active', _t(__CLASS__.'.Active', 'Deactivate'), _t(__CLASS__.'.Inactive', 'Activate')));
```

Display records in boxes instead of rows
-------------------

The `GridFieldTiles` component enables data to be displayed in tiles. You have full control over the appearance of each tile at all times.

This component is ideal for displaying an image gallery or listing contacts with avatar images, etc.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldTiles;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

// Use a callback function to render the content of each tile
$callbackTileRenderer = function($Item, $Index, $Total){
return <<<EOD
<div>
    <div>{$Index}/{$Total}</div>
    <div style="font-weight:bold;font-size:x-large">{$Item->Title}</div>
    <div>{$Item->Value}</div>
    <div>{$Item->Date}</div>
</div>
EOD;
};
$gridFieldConfig->addComponent(new GridFieldTiles($callbackTileRenderer));
```

Display records in calendar
-------------------
Would you like to organize your data records chronologically? Then use the `GridFieldCalendar` component. The calendar 
is rendered using the famous [FullCalendar](https://fullcalendar.io) library. You can customize the appearance of each record in the calendar.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldCalendar;
use SilverStripe\ORM\FieldType\DBDatetime;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

$calendar = new GridFieldCalendar('before');
// Define the initial view (optional). Default is GridFieldCalendar::VIEW_DAYGRIDMONTH.
$calendar->setInitialView(GridFieldCalendar::VIEW_DAYGRIDMONTH);
// Define a fix height for the content. This may result in scrollbars. 0 means auto.
$calendar->setContentHeight(400);
// Use the "Title" Property as title of the calendar event
$calendar->setTitleFormat('Title');
// Use the "Date" Property as the startdate of the event
$calendar->setStartDateFormat('Date');
// End date is optional. Here we use a callback function to mimic the end date
$calendar->setEndDateFormat(function ($record) {
    $field = $record->dbObject('Date');
    $field->modify('+ 1 day');
    $field->modify('- 5 hours');
    return $field->Format(DBDatetime::ISO_DATETIME_NORMALISED);
});
// Does the event last all day? 
$calendar->setFullDayFormat(function ($record) {
    return $record->FullDay ? true : false;
});
// The event color is also optional. We recommend using the CSS classes to make several events of the same type appear
// in the same style.
$calendar->setColorFormat(function ($record) {
    return '#eee';
});
$gridFieldConfig->addComponent($calendar);
```
