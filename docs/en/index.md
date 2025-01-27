Silverstripe GridField Pro
=====================

Toggle boolean values
-------------------

The `GridFieldToggleAction` component provides a more convinient solution to toggle the values of a boolean column right inside the grid, without having to open the detail view.

```php
$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();
$gridFieldConfig->addComponent(new \Clesson\Silverstripe\Forms\GridField\GridFieldToggleAction('Active', 'Deactivate', 'Activate'));
```

Display records in boxes instead of rows
-------------------

The `GridFieldTiles` component enables data to be displayed in tiles. You have full control over the appearance of each tile at all times.

This component is ideal for displaying an image gallery or listing contacts with avatar images, etc.

```php
$gridField = $fields->fieldByName('Items');
$gridFieldConfig = $gridField->getConfig();

// use a callback function 
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
$gridFieldConfig->addComponent(new \Clesson\Silverstripe\Forms\GridField\GridFieldTiles($callbackTileRenderer));
```
