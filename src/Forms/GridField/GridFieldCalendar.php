<?php

namespace Clesson\Silverstripe\Forms\GridField;

use DateTime;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Model\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * A GridField component that displays records in a calendar view.
 *
 * @package Clesson\GridfieldPro
 * @subpackage Forms
 */
class GridFieldCalendar implements GridField_HTMLProvider, GridField_URLHandler
{

    /**
     * Monthly view with a grid for each day
     */
    public const VIEW_DAYGRIDMONTH = 'dayGridMonth'; //	Monatsansicht mit einem Raster für jeden Tag.

    /**
     * Weekly view in grid format (all day).
     */
    public const VIEW_DAYGRIDWEEK= 'dayGridWeek';

    /**
     * Daily view in grid format (all day).
     */
    public const VIEW_DAYGRIDDAY = 'dayGridDay';

    /**
     * Week view with timeline (hour-based events).
     */
    public const VIEW_TIMEGRIDWEEK = 'timeGridWeek';

    /**
     * Daily view with timeline (hour-based events).
     */
    public const VIEW_TIMEGRIDDAY = 'timeGridDay';

    /**
     * List of all events of a year (clear style).
     */
    public const VIEW_LISTYEAR = 'listYear';

    /**
     * List of all events of a month.
     */
    public const VIEW_LISTMONTH = 'listMonth';

    /**
     * List of all events of a week.
     */
    public const VIEW_LISTWEEK = 'listWeek';

    /**
     * List of all events of a day.
     */
    public const VIEW_LISTDAY = 'listDay';




    /**
     * @var mixed The titleFormat value. Default is "Title".
     */
    protected mixed $_titleFormat = "Title";

    /**
     * @var mixed The startDateFormat value. Default is "Start".
     */
    protected mixed $_startDateFormat = "Start";

    /**
     * @var mixed The endDateFormat value. Default is "".
     */
    protected mixed $_endDateFormat = "";

    /**
     * @var mixed The fullDayFormat value. Default is "".
     */
    protected mixed $_fullDayFormat = "";

    /**
     * @var mixed The colorFormat value. Default is "Color".
     */
    protected mixed $_colorFormat = "";

    /**
     * @var int The contentHeight value. Default is 0.
     */
    protected int $_contentHeight = 0;

    /**
     * @var string The initialView value. Default is "dayGridMonth".
     */
    protected string $_initialView = "dayGridMonth";

    /**
     * @var string The locale value. Default is i18n::get_locale().
     */
    protected string $_locale = "en";

    /**
     * @var string The targetFragment value. Default is "before".
     */
    protected string $_targetFragment = "before";

    /**
     * An array of actions that can be accessed via a request. Each array element should be an action name, and the
     * permissions or conditions required to allow the user to access it.
     * @var string[]
     */
    private static $allowed_actions = [
        'handleCalendarOptions',
        'handleCalendarEvents',
    ];

    /**
     *
     * @param string $targetFragment
     */
    public function __construct(string $targetFragment = 'before', string $locale = '')
    {
        $this->setTargetFragment($targetFragment);
        $this->setLocale($locale ? $locale : i18n::get_locale());
    }

    /**
     * Set the titleFormat. You can specify the name of a property or a callback function to determine the value.
     * @param mixed $titleFormat
     * @return $this
     */
    public function setTitleFormat(mixed $titleFormat)
    {
        $this->_titleFormat = $titleFormat;
        return $this;
    }

    /**
     * Set the titleFormat. Default value is "Title".
     * @return mixed the titleFormat
     */
    public function getTitleFormat(): mixed
    {
        return $this->_titleFormat;
    }

    /**
     * Set the startDateFormat. You can specify the name of a property or a callback function to determine the value.
     * @param mixed $startDateFormat
     * @return $this
     */
    public function setStartDateFormat(mixed $startDateFormat)
    {
        $this->_startDateFormat = $startDateFormat;
        return $this;
    }

