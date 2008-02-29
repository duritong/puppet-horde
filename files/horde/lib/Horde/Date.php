<?php

define('HORDE_DATE_SUNDAY',    0);
define('HORDE_DATE_MONDAY',    1);
define('HORDE_DATE_TUESDAY',   2);
define('HORDE_DATE_WEDNESDAY', 3);
define('HORDE_DATE_THURSDAY',  4);
define('HORDE_DATE_FRIDAY',    5);
define('HORDE_DATE_SATURDAY',  6);

define('HORDE_DATE_MASK_SUNDAY',    1);
define('HORDE_DATE_MASK_MONDAY',    2);
define('HORDE_DATE_MASK_TUESDAY',   4);
define('HORDE_DATE_MASK_WEDNESDAY', 8);
define('HORDE_DATE_MASK_THURSDAY', 16);
define('HORDE_DATE_MASK_FRIDAY',   32);
define('HORDE_DATE_MASK_SATURDAY', 64);
define('HORDE_DATE_MASK_WEEKDAYS', 62);
define('HORDE_DATE_MASK_WEEKEND',  65);
define('HORDE_DATE_MASK_ALLDAYS', 127);

define('HORDE_DATE_MASK_SECOND',    1);
define('HORDE_DATE_MASK_MINUTE',    2);
define('HORDE_DATE_MASK_HOUR',      4);
define('HORDE_DATE_MASK_DAY',       8);
define('HORDE_DATE_MASK_MONTH',    16);
define('HORDE_DATE_MASK_YEAR',     32);
define('HORDE_DATE_MASK_ALLPARTS', 63);

/**
 * Horde Date wrapper/logic class, including some calculation
 * functions.
 *
 * $Horde: framework/Date/Date.php,v 1.8.10.9 2007/03/23 14:41:46 jan Exp $
 *
 * @package Horde_Date
 */
class Horde_Date {

    /**
     * Year
     *
     * @var integer
     */
    var $year;

    /**
     * Month
     *
     * @var integer
     */
    var $month;

    /**
     * Day
     *
     * @var integer
     */
    var $mday;

    /**
     * Hour
     *
     * @var integer
     */
    var $hour = 0;

    /**
     * Minute
     *
     * @var integer
     */
    var $min = 0;

    /**
     * Second
     *
     * @var integer
     */
    var $sec = 0;

    /**
     * Build a new date object. If $date contains date parts, use them
     * to initialize the object.
     */
    function Horde_Date($date = null)
    {
        if (is_array($date) || is_object($date)) {
            foreach ($date as $key => $val) {
                if (in_array($key, array('year', 'month', 'mday', 'hour', 'min', 'sec'))) {
                    $this->$key = (int)$val;
                }
            }
        } elseif (!is_null($date)) {
            // Match YYYY-MM-DD HH:MM:SS, YYYYMMDDHHMMSS and YYYYMMDD'T'HHMMSS'Z'.
            if (preg_match('/(\d{4})-?(\d{2})-?(\d{2})T? ?(\d{2}):?(\d{2}):?(\d{2})Z?/', $date, $parts)) {
                $this->year = (int)$parts[1];
                $this->month = (int)$parts[2];
                $this->mday = (int)$parts[3];
                $this->hour = (int)$parts[4];
                $this->min = (int)$parts[5];
                $this->sec = (int)$parts[6];
            } else {
                // Try as a timestamp.
                $parts = @getdate($date);
                if ($parts) {
                    $this->year = $parts['year'];
                    $this->month = $parts['mon'];
                    $this->mday = $parts['mday'];
                    $this->hour = $parts['hours'];
                    $this->min = $parts['minutes'];
                    $this->sec = $parts['seconds'];
                }
            }
        }
    }

    /**
     * Return the unix timestamp representation of this date.
     *
     * @return integer  A unix timestamp.
     */
    function timestamp()
    {
        return @mktime($this->hour, $this->min, $this->sec, $this->month, $this->mday, $this->year);
    }

    /**
     * Return the unix timestamp representation of this date, 12:00am.
     *
     * @return integer  A unix timestamp.
     */
    function datestamp()
    {
        return @mktime(0, 0, 0, $this->month, $this->mday, $this->year);
    }

    /**
     * @static
     */
    function isLeapYear($year)
    {
        if (strlen($year) != 4 || preg_match('/\D/', $year)) {
            return false;
        }

        return (($year % 4 == 0 && $year % 100 != 0) || $year % 400 == 0);
    }

