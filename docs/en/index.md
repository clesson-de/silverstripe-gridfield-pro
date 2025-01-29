Silverstripe GridField Pro
=====================

Toggle boolean values inside tables
-------------------

The `GridFieldToggleAction` component provides a more convinient solution to toggle the values of a boolean column right 
inside the grid, without having to open the detail view.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldToggleAction;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

// let's assume we want to switch the Boolean value of the “Active” property...
$component = new GridFieldToggleAction(
    'Active', // the name of the property in the model
    _t(__CLASS__.'.Active', 'Deactivate'), // The label that is displayed when the value of “Active” is true
    _t(__CLASS__.'.Inactive', 'Activate')   // The label that is displayed when the value of “Active” is false
);

// Assuming that only one of the models in the gridfield may be true and all others must be false, we can solve this
// with setUnique:
$component->setUnique(true);

// add the component    
$gridFieldConfig->addComponent($component);
```

Display records in boxes instead of rows
-------------------

The `GridFieldTiles` component enables data to be displayed in tiles. You have full control over the appearance of each 
tile at all times.

This component is ideal for displaying an image gallery or listing contacts with avatar images, etc.

```php
use Clesson\Silverstripe\Forms\GridField\GridFieldTiles;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

// Use a callback function to render the content of the individual tiles.
// Alternatively, you can also specify the path to a template file.
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

// Create an instance of the Tiles component
$component = new GridFieldTiles("before", $callbackTileRenderer, 200, 300, 15);

// if you do not want the user to be able to jump to the DetailForm, set the component to editable = false.
$component->setEditable(false);

// add the component to the GridField config
$gridFieldConfig->addComponent($component);
```

Display records in calendar
-------------------
Would you like to organize your data records chronologically? Then use the `GridFieldCalendar` component. The calendar 
is rendered using the famous [FullCalendar](https://fullcalendar.io) library. You can customize the appearance of each 
record in the calendar.

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

Filter data records by a leading character in the property
-------------------
The GridField_CharFilter consists of a series of buttons, each of which represents a single character. If a button is 
selected (e.g. “F”), only records whose name begins with an “F”, for example, are listed.

```php
use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;

$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

// Create an instance of the CharFilter component
// The “Name” property is filtered here. The list of characters that are displayed as buttons is specified as a string.
// An array of individual characters or no specification at all would also be possible.
$component = new GridField_CharFilter("before", "Name", "a-z|A-Z|0-9");

// add the component to the GridField config
$gridFieldConfig->addComponent($component);
```
