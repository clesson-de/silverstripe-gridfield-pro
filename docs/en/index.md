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
