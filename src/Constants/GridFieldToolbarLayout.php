<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Constants;

/**
 * Supported toolbar alignment and sizing values.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Constants
 */
final class GridFieldToolbarLayout
{
    /** Aligns a group to the start of its row. */
    public const LEFT = 'left';
    /** Aligns a group to the end of its row. */
    public const RIGHT = 'right';
    /** Keeps an item's intrinsic width. */
    public const CONTENT = 'content';
    /** Allows an item to consume available space. */
    public const GROW = 'grow';
    /** Reserves an entire row for an item. */
    public const FULL = 'full';

    /** Provides translated layout descriptions. */
    public static function options(): array
    {
        return [
            self::LEFT => _t(__CLASS__ . '.LEFT', 'Left'),
            self::RIGHT => _t(__CLASS__ . '.RIGHT', 'Right'),
            self::CONTENT => _t(__CLASS__ . '.CONTENT', 'Content width'),
            self::GROW => _t(__CLASS__ . '.GROW', 'Flexible width'),
            self::FULL => _t(__CLASS__ . '.FULL', 'Full width'),
        ];
    }

    /** Resolves a supported layout description. */
    public static function label(mixed $value): string
    {
        return self::options()[$value] ?? (string)$value;
    }
}
