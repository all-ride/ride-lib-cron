<?php

namespace ride\library\cron\job;

use ride\library\cron\exception\CronException;

/**
 * Generic implementation of a automated task (cron)
 */
class GenericCronJob implements CronJob {

    /**
     * Asterix for every value
     * @var string
     */
    const ASTERIX = '*';

    /**
     * Separator for an element of a list of values
     * @var string
     */
    const SEPARATOR_LIST = ',';

    /**
     * Separator for the increment value
     * @var string
     */
    const SEPARATOR_INCREMENT = '/';

    /**
     * Separator for a range of time
     * @var string
     */
    const SEPARATOR_RANGE = '-';

    /**
     * Unique id for this job
     * @var string
     */
    protected $id;

    /**
     * Callback to the task of this job
     * @var zibo\library\Callback
     */
    protected $callback;

    /**
     * String representation of the interval
     * @var string
     */
    protected $intervalDefinition;

    /**
     * Array with the minute values or ASTERIX for all values
     * @var string|array
     */
    protected $minute;

    /**
     * Array with the hour values or ASTERIX for all values
     * @var string|array
     */
    protected $hour;

    /**
     * Array with the day values or ASTERIX for all values
     * @var string|array
     */
    protected $day;

    /**
     * Array with the month values or ASTERIX for all values
     * @var string|array
     */
    protected $month;

    /**
     * Array with the day of week values or ASTERIX for all values
     * @var string|array
     */
    protected $dayOfWeek;

    /**
     * Constructs a new job
     * @param callback|\ride\library\reflection\Callback $callback
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $dayOfWeek
     * @return null
     */
    public function __construct($callback, $minute = null, $hour = null, $day = null, $month = null, $dayOfWeek = null) {
        $this->setCallback($callback);
        $this->setRunInterval($minute, $hour, $day, $month, $dayOfWeek);
    }

    /**
     * Gets a string representation of this job
     * @return string
     */
    public function __toString() {
        if (is_array($this->callback)) {
            if (is_object($this->callback[0])) {
                $string = get_class($this->callback[0]) . '->';
            } else {
                $string = $this->callback[0] . '::';
            }

            $string .= $this->callback[1];
        } else {
            $string = (string) $this->callback;
        }

        return $string . ' (' . $this->intervalDefinition . ')';
    }

    /**
     * Gets a unique id for this job
     * @return string
     */
    public function getId() {
        if ($this->id) {
            return $this->id;
        }

        $this->id = md5((string) $this);

        return $this->id;
    }

    /**
     * Sets the callback of this job
     * @param callback|\ride\library\reflection\Callback $callback
     * @return null
     */
    protected function setCallback($callback) {
        if (!$callback || (!is_string($callback) && !is_array($callback))) {
            throw new CronException('Could not set the callback: empty or invalid callback provided');
        }

        $this->callback = $callback;
    }

    /**
     * Gets the callback of this job
     * @return zibo\library\Callback
     */
    public function getCallback() {
        return $this->callback;
    }

    /**
     * Gets the interval definition
     * @return string
     */
    public function getIntervalDefinition() {
        return $this->intervalDefinition;
    }

