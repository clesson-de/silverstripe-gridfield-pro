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
 * use Clesson\Silverstripe\Forms\GridField\GridFieldTiles;
 *
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
 * // Create an instance of the Tiles component
 * $component = new GridFieldTiles("before", $tileRenderer, 200, 300, 15);
 *
 * // if you do not want the user to be able to jump to the DetailForm, set the component to editable = false.
 * $component->setEditable(false);
 *
 * // add the component to the GridField config
 * $gridFieldConfig->addComponent($component);
 * ```
 */
class GridFieldTiles implements GridField_HTMLProvider
{

    /**
     * @var bool Either items can be clicked to view the detail form or not. Default is true.
     */
    protected bool $_editable = true;

    /**
     * @var string The targetFragment value. Default is "before".
     */
    protected string $_targetFragment = "before";

    /**
     * @var int The tileGap value defines the gap between the tiles (both horizontal and vertical). Default is 15.
     */
    protected int $_tileGap = 15;

    /**
     * @var int The tileWidth value defines the width of each tile. Default is 200.
     */
    protected int $_tileWidth = 200;

    /**
     * @var int The tileHeight value defines the height of each tile. Default is 200.
     */
    protected int $_tileHeight = 200;

    /**
     * @var mixed Define a callback function or template path to render the tiles
     */
    protected mixed $_tileRenderer = "";

    /**
     * @param string $targetFragment
     * @param mixed $tileRenderer a callback function or template path to render the tiles
     * @param int $tileWidth the width of each tile
     * @param int $tileHeight the height of each tile
     * @param int $tileGap the gap between the tiles (both horizontal and vertical)
     */
    public function __construct(string $targetFragment = 'before', mixed $tileRenderer = '', int $tileWidth = 200, int $tileHeight = 200, int $tileGap = 15)
    {
        $this->setTargetFragment($targetFragment);
        $this->setTileRenderer($tileRenderer);
        $this->setTileWidth($tileWidth);
        $this->setTileWidth($tileHeight);
        $this->setTileGap($tileGap);
    }

    /**
     * @param $gridField
     * @return array
     */
    public function getHTMLFragments($gridField): array
    {
        $tileRenderer = $this->getTileRenderer();
        $items = $gridField->getList()->toArray();
        $template = SSViewer::create(__CLASS__);
        $total = count($items);
        $editable = $this->getEditable();
        $index = 0;
        $data = ArrayData::create([
            'TileHeight' => $this->getTileHeight(),
            'TileWidth' => $this->getTileWidth(),
            'TileGap' => $this->getTileGap(),
            'Items' => ArrayList::create(array_map(function ($index, $item) use ($gridField, $tileRenderer, $total, $editable) {
                // --- Link
                if ($editable) {
                    $Link = Controller::join_links(
                        $gridField->Link('item'),
                        $item->ID,
                        'edit'
                    );
                    $Link = Director::absoluteURL($Link);
                } else {
                    $Link = '';
                }
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
            $this->_targetFragment => $template->process($data)
        ];
    }

    /**
     * Set the editable.
     * @param bool $editable
     * @return $this
     */
    public function setEditable(bool $editable): GridFieldTiles
    {
        $this->_editable = $editable;
        return $this;
    }

    /**
     * Set the editable. Default value is true.
     * @return bool the editable
     */
    public function getEditable(): bool
    {
        return $this->_editable;
    }

    /**
     * Set the targetFragment.
     * @param string $targetFragment
     * @return $this
     */
    public function setTargetFragment(string $targetFragment): GridFieldTiles
    {
        $this->_targetFragment = $targetFragment;
        return $this;
    }

    /**
     * Set the targetFragment. Default value is "before".
     * @return string the targetFragment
     */
    public function getTargetFragment(): string
    {
        return $this->_targetFragment;
    }

    /**
     * Set the tileGap.
     * @param int $tileGap
     * @return $this
     */
    public function setTileGap(int $tileGap): GridFieldTiles
    {
        $this->_tileGap = $tileGap;
        return $this;
    }

    /**
     * Set the tileGap. Default value is 15".
     * @return int the tileGap
     */
    public function getTileGap(): int
    {
        return $this->_tileGap;
    }

    /**
     * Set the tileWidth.
     * @param int $tileWidth
     * @return $this
     */
    public function setTileWidth(int $tileWidth): GridFieldTiles
    {
        $this->_tileWidth = $tileWidth;
        return $this;
    }

    /**
     * Set the tileWidth. Default value is 200".
     * @return int the tileWidth
     */
    public function getTileWidth(): int
    {
        return $this->_tileWidth;
    }

    /**
     * Set the tileHeight.
     * @param int $tileHeight
     * @return $this
     */
    public function setTileHeight(int $tileHeight): GridFieldTiles
    {
        $this->_tileHeight = $tileHeight;
        return $this;
    }

    /**
     * Set the tileHeight. Default value is 200".
     * @return int the tileHeight
     */
    public function getTileHeight(): int
    {
        return $this->_tileHeight;
    }

    /**
     * Set the tileRenderer.
     * @param mixed $tileRenderer
     * @return $this
     */
    public function setTileRenderer(mixed $tileRenderer): GridFieldTiles
    {
        $this->_tileRenderer = $tileRenderer;
        return $this;
    }

    /**
     * Set the tileRenderer. Default value is "".
     * @return mixed the tileRenderer
     */
    public function getTileRenderer(): mixed
    {
        return $this->_tileRenderer;
    }

}
