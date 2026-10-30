<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Forms\GridField;

use IntlDateFormatter;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Security;

/**
 * Combines creation and modification timestamps using the current member's formats.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldRecordDates extends AbstractGridFieldComponent implements GridField_ColumnProvider
{
    /** Combined presentation column; sortable headers can map it to LastEdited. */
    public const COLUMN = 'RecordDates';
    /** Standard creation timestamp. */
    private const CREATED = 'Created';
    /** Standard modification timestamp. */
    private const LAST_EDITED = 'LastEdited';

    /** Replaces standard timestamp columns at their first position, or appends the combined column. */
    public function augmentColumns($gridField, &$columns): void
    {
        $result = [];
        foreach ($columns as $column) {
            $column = in_array($column, [self::CREATED, self::LAST_EDITED], true) ? self::COLUMN : $column;
            if (!in_array($column, $result, true)) {
                $result[] = $column;
            }
        }
        if (!in_array(self::COLUMN, $result, true)) {
            $result[] = self::COLUMN;
        }
        $columns = $result;
    }

    /** Declares the single column owned by this component. */
    public function getColumnsHandled($gridField): array
    {
        return [self::COLUMN];
    }

    /** Uses a translated heading without exposing a synthetic sort key. */
    public function getColumnMetadata($gridField, $columnName): array
    {
        return ['title' => _t(__CLASS__ . '.CREATED', 'Created')];
    }

    /** Leaves sizing to the standard GridField layout. */
    public function getColumnAttributes($gridField, $record, $columnName): array
    {
        return [];
    }

    /** Compares timestamps to minute precision independently of their display format. */
    public function getColumnContent($gridField, $record, $columnName): string
    {
        $created = (string) $record->getField(self::CREATED);
        $edited = (string) $record->getField(self::LAST_EDITED);
        $html = $this->formattedDate($record, self::CREATED);
        if ($edited !== '' && substr($edited, 0, 16) !== substr($created, 0, 16)) {
            $sameDay = substr($created, 0, 10) === substr($edited, 0, 10);
            $modified = $sameDay
                ? _t(__CLASS__ . '.MODIFIED_AT_TIME', 'Modified at {time}', [
                    'time' => $this->formattedDate($record, self::LAST_EDITED, true),
                ])
                : Convert::raw2xml(_t(__CLASS__ . '.MODIFIED', 'Modified'))
                    . ' ' . $this->formattedDate($record, self::LAST_EDITED);
            $html .= '<br><small>' . $modified . '</small>';
        }
        return $html;
    }

    /** Preserves member formatting while suppressing seconds and fractional seconds. */
    private function formattedDate(DataObject $record, string $field, bool $timeOnly = false): string
    {
        /** @var DBDatetime $date */
        $date = $record->dbObject($field);
        if (!$date->exists()) {
            return '';
        }
        $member = Security::getCurrentUser();
        if (!$member) {
            $time = $date->getFormatter(IntlDateFormatter::NONE)->format($date->getTimestamp());
            $day = $date->getFormatter(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE)->format($date->getTimestamp());
            return Convert::raw2xml($timeOnly ? $time : $day . ', ' . $time);
        }
        $timeFormat = $this->withoutSeconds($member->getTimeFormat());
        $pattern = $timeOnly ? $timeFormat : $member->getDateFormat() . ', ' . $timeFormat;
        return Convert::raw2xml($date->Format($pattern, $member->getLocale()));
    }

    /** Quoted ICU literals must not be interpreted as second-pattern symbols. */
    private function withoutSeconds(string $pattern): string
    {
        return preg_replace("/'(?:[^']|'')*'(*SKIP)(*F)|[.:]?\\s*s+(?:[.,]S+)?/u", '', $pattern);
    }
}