    /**
     * Set the startDateFormat. Default value is "Start".
     * @return mixed the startDateFormat
     */
    public function getStartDateFormat(): mixed
    {
        return $this->_startDateFormat;
    }

    /**
     * Set the endDateFormat. You can specify the name of a property or a callback function to determine the value.
     * @param mixed $endDateFormat
     * @return $this
     */
    public function setEndDateFormat(mixed $endDateFormat)
    {
        $this->_endDateFormat = $endDateFormat;
        return $this;
    }

    /**
     * Set the endDateFormat. Default value is "".
     * @return mixed the endDateFormat
     */
    public function getEndDateFormat(): mixed
    {
        return $this->_endDateFormat;
    }

    /**
     * Set the fullDayFormat. You can specify the name of a property or a callback function to determine the value.
     * @param mixed $fullDayFormat
     * @return $this
     */
    public function setFullDayFormat(mixed $fullDayFormat)
    {
        $this->_fullDayFormat = $fullDayFormat;
        return $this;
    }

    /**
     * Set the fullDayFormat. Default value is "FullDay".
     * @return mixed the fullDayFormat
     */
    public function getFullDayFormat(): mixed
    {
        return $this->_fullDayFormat;
    }

    /**
     * Set the colorFormat. You can specify the name of a property or a callback function to determine the value.
     * @param mixed $colorFormat
     * @return $this
     */
    public function setColorFormat(mixed $colorFormat)
    {
        $this->_colorFormat = $colorFormat;
        return $this;
    }

    /**
     * Set the colorFormat. Default value is "Color".
     * @return mixed the colorFormat
     */
    public function getColorFormat(): mixed
    {
        return $this->_colorFormat;
    }

    /**
     * Set the contentHeight.
     * @param int $contentHeight
     * @return $this
     */
    public function setContentHeight(int $contentHeight): GridFieldCalendar
    {
        $this->_contentHeight = $contentHeight;
        return $this;
    }

    /**
     * Set the contentHeight. Default value is 0. Please note that this only affects the height of the content, not
     * the entire height of the calendar.
     * @return int the contentHeight
     */
    public function getContentHeight(): int
    {
        return $this->_contentHeight;
    }

    /**
     * Defines the initial view in which the calendar is displayed. All values offered by fullcalendar are possible
     * (dayGridMonth,timeGridWeek,timeGridDay), as well as your own views, which are specified in the configuration
     * under “views”.
     * @param string $initialView
     * @return $this
     */
    public function setInitialView(string $initialView): GridFieldCalendar
    {
        $this->_initialView = $initialView;
        return $this;
    }

    /**
     * Set the initialView. Default value is "dayGridMonth".
     * @return string the initialView
     */
    public function getInitialView(): string
    {
        return $this->_initialView;
    }

    /**
     * Define the locale for the calendar. By default, the calendar is displayed in the currently set locale, which is
     * determined using i18n::get_locale(). If you specify a different locale, this overwrites the fallback level.
     * @param string $locale
     * @return $this
     */
    public function setLocale(string $locale): GridFieldCalendar
    {
        $this->_locale = $locale;
        return $this;
    }

    /**
     * Set the locale. Default value is i18n::get_locale().
     * @return string the locale
     */
    public function getLocale(): string
    {
        return $this->_locale;
    }

    /**
     * Set the targetFragment.
     * @param string $targetFragment
     * @return $this
     */
    public function setTargetFragment(string $targetFragment)
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
     * Returns a map where the keys are fragment names and the values are pieces of HTML to add to these fragments.
     * @param $gridField
     * @return array
     */
    public function getHTMLFragments($gridField): array
    {
        $template = SSViewer::create(__CLASS__);
        $data = ArrayData::create([
            'AttributesHTML' => $this->getAttributesHTML($gridField),
        ]);
        return [
            $this->_targetFragment => $template->process($data)
        ];
    }