    /**
     * Gets the time when this job should run next
     * @param int $time if not provided, the last run time will be used or now if this job hasn't run yet
     * @return int
     */
    public function getNextRunTime($time = null) {
        if ($time === null) {
            $time = time();
        }

        $minute = date('i', $time);
        $hour = date('G', $time);
        $day = date('j', $time);
        $month = date('n', $time);
        $year = date('Y', $time);
        $dayOfWeek = date('w', $time);

        if ($this->minute == self::ASTERIX && $this->hour == self::ASTERIX && $this->day == self::ASTERIX && $this->month == self::ASTERIX && $this->dayOfWeek == self::ASTERIX) {
            $this->addMinute($minute, $hour, $day, $month, $year);

            return mktime($hour, $minute, 0, $month, $day, $year);
        }

        $newHour = $hour;
        $newDay = $day;
        $newMonth = $month;
        $newYear = $year;
        $changed = false;

        $newMinute = $this->getNextRunIntervalValue($this->minute, $minute, null, false);
        if ($newMinute === null) {
            $newMinute = $minute;
            $this->addMinute($newMinute, $newHour, $newDay, $newMonth, $newYear);
        }

        if ($newMinute != $minute) {
            $changed = true;
        }

        $tmpHour = $newHour;
        if ($minute < $newMinute) {
           $newHour = $this->getNextRunIntervalValue($this->hour, $newHour, $newHour, true);
        } else {
           $newHour = $this->getNextRunIntervalValue($this->hour, $newHour, null, false);
        }
        if ($newHour === null) {
            $newHour = $tmpHour;
            if ($newHour == $hour) {
                $this->addHour($newHour, $newDay, $newMonth, $newYear);
            }
        }

        if ($newHour != $hour) {
            $changed = true;
            $newMinute = $this->getFirstRunIntervalValue($this->minute, 0);
        }

        $tmpDay = $newDay;
        if ($newHour < $hour || ($newHour == $hour && $newMinute <= $minute)) {
            $newDay = $this->getNextRunIntervalValue($this->day, $newDay, null, false);
        } else {
            $newDay = $this->getNextRunIntervalValue($this->day, $newDay, $newDay, true);
        }
        if ($newDay === null) {
            $newDay = $tmpDay;
            if ($newDay == $day) {
                $this->addDay($newDay, $newMonth, $newYear);
            }
        }

        if ($newDay != $day) {
            $changed = true;
            $newMinute = $this->getFirstRunIntervalValue($this->minute, 0);
            $newHour = $this->getFirstRunIntervalValue($this->hour, 0);
        }

        $tmpMonth = $newMonth;
        if ($newDay < $day || ($newDay == $day && ($newHour < $hour || ($newHour == $hour && $newMinute <= $minute)))) {
            $newMonth = $this->getNextRunIntervalValue($this->month, $newMonth, null, false);
        } else {
            $newMonth = $this->getNextRunIntervalValue($this->month, $newMonth, $newMonth, true);
        }
        if ($newMonth == null) {
            $newMonth = $tmpMonth;
            if ($newMonth == $month) {
                $this->addMonth($newMonth, $newYear);
            }
        }

        if ($newMonth != $month) {
            $newMinute = $this->getFirstRunIntervalValue($this->minute, 0);
            $newHour = $this->getFirstRunIntervalValue($this->hour, 0);
            $newDay = $this->getFirstRunIntervalValue($this->day, 1);
        }

        $nextRunTime = mktime($newHour, $newMinute, 0, $newMonth, $newDay, $newYear);

        if ($this->dayOfWeek != self::ASTERIX && !isset($this->dayOfWeek[date('w', $nextRunTime)])) {
            $nextRunTime = mktime(0, 0, 0, date('m', $nextRunTime), date('d', $nextRunTime), date('Y', $nextRunTime)) + 86399;

            return $this->getNextRunTime($nextRunTime);
        }

        return $nextRunTime;
    }

    /**
     * Gets the first interval value of a interval
     * @param string|array $values values of the interval
     * @param string $default value for when the interval values is a asterix
     * @return string the first interval value
     */
    protected function getFirstRunIntervalValue($values, $default) {
        if ($values === self::ASTERIX) {
            return $default;
        }

        foreach ($values as $v) {
            return $v;
        }
    }

    /**
     * Gets the next interval value which comes after the provided value
     * @param string|array $values values of the interval
     * @param string $value value to check
     * @param string $default value for when the interval values is a asterix
     * @param boolean $includeValue true to include the provided value, false otherwise
     * @return string the next interval value
     */
    protected function getNextRunIntervalValue($values, $value, $default, $includeValue = false) {
        if ($values === self::ASTERIX) {
            return $default;
        }

        if ($includeValue) {
            foreach ($values as $v) {
                if ($v >= $value) {
                    return $v;
                }
            }
        } else {
            foreach ($values as $v) {
                if ($v > $value) {
                    return $v;
                }
            }
        }

        foreach ($values as $v) {
            return $v;
        }
    }

    /**
     * Adds a minute
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $year
     * @return null
     */
    protected function addMinute(&$minute, &$hour, &$day, &$month, &$year) {
        $minute++;
        if ($minute == 60) {
            $this->addHour($hour, $day, $month, $year);
            $minute = 0;
        }
    }

    /**
     * Adds an hour
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $year
     * @return null
     */
    protected function addHour(&$hour, &$day, &$month, &$year) {
        $hour++;
        if ($hour == 24) {
            $this->addDay($day, $month, $year);
            $hour = 0;
        }
    }

    /**
     * Adds a day
     * @param string $day
     * @param string $month
     * @param string $year
     * @return null
     */
    protected function addDay(&$day, &$month, &$year) {
        $day++;
        if ($day > date('t', mktime(0, 0, 0, $month, 1, $year))) {
            $this->addMonth($month, $year);
            $day = 1;
        }
    }

    /**
     * Adds a month
     * @param string $month
     * @param string $year
     * @return null
     */
    protected function addMonth(&$month, &$year) {
        $month++;
        if ($month > 12) {
            $year++;
            $month = 1;
        }
    }

    /**
     * Sets the run interval for this job
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $dayOfWeek
     * @return null
     */
    protected function setRunInterval($minute = null, $hour = null, $day = null, $month = null, $dayOfWeek = null) {
        $this->setRunIntervalMinute($minute);
        $this->setRunIntervalHour($hour);
        $this->setRunIntervalDay($day);
        $this->setRunIntervalMonth($month);
        $this->setRunIntervalDayOfWeek($dayOfWeek);

        if ($minute === null) {
            $minute = self::ASTERIX;
        }
        if ($hour === null) {
            $hour = self::ASTERIX;
        }
        if ($day === null) {
            $day = self::ASTERIX;
        }
        if ($month === null) {
            $month = self::ASTERIX;
        }
        if ($dayOfWeek === null) {
            $dayOfWeek = self::ASTERIX;
        }

        $this->intervalDefinition = $minute . ' ' . $hour . ' ' . $day . ' ' . $month . ' ' . $dayOfWeek;
    }

