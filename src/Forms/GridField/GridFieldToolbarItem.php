<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use Clesson\Silverstripe\Constants\GridFieldToolbarLayout as Layout;
use InvalidArgumentException;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Immutable fragment placement, independent of the component rendering it.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
final class GridFieldToolbarItem
{
    /** Validates placement before it can enter a toolbar. */
    public function __construct(
        private readonly string $fragment,
        public readonly int $row,
        public readonly string $alignment,
        public readonly string $width,
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $fragment) || $row < 0
            || !in_array($alignment, [Layout::LEFT, Layout::RIGHT], true)
            || !in_array($width, [Layout::CONTENT, Layout::GROW, Layout::FULL], true)) {
            throw new InvalidArgumentException('Invalid toolbar item placement.');
        }
    }

    /** Returns the native GridField fragment to pass to an HTML provider. */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /** Exposes only validated layout values and a trusted fragment definition. */
    public function forTemplate(): ArrayData
    {
        return ArrayData::create([
            'Width' => $this->width,
            'Fragment' => DBField::create_field('HTMLText', '$DefineFragment(' . $this->fragment . ')'),
        ]);
    }
}