    /**
     * Returns the configuration object used for the calendar.
     * @param GridField $gridField
     * @param $request
     * @return HTTPResponse
     */
    public function handleCalendarOptions(GridField $gridField, $request): HTTPResponse
    {
        $options = (object)[
            'options' => (object)[
                'initialView' => $this->getInitialView(),
                'initialDate' => $this->getInitialDate($gridField),
                'locale' => 'de', //$this->getLocale(),
                'contentHeight' => $this->getContentHeight() === 0 ? 'auto' : $this->getContentHeight(),
                'headerToolbar' => (object)[
                    'left' => 'prev,next today', // Buttons für vorherigen/nächsten Monat und "Heute"
                    'center' => 'title', // Titel des aktuellen Zeitraums
                    'right' => 'reloadButton multiMonth,dayGridMonth,timeGridWeek,timeGridDay', // Buttons für verschiedene Ansichten
                ],
                'buttonText' => (object)[
                    'multiMonth' => _t(__CLASS__ . '.BUTTON_TEXT_MULTI_MONTH', '3 months'),
                    'today' => _t(__CLASS__ . '.BUTTON_TEXT_TODAY', 'Today'),
                    'month' => _t(__CLASS__ . '.BUTTON_TEXT_MONTH', 'Month'),
                    'week' => _t(__CLASS__ . '.BUTTON_TEXT_WEEK', 'Week'),
                    'day' => _t(__CLASS__ . '.BUTTON_TEXT_DAY', 'Day'),
                    'list' => _t(__CLASS__ . '.BUTTON_TEXT_LIST', 'List'),
                ],
                'customButtons' => (object)[
                    'reloadButton' => (object)[
                        'text' => _t(__CLASS__ . '.BUTTON_TEXT_RELOAD', 'Reload'),
                        'click' => 'function() { alert("clicked the custom button!"); }',
                    ],
                ],
                'views' => (object)[
                    'multiMonth' => (object)[
                        'type' => 'dayGridMonth',
                        'duration' => (object)[
                            'months' => 3
                        ]
                    ]
                ],
                'events' => $this->getEventList($gridField),
                'selectable' => true
            ]
        ];
        $response = new HTTPResponse();
        $response->setStatusCode(200);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->setBody(json_encode($options));
        return $response;
    }

    /**
     * Used as relod function by the calendar. Returns a list of events and a initial date to switch calendar view.
     * @param GridField $gridField
     * @param $request
     * @return HTTPResponse
     */
    public function handleCalendarEvents(GridField $gridField, $request): HTTPResponse
    {
        $data = (object)[
            'initialDate' => $this->getInitialDate($gridField),
            'items' => $this->getEventList($gridField)
        ];
        $response = new HTTPResponse();
        $response->setStatusCode(200);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->setBody(json_encode($data));
        return $response;
    }

    /**
     * From the list of events to be displayed, select a date that is closest to the current time. This date is used to
     * set the initial view and the view as soon as the event list has been reloaded to a date. This is to prevent the
     * user from having to click through to the events each time the event list is loaded.
     * @param GridField $gridField
     * @return string
     */
    protected function getInitialDate(GridField $gridField): string
    {
        $startDateFormat = $this->getStartDateFormat();
        $events = array_map(function (DataObject $Model) use ($startDateFormat) {
            $date = static::formatValue($Model, $startDateFormat);
            return [
                'diff' => strtotime($date) - time(),
                'date' => $date
            ];
        }, $gridField->getList()->toArray());
        if (!$events) {
            return DBDatetime::now()->Format(DBDatetime::ISO_DATETIME_NORMALISED);
        }
        usort($events, function ($a, $b) {
            if ($a['diff'] < $b['diff']) {
                return 1;
            } elseif ($a['diff'] > $b['diff']) {
                return -1;
            }
            return 0;
        });
        $event = current($events);
        return $event['date'];
    }

