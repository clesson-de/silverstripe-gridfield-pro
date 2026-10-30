<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Tests\GridFieldPro;

use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
use IntlDateFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Covers member formatting, minute-precision comparison and automatic column replacement.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Tests
 */
class GridFieldRecordDatesTest extends SapphireTest
{
    /** Existing timestamp columns collapse in place, without duplicating the component column. */
    public function testColumns(): void
    {
        $component = GridFieldRecordDates::create();
        foreach ([
            [['Title', 'Created', 'LastEdited', 'Actions'], ['Title', 'RecordDates', 'Actions']],
            [['Title', 'LastEdited', 'Created'], ['Title', 'RecordDates']],
            [['Title'], ['Title', 'RecordDates']],
            [['RecordDates', 'Created'], ['RecordDates']],
        ] as [$columns, $expected]) {
            $component->augmentColumns(null, $columns);
            self::assertSame($expected, $columns);
            $component->augmentColumns(null, $columns);
            self::assertSame($expected, $columns);
        }
        self::assertSame(['RecordDates'], $component->getColumnsHandled(null));
    }

    /** Uses date and time preferences of the actual current user, not the global default. */
    #[DataProvider('formats')]
    public function testMemberFormats(string $dateFormat, string $timeFormat, string $expected): void
    {
        $member = $this->getMockBuilder(Member::class)->onlyMethods(['getDateFormat', 'getTimeFormat'])->getMock();
        $member->Locale = 'en_US';
        $member->method('getDateFormat')->willReturn($dateFormat);
        $member->method('getTimeFormat')->willReturn($timeFormat);
        $previous = Security::getCurrentUser();
        Security::setCurrentUser($member);
        try {
            $record = Member::create(['Created' => '2026-10-29 14:05:06', 'LastEdited' => '2026-10-29 14:05:07']);
            $html = GridFieldRecordDates::create()->getColumnContent(null, $record, 'RecordDates');
            self::assertSame($expected, $html);
            self::assertStringNotContainsString('<small>', $html);
            self::assertStringNotContainsString('<strong>', $html);
        } finally {
            Security::setCurrentUser($previous);
        }
    }

    /** ICU patterns represent independent member preferences. */
    public static function formats(): array
    {
        return [
            ['dd.MM.yyyy', 'HH:mm', '29.10.2026, 14:05'],
            ['yyyy/MM/dd', 'HH:mm:ss', '2026/10/29, 14:05'],
            ['dd.MM.yyyy', 'h:mm:ss a', '29.10.2026, 2:05 PM'],
            ['dd.MM.yyyy', 'HH.mm.ss', '29.10.2026, 14.05'],
            ['dd.MM.yyyy', 'HH:mm:ss.SSS', '29.10.2026, 14:05'],
            ['dd.MM.yyyy', "HH:mm:ss 'seconds'", '29.10.2026, 14:05 seconds'],
            ["'<b>'yyyy'</b>'", 'HH:mm', '&lt;b&gt;2026&lt;/b&gt;, 14:05'],
        ];
    }

    /** The member's real locale takes precedence over the application locale. */
    public function testMemberLocale(): void
    {
        $previous = Security::getCurrentUser();
        $locale = i18n::get_locale();
        i18n::set_locale('en_US');
        Security::setCurrentUser(Member::create(['Locale' => 'de_DE']));
        try {
            $record = Member::create(['Created' => '2026-10-29 14:05:06']);
            self::assertSame('29.10.2026, 14:05', GridFieldRecordDates::create()->getColumnContent(null, $record, 'RecordDates'));
        } finally {
            Security::setCurrentUser($previous);
            i18n::set_locale($locale);
        }
    }

    /** Changes in another minute produce the translated modification line. */
    public function testDifferentTimestampAndGermanLabel(): void
    {
        $previous = Security::getCurrentUser();
        $locale = i18n::get_locale();
        i18n::set_locale('de_DE');
        $member = $this->getMockBuilder(Member::class)->onlyMethods(['getDateFormat', 'getTimeFormat'])->getMock();
        $member->Locale = 'de_DE';
        $member->method('getDateFormat')->willReturn('dd.MM.yyyy');
        $member->method('getTimeFormat')->willReturn('HH:mm');
        Security::setCurrentUser($member);
        try {
            $record = Member::create(['Created' => '2026-10-29 14:05:59', 'LastEdited' => '2026-10-29 14:06:00']);
            $component = GridFieldRecordDates::create();
            self::assertSame('29.10.2026, 14:05<br><small>Geändert um 14:06 Uhr</small>',
                $component->getColumnContent(null, $record, 'RecordDates'));
            $record->LastEdited = '2026-10-30 00:01:00';
            self::assertSame('29.10.2026, 14:05<br><small>Geändert 30.10.2026, 00:01</small>',
                $component->getColumnContent(null, $record, 'RecordDates'));
            $record->Created = null;
            self::assertSame('<br><small>Geändert 30.10.2026, 00:01</small>',
                $component->getColumnContent(null, $record, 'RecordDates'));
            self::assertSame(['title' => 'Erstellt'], $component->getColumnMetadata(null, 'RecordDates'));
        } finally {
            Security::setCurrentUser($previous);
            i18n::set_locale($locale);
        }
    }

    /** Same-day changes retain the user's 12-hour clock without repeating the date. */
    public function testSameDayWithTwelveHourClock(): void
    {
        $previous = Security::getCurrentUser();
        $locale = i18n::get_locale();
        i18n::set_locale('en_US');
        $member = $this->getMockBuilder(Member::class)->onlyMethods(['getDateFormat', 'getTimeFormat'])->getMock();
        $member->Locale = 'en_US';
        $member->method('getDateFormat')->willReturn('yyyy/MM/dd');
        $member->method('getTimeFormat')->willReturn('h:mm:ss a');
        Security::setCurrentUser($member);
        try {
            $record = Member::create(['Created' => '2026-10-29 14:05:00', 'LastEdited' => '2026-10-29 15:06:07']);
            self::assertSame('2026/10/29, 2:05 PM<br><small>Modified at 3:06 PM</small>',
                GridFieldRecordDates::create()->getColumnContent(null, $record, 'RecordDates'));
        } finally {
            Security::setCurrentUser($previous);
            i18n::set_locale($locale);
        }
    }

    /** Core locale formatting remains available without a member; missing dates stay empty. */
    public function testAnonymousAndEmptyDates(): void
    {
        $previous = Security::getCurrentUser();
        Security::setCurrentUser(null);
        try {
            $component = GridFieldRecordDates::create();
            self::assertSame('', $component->getColumnContent(null, Member::create(), 'RecordDates'));
            $record = Member::create(['Created' => '2026-10-29 14:05:06']);
            self::assertSame(
                $record->dbObject('Created')->getFormatter(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE)
                    ->format($record->dbObject('Created')->getTimestamp()) . ', '
                    . $record->dbObject('Created')->getFormatter(IntlDateFormatter::NONE)
                        ->format($record->dbObject('Created')->getTimestamp()),
                $component->getColumnContent(null, $record, 'RecordDates')
            );
            self::assertStringNotContainsString(':06', $component->getColumnContent(null, $record, 'RecordDates'));
            $record->LastEdited = '2026-10-29 15:07:08';
            $time = $record->dbObject('LastEdited')->getFormatter(IntlDateFormatter::NONE)
                ->format($record->dbObject('LastEdited')->getTimestamp());
            self::assertStringEndsWith(' ' . $time . '</small>',
                $component->getColumnContent(null, $record, 'RecordDates'));
        } finally {
            Security::setCurrentUser($previous);
        }
    }
}
