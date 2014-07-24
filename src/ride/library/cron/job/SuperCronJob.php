<?php

namespace ride\library\cron\job;

/**
 * Generic cron job extended to have a second precision instead of a minute one
 */
class SuperCronJob extends GenericCronJob {

    /**
     * Array with the second values or ASTERIX for all values
     * @var string|array
     */
    protected $second;

    /**
     * Constructs a new job
     * @param callback|\ride\library\reflection\Callback $callback
     * @param string $second
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $dayOfWeek
     * @return null
     */
    public function __construct($callback, $second = null, $minute = null, $hour = null, $day = null, $month = null, $dayOfWeek = null) {
        $this->setCallback($callback);
        $this->setRunInterval($second, $minute, $hour, $day, $month, $dayOfWeek);
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

        $second = date('s', $time);
        $minute = date('i', $time);
        $hour = date('G', $time);
        $day = date('j', $time);
        $month = date('n', $time);
        $year = date('Y', $time);
        $dayOfWeek = date('w', $time);

        if ($this->second == self::ASTERIX && $this->minute == self::ASTERIX && $this->hour == self::ASTERIX && $this->day == self::ASTERIX && $this->month == self::ASTERIX && $this->dayOfWeek == self::ASTERIX) {
            $this->addSecond($second, $minute, $hour, $day, $month, $year);

            return mktime($hour, $minute, $second, $month, $day, $year);
        }

        $newMinute = $minute;
        $newHour = $hour;
        $newDay = $day;
        $newMonth = $month;
        $newYear = $year;
        $changed = false;

        $newSecond = $this->getNextRunIntervalValue($this->second, $second, null, false);
        if ($newSecond === null) {
            $newSecond = $second;
            $this->addSecond($newSecond, $newMinute, $newHour, $newDay, $newMonth, $newYear);
        }

        if ($newSecond != $second) {
            $changed = true;
        }

        $tmpMinute = $newMinute;
        if ($second < $newSecond) {
            $newMinute = $this->getNextRunIntervalValue($this->minute, $newMinute, $newMinute, true);
        } else {
            $newMinute = $this->getNextRunIntervalValue($this->minute, $newMinute, null, false);
        }
        if ($newMinute === null) {
            $newMinute = $tmpMinute;
            if ($newMinute == $minute) {
                $this->addMinute($newMinute, $newHour, $newDay, $newMonth, $newYear);
            }
        }

        if ($newMinute != $minute) {
            $changed = true;
            $newSecond = $this->getFirstRunIntervalValue($this->second, 0);
        }

        $tmpHour = $newHour;
        if ($newMinute < $minute || ($newMinute == $minute && $newSecond <= $second)) {
           $newHour = $this->getNextRunIntervalValue($this->hour, $newHour, null, false);
        } else {
           $newHour = $this->getNextRunIntervalValue($this->hour, $newHour, $newHour, true);
        }
        if ($newHour === null) {
            $newHour = $tmpHour;
            if ($newHour == $hour) {
                $this->addHour($newHour, $newDay, $newMonth, $newYear);
            }
        }

        if ($newHour != $hour) {
            $changed = true;
            $newSecond = $this->getFirstRunIntervalValue($this->second, 0);
            $newMinute = $this->getFirstRunIntervalValue($this->minute, 0);
        }

        $tmpDay = $newDay;
        if ($newHour < $hour || ($newHour == $hour && ($newMinute < $minute || ($newMinute == $minute && $newSecond <= $second)))) {
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
            $newSecond = $this->getFirstRunIntervalValue($this->second, 0);
            $newMinute = $this->getFirstRunIntervalValue($this->minute, 0);
            $newHour = $this->getFirstRunIntervalValue($this->hour, 0);
        }

        $tmpMonth = $newMonth;
        if ($newDay < $day || ($newDay == $day && ($newHour < $hour || ($newHour == $hour && ($newMinute < $minute || ($newMinute == $minute && $newSecond <= $second)))))) {
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
            $newSecond = $this->getFirstRunIntervalValue($this->second, 0);
            $newMinute = $this->getFirstRunIntervalValue($this->minute, 0);
            $newHour = $this->getFirstRunIntervalValue($this->hour, 0);
            $newDay = $this->getFirstRunIntervalValue($this->day, 1);
        }

        $nextRunTime = mktime($newHour, $newMinute, $newSecond, $newMonth, $newDay, $newYear);

        if ($this->dayOfWeek != self::ASTERIX && !isset($this->dayOfWeek[date('w', $nextRunTime)])) {
            $nextRunTime = mktime(0, 0, 0, date('m', $nextRunTime), date('d', $nextRunTime), date('Y', $nextRunTime)) + 86399;

            return $this->getNextRunTime($nextRunTime);
        }

        return $nextRunTime;
    }

    /**
     * Adds a second
     * @param string $second
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $year
     * @return null
     */
    protected function addSecond(&$second, &$minute, &$hour, &$day, &$month, &$year) {
        $second++;
        if ($second == 60) {
            $this->addMinute($minute, $hour, $day, $month, $year);
            $second = 0;
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
    protected function setRunInterval($second = null, $minute = null, $hour = null, $day = null, $month = null, $dayOfWeek = null) {
        parent::setRunInterval($minute, $hour, $day, $month, $dayOfWeek);

        $this->setRunIntervalSecond($second);

        if ($second=== null) {
            $second = self::ASTERIX;
        }

        $this->intervalDefinition = $second . ' ' . $this->intervalDefinition;
    }

    /**
     * Sets the run interval for second
     * @param string $second
     * @return null
     */
    protected function setRunIntervalSecond($second = null) {
        if ($second === null || $second == self::ASTERIX) {
            $this->second = self::ASTERIX;

            return;
        }

        $this->second = $this->parseRunIntervalValue($second, 0, 59);
    }

}