    /**
     * Creates a list of events for the calendar.
     * @param GridField $gridField
     * @return array
     */
    protected function getEventList(GridField $gridField): array
    {
        $titleFormat = $this->getTitleFormat();
        $startDateFormat = $this->getStartDateFormat();
        $endDateFormat = $this->getEndDateFormat();
        $fullDayFormat = $this->getFullDayFormat();
        $colorFormat = $this->getColorFormat();
        $classNames = []; //$this->getColorFormat();



        return array_map(function (DataObject $Model) use ($titleFormat, $startDateFormat, $endDateFormat, $fullDayFormat, $colorFormat, $classNames, $gridField) {
            $result = (object)[
                'id' => $Model->ID,
                'title' => static::formatValue($Model, $titleFormat),
                'start' => static::formatValue($Model, $startDateFormat),
                'allDay' => static::formatValue($Model, $fullDayFormat) ? true : false
            ];
            if ($endDateFormat) {
                $result->end = static::formatValue($Model, $endDateFormat);
            }
            if ($colorFormat) {
                $result->color = static::formatValue($Model, $colorFormat);
            }
            $classNames[] = 'event-default';
            if ($classNames) {
                $result->className = implode(' ', $classNames);
            }
            $result->url = Director::absoluteURL(Controller::join_links(
                $gridField->Link('item'),
                $Model->ID,
                'edit'
            ));
            return $result;
        }, $gridField->getList()->toArray());
    }

    /**
     * Uses property name or callable to return a value.
     * @param DataObject $record
     * @param mixed $format
     * @return string
     */
    protected static function formatValue(DataObject $record, mixed $format): string
    {
        // Attention! If you pass the name of a property that is called “Date”, for example, then the is_callable()
        // check is positive!!! Therefore the double check for is_string and is_callable takes place!
        if (!is_string($format) && is_callable($format)) {
            return $format($record);
        }
        if ($record->hasField($format)) {
            $dbObject = $record->dbObject($format);
            if ($dbObject && $dbObject instanceof DBDatetime) {
                /** @var DBDatetime $dbObject */
                return $dbObject->Format(DBDatetime::ISO_DATETIME_NORMALISED);
            } else if ($dbObject && $dbObject instanceof DBDate) {
                /** @var DBDate $dbObject */
                return $dbObject->Format(DBDate::ISO_DATE);
            } else if ($dbObject) {
                return (string)$dbObject;
            }
            return $record->$format;
        }
        return (string)$format;
    }

    /**
     * Return URLs to be handled by this grid field, in an array the same form as $url_handlers.
     * Handler methods will be called on the component, rather than the GridField.
     * @param $gridField
     * @return string[]
     */
    public function getURLHandlers($gridField): array
    {
        return [
            'calendar/options' => 'handleCalendarOptions',
            'calendar/events' => 'handleCalendarEvents',
        ];
    }

    /**
     * Returns all the attributes of the div for the js calendar.
     * @param GridField $gridField
     * @return array
     */
    public function getAttributes(GridField $gridField): array
    {
        list($lang, $country) = array_pad(explode('_', $this->getLocale()), 2, 'en');
        return [
            'data-options-url' => Controller::join_links(
                $gridField->Link(),
                'calendar/options'
            ),
            'data-eventlist-url' => Controller::join_links(
                $gridField->Link(),
                'calendar/events'
            ),
            'style' => 'min-height:' . $this->getContentHeight() . 'px;',
        ];
    }

    /**
     * Returns all the attributes as html safe string.
     * @param GridField $gridField
     * @return DBField
     */
    public function getAttributesHTML(GridField $gridField): DBField
    {
        $attributes = $this->getAttributes($gridField);
        $html = implode(' ', array_map(function ($key, $value) {
            return $key . '=' . $value . '';
        }, array_keys($attributes), $attributes));
        return DBField::create_field('HTMLFragment', $html);
    }

}