    /**
     * Returns the day of the year (1-366) that corresponds to the
     * first day of the given week.
     *
     * TODO: with PHP 5.1+, see http://derickrethans.nl/calculating_start_and_end_dates_of_a_week.php
     *
     * @param integer $week  The week of the year to find the first day of.
     * @param integer $year  The year to calculate for.
     *
     * @return integer  The day of the year of the first day of the given week.
     */
    function firstDayOfWeek($week, $year)
    {
        $jan1 = new Horde_Date(array('year' => $year, 'month' => 1, 'mday' => 1));
        $start = $jan1->dayOfWeek();
        if ($start > HORDE_DATE_THURSDAY) {
            $start -= 7;
        }
        return (($week * 7) - (7 + $start)) + 1;
    }

    /**
     * @static
     */
    function daysInMonth($month, $year)
    {
        if ($month == 2) {
            if (Horde_Date::isLeapYear($year)) {
                return 29;
            } else {
                return 28;
            }
        } elseif ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
            return 30;
        } else {
            return 31;
        }
    }

    /**
     * Return the day of the week (0 = Sunday, 6 = Saturday) of this
     * object's date.
     *
     * @return integer  The day of the week.
     */
    function dayOfWeek()
    {
        if ($this->month > 2) {
            $month = $this->month - 2;
            $year = $this->year;
        } else {
            $month = $this->month + 10;
            $year = $this->year - 1;
        }

        $day = (floor((13 * $month - 1) / 5) +
                $this->mday + ($year % 100) +
                floor(($year % 100) / 4) +
                floor(($year / 100) / 4) - 2 *
                floor($year / 100) + 77);

        return (int)($day - 7 * floor($day / 7));
    }

    /**
     * Returns the day number of the year (1 to 365/366).
     *
     * @return integer  The day of the year.
     */
    function dayOfYear()
    {
        $monthTotals = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $dayOfYear = $this->mday + $monthTotals[$this->month - 1];
        if (Horde_Date::isLeapYear($this->year) && $this->month > 2) {
            ++$dayOfYear;
        }

        return $dayOfYear;
    }

    /**
     * Returns week of the year, first Monday is first day of first
     * week.
     *
     * @return integer  The week number.
     */
    function weekOfYear()
    {
        return date('W', $this->timestamp());
    }

    /**
     * Return the number of weeks in the given year (52 or 53).
     *
     * @param integer $year  The year to count the number of weeks in.
     *
     * @return integer $numWeeks      The number of weeks in $year.
     */
    function weeksInYear($year)
    {
        // Find the last Thursday of the year.
        $day = 31;
        while (date('w', mktime(0, 0, 0, 12, $day, $year)) != HORDE_DATE_THURSDAY) {
            --$day;
        }

        $date = new Horde_Date(array('year' => $year, 'month' => 12, 'mday' => $day));
        return $date->weekOfYear();
    }

    /**
     * Set the date of this object to the $nth weekday of $weekday.
     *
     * @param integer $weekday  The day of the week (0 = Sunday, etc).
     * @param integer $nth      The $nth $weekday to set to (defaults to 1).
     */
    function setNthWeekday($weekday, $nth = 1)
    {
        if ($weekday < HORDE_DATE_SUNDAY || $weekday > HORDE_DATE_SATURDAY) {
            return false;
        }

        $this->mday = 1;
        $first = $this->dayOfWeek();
        if ($weekday < $first) {
            $this->mday = 8 + $weekday - $first;
        } else {
            $this->mday = $weekday - $first + 1;
        }
        $this->mday += 7 * $nth - 7;

        $this->correct();

        return true;
    }

    function dump($prefix = '')
    {
        echo ($prefix ? $prefix . ': ' : '') . $this->year . '-' . $this->month . '-' . $this->mday . "<br />\n";
    }

    /**
     * Is the date currently represented by this object a valid date?
     *
     * @return boolean  Validity, counting leap years, etc.
     */
    function isValid()
    {
        if ($this->year < 0 || $this->year > 9999) {
            return false;
        }
        return checkdate($this->month, $this->mday, $this->year);
    }

    /**
     * Correct any over- or underflows in any of the date's members.
     *
     * @param integer $mask  We may not want to correct some overflows.
     */
    function correct($mask = HORDE_DATE_MASK_ALLPARTS)
    {
        if ($mask & HORDE_DATE_MASK_SECOND) {
            while ($this->sec < 0) {
                --$this->min;
                $this->sec += 60;
            }
            while ($this->sec > 59) {
                ++$this->min;
                $this->sec -= 60;
            }
        }

        if ($mask & HORDE_DATE_MASK_MINUTE) {
            while ($this->min < 0) {
                --$this->hour;
                $this->min += 60;
            }
            while ($this->min > 59) {
                ++$this->hour;
                $this->min -= 60;
            }
        }

        if ($mask & HORDE_DATE_MASK_HOUR) {
            while ($this->hour < 0) {
                --$this->mday;
                $this->hour += 24;
            }
            while ($this->hour > 23) {
                ++$this->mday;
                $this->hour -= 24;
            }
        }

        if ($mask & HORDE_DATE_MASK_MONTH) {
            while ($this->month > 12) {
                ++$this->year;
                $this->month -= 12;
            }
            while ($this->month < 1) {
                --$this->year;
                $this->month += 12;
            }
        }

        if ($mask & HORDE_DATE_MASK_DAY) {
            while ($this->mday > Horde_Date::daysInMonth($this->month, $this->year)) {
                $this->mday -= Horde_Date::daysInMonth($this->month, $this->year);
                ++$this->month;
                $this->correct(HORDE_DATE_MASK_MONTH);
            }
            while ($this->mday < 1) {
                --$this->month;
                $this->correct(HORDE_DATE_MASK_MONTH);
                $this->mday += Horde_Date::daysInMonth($this->month, $this->year);
            }
        }
    }

    /**
     * Compare this date to another date object to see which one is
     * greater (later). Assumes that the dates are in the same
     * timezone.
     *
     * @param mixed $date  The date to compare to.
     *
     * @return integer  ==  0 if the dates are equal
     *                  >=  1 if this date is greater (later)
     *                  <= -1 if the other date is greater (later)
     */
    function compareDate($date)
    {
        if (!is_a($date, 'Horde_Date')) {
            $date = new Horde_Date($date);
        }

        if ($this->year != $date->year) {
            return $this->year - $date->year;
        } elseif ($this->month != $date->month) {
            return $this->month - $date->month;
        } else {
            return $this->mday - $date->mday;
        }
    }

    /**
     * Compare this to another date object by time, to see which one
     * is greater (later). Assumes that the dates are in the same
     * timezone.
     *
     * @param mixed $date  The date to compare to.
     *
     * @return integer  ==  0 if the dates are equal
     *                  >=  1 if this date is greater (later)
     *                  <= -1 if the other date is greater (later)
     */
    function compareTime($date)
    {
        if (!is_a($date, 'Horde_Date')) {
            $date = new Horde_Date($date);
        }

        if ($this->hour != $date->hour) {
            return $this->hour - $date->hour;
        } elseif ($this->min != $date->min) {
            return $this->min - $date->min;
        } else {
            return $this->sec - $date->sec;
        }
    }

    /**
     * Compare this to another date object, including times, to see
     * which one is greater (later). Assumes that the dates are in the
     * same timezone.
     *
     * @param mixed $date  The date to compare to.
     *
     * @return integer  ==  0 if the dates are equal
     *                  >=  1 if this date is greater (later)
     *                  <= -1 if the other date is greater (later)
     */
    function compareDateTime($date)
    {
        if (!is_a($date, 'Horde_Date')) {
            $date = new Horde_Date($date);
        }

        if ($diff = $this->compareDate($date)) {
            return $diff;
        } else {
            return $this->compareTime($date);
        }
    }

    /**
     * Get the time offset for local time zone.
     *
     * @param boolean $colon  Place a colon between hours and minutes?
     *
     * @return string  Timezone offset as a string in the format +HH:MM.
     */
    function tzOffset($colon = true)
    {
        $secs = date('Z', $this->timestamp());

        if ($secs < 0) {
            $sign = '-';
            $secs = -$secs;
        } else {
            $sign = '+';
        }
        $colon = $colon ? ':' : '';
        $mins = intval(($secs + 30) / 60);
        return sprintf('%s%02d%s%02d',
                       $sign, $mins / 60, $colon, $mins % 60);
    }

    /**
     * Format time in ISO-8601 format.
     *
     * @return string  Date and time in ISO-8601 format.
     */
    function iso8601DateTime()
    {
        $tzoff = $this->tzOffset();
        $date = $this->year . '-' . $this->month . '-' . $this->mday;
        $time = $this->hour . ':' . $this->min . ':' . $this->sec;

        return $date . 'T' . $time . $tzoff;
    }

    /**
     * Format time in RFC 2822 format.
     *
     * @return string  Date and time in RFC 2822 format.
     */
    function rfc2822DateTime()
    {
        return date('D, j M Y H:i:s ', $this->timestamp()) . $this->tzOffset(false);
    }

    /**
     * Format time in RFC 3339 format.
     *
     * @return string  Date and time in RFC 3339 format.
     */
    function rfc3339DateTime()
    {
        return date('Y-m-d\TH:i', $this->timestamp());
    }

    /**
     * Format time to standard 'ctime' format.
     *
     * @return string  Date and time.
     */
    function cTime($time = false)
    {
        return date('D M j H:i:s Y', $this->timestamp());
    }

    /**
     * Format date and time using strftime() format.
     *
     * @return string  strftime() formatted date and time.
     */
    function strftime($format)
    {
        return strftime($format, $this->timestamp());
    }

}