    /**
     * Sets the run interval for minute
     * @param string $minute
     * @return null
     */
    protected function setRunIntervalMinute($minute = null) {
        if ($minute === null || $minute == self::ASTERIX) {
            $this->minute = self::ASTERIX;

            return;
        }

        $this->minute = $this->parseRunIntervalValue($minute, 0, 59);
    }

    /**
     * Sets the run interval for hour
     * @param string $hour
     * @return null
     */
    protected function setRunIntervalHour($hour = null) {
        if ($hour === null || $hour == self::ASTERIX) {
            $this->hour = self::ASTERIX;

            return;
        }

        $this->hour = $this->parseRunIntervalValue($hour, 0, 23);
    }

    /**
     * Sets the run interval for day
     * @param string $day
     * @return null
     */
    protected function setRunIntervalDay($day = null) {
        if ($day === null || $day == self::ASTERIX) {
            $this->day = self::ASTERIX;

            return;
        }

        $this->day = $this->parseRunIntervalValue($day, 1, 31);
    }

    /**
     * Sets the run interval for month
     * @param string $month
     * @return null
     */
    protected function setRunIntervalMonth($month = null) {
        if ($month === null || $month == self::ASTERIX) {
            $this->month = self::ASTERIX;

            return;
        }

        $this->month = $this->parseRunIntervalValue($month, 1, 12);
    }

    /**
     * Sets the run interval for day of the week
     * @param string $dayOfWeek
     * @return null
     */
    protected function setRunIntervalDayOfWeek($dayOfWeek = null) {
        if ($dayOfWeek === null || $dayOfWeek == self::ASTERIX) {
            $this->dayOfWeek = self::ASTERIX;

            return;
        }

        $this->dayOfWeek = $this->parseRunIntervalValue($dayOfWeek, 0, 7);
        if (isset($this->dayOfWeek[7])) {
            unset($this->dayOfWeek[7]);
            $this->dayOfWeek[0] = 0;
        }
    }

    /**
     * Gets an array with all the values of the interval value
     * @param string $value
     * @param integer $min
     * @param integer $max
     * @return array
     */
    protected function parseRunIntervalValue($value, $min, $max) {
        if ($value === null || $value === '') {
            throw new CronException('Could not parse the interval value: provided value is empty');
        }

        $values = array();

        $explodedValue = explode(self::SEPARATOR_LIST, $value);
        foreach ($explodedValue as $value) {
            $incrementValue = null;
            $posSeparatorIncrement = strpos($value, self::SEPARATOR_INCREMENT);
            if ($posSeparatorIncrement !== false) {
                if ($posSeparatorIncrement != 0) {
                    list($value, $incrementValue) = explode(self::SEPARATOR_INCREMENT, $value, 2);
                } else {
                    $incrementValue = substr($value, 1);
                    $value = self::ASTERIX;
                }

                if (!is_numeric($incrementValue)) {
                    throw new CronException('Could not parse the interval value: invalid increment value');
                }

                if ($value == self::ASTERIX) {
                    $value = $min . self::SEPARATOR_RANGE . $max;
                }
            }

            $posSeparatorRange = strpos($value, self::SEPARATOR_RANGE);
            if ($posSeparatorRange !== false && $posSeparatorRange != 0) {
                $range = explode(self::SEPARATOR_RANGE, $value, 2);

                $this->validateRunIntervalValue($range[0], $min, $max);
                $this->validateRunIntervalValue($range[1], $min, $max);
                if ($range[0] > $range[1]) {
                    throw new CronException('Could not parse the interval value: ' . $range[0] . ' is greater then ' . $range[1] . ' in ' . $value);
                }

                $loopIncrement = 1;
                if ($incrementValue) {
                    $loopIncrement = $incrementValue;
                }

                for ($i = $range[0]; $i <= $range[1]; $i += $loopIncrement) {
                    $values[(int) $i] = (int) $i;
                }
            } else {
                $this->validateRunIntervalValue($value, $min, $max);

                if ($incrementValue) {
                    do {
                       $values[(int) $value] = (int) $value;

                       $value += $incrementValue;
                    } while ($value <= $max);
                } else {
                   $values[(int) $value] = (int) $value;
                }

            }
        }

        asort($values);

        return $values;
    }

    /**
     * Validates a numeric value between the provided minimum and maximum
     * @param mixed $value
     * @param integer $min
     * @param integer $max
     * @return null
     * @throws \ride\library\cron\exception\CronException when the provided
     * value is invalid
     */
    protected function validateRunIntervalValue($value, $min, $max) {
        if (!is_numeric($value)) {
            throw new CronException('Invalid value provided: ' . $value);
        }

        if ($value < $min || $max < $value) {
            throw new CronException('Invalid value provided: ' . $value . ' should be between ' . $min . ' and ' . $max);
        }
    }

}
