<?php

namespace ride\library\cron\job;

/**
 * Interface for an automated task
 */
interface CronJob {

    /**
     * Gets a unique id for this job
     * @return string
     */
    public function getId();

    /**
     * Gets the callback of this job
     * @return callback|\ride\library\reflection\Callback
     */
    public function getCallback();

    /**
     * Gets the interval definition
     * @return string
     */
    public function getIntervalDefinition();

    /**
     * Gets the time when this job should run next
     * @param int $time if not provided, the last run time will be used or now if this job hasn't run yet
     * @return integer
     */
    public function getNextRunTime($time = null);

}
