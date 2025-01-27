<?php

namespace Clesson\Silverstripe\Forms\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * This class is a GridField component that displays the records of a gridfield inside tiles.
 *
 * ```php
 * $gridField = $fields->fieldByName('Items');
 * $gridFieldConfig = $gridField->getConfig();
 *
 * // use a callback function to generate the content of the tiles...
 * $tileRenderer = function($Item, $Index, $Total){
 *  return <<<EOD
 *      <div>
 *          <div>{$Index}/{$Total}</div>
 *          <div style="font-weight:bold;font-size:x-large">{$Item->Title}</div>
 *          <div>{$Item->Value}</div>
 *          <div>{$Item->Date}</div>
 *      </div>
 * EOD;
 *
 * // or use a custom template
 * $tileRenderer = 'App\MyModel_Tile';
 *
 * // add the component to the GridField config
 * $gridFieldConfig->addComponent(new \Clesson\Silverstripe\Forms\GridField\GridFieldTiles($tileRenderer));
 * ```
 */
class GridFieldTiles implements GridField_HTMLProvider
{

    /**
     * @var int Define the height of each tile
     */
    protected int $tileHeight = 200;

    /**
     * @var int Define the width of each tile
     */
    protected int $tileWidth = 200;

    /**
     * @var int Define the gap between the tiles (both horizontal and vertical)
     */
    protected int $tileGap = 15;

    /**
     * @var mixed|string Define a callback function or template path to render the tiles
     */
    protected mixed $tileRenderer = '';

    /**
     * @param mixed $tileRenderer a callback function or template path to render the tiles
     * @param int $tileWidth the width of each tile
     * @param int $tileHeight the height of each tile
     * @param int $tileGap the gap between the tiles (both horizontal and vertical)
     */
    public function __construct(mixed $tileRenderer, int $tileWidth=200, int $tileHeight = 200, int $tileGap = 15)
    {
        $this->tileRenderer = $tileRenderer;
        $this->tileWidth = $tileWidth;
        $this->tileHeight = $tileHeight;
        $this->tileGap = $tileGap;
    }

    /**
     * @param $gridField
     * @return array
     */
    public function getHTMLFragments($gridField): array
    {
        $tileRenderer = $this->tileRenderer;
        $items = $gridField->getList()->toArray();
        $template = SSViewer::create(__CLASS__);
        $total = count($items);
        $index = 0;
        $data = ArrayData::create([
            'TileHeight' => $this->getTileHeight(),
            'TileWidth' => $this->getTileWidth(),
            'TileGap' => $this->getTileGap(),
            'Items' => ArrayList::create(array_map(function ($index, $item) use ($gridField, $tileRenderer, $total) {
                // --- Link
                $Link = Controller::join_links(
                    $gridField->Link('item'),
                    $item->ID,
                    'edit'
                );
                $Link = Director::absoluteURL($Link);
                // --- Content
                $Content = '';
                if (is_string($tileRenderer)) {
                    $Content = $item->renderWith($tileRenderer);
                } elseif (is_callable($tileRenderer)) {
                    $Content = $tileRenderer($item, $index, $total);
                }
                return ArrayData::create([
                    'Link' => $Link,
                    'Content' => DBField::create_field('HTMLText', $Content)
                ]);
            }, array_keys($items), $items))
        ]);
        return [
            'before' => $template->process($data)
        ];
    }

    public function getTileHeight(): int
    {
        return $this->tileHeight;
    }

    public function setTileHeight(int $height): GridFieldTiles
    {
        $this->tileHeight = $height;
        return $this;
    }


    public function getTileWidth(): int
    {
        return $this->tileWidth;
    }

    public function setTileWidth(int $width): GridFieldTiles
    {
        $this->tileWidth = $width;
        return $this;
    }


    public function getTileGap(): int
    {
        return $this->tileGap;
    }

    public function setTileGap(int $gap): GridFieldTiles
    {
        $this->tileGap = $gap;
        return $this;
    }


}
