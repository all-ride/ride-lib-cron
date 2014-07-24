<?php

namespace ride\library\cron;

use ride\library\cron\job\CronJob;
use ride\library\cron\job\GenericCronJob;
use ride\library\log\Log;
use ride\library\reflection\Invoker;

use \Exception;

/**
 * Cron to run automated tasks at defined times
 */
class Cron {

    /**
     * Name of the log
     * @var string
     */
    const LOG_NAME = 'cron';

    /**
     * Instance of the call invoker
     * @var \ride\library\reflection\Invoker
     */
    protected $invoker;

    /**
     * Array with the registered jobs
     * @var array
     */
    protected $jobs;

    /**
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

    /**
     * Constructs a new cron instance
     * @return null
     */
    public function __construct(Invoker $invoker) {
        $this->invoker = $invoker;
        $this->jobs = array();
    }

    /**
     * Sets the instance of the log
     * @param \ride\library\log\Log $log
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Gets all the registered jobs
     * @return array Array with CronJob objects
     */
    public function getJobs() {
        return $this->jobs;
    }

    /**
     * Registers a new job with parameters
     * @param callback|\ride\library\reflection\Callback $callback
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $dayOfWeek
     * @return string Id of the job
     */
    public function registerJob($callback, $minute = null, $hour = null, $day = null, $month = null, $dayOfWeek = null) {
        $job = new GenericCronJob($callback, $minute, $hour, $day, $month, $dayOfWeek);

        return $this->registerCronJob($job);
    }

    /**
     * Registers a new job with a object
     * @param \ride\library\cron\job\CronJob $job
     * @return string Id of the job
     * @throws \ride\library\cron\exception\CronException when the provided job
     * id is already in use
     */
    public function registerCronJob(CronJob $job) {
        $id = $job->getId();

        if (isset($this->jobs[$id])) {
            throw new CronException('Could not register the job: id of the job is already in use');
        }

        $this->jobs[$id] = $job;

        return $id;
    }

    /**
     * Removes a job
     * @param string $id Id of the job
     * @return boolean True when the job has been found and removed, false
     * ottherwise
     */
    public function removeJob($id) {
        if (!isset($this->jobs[$id])) {
            return false;
        }

        unset($this->jobs[$id]);

        return true;
    }

    /**
     * Runs the service
     *
     * If no loop provided, this will keep on running until killed unless there are no jobs.
     * @param int $loop number of loops to make (optional)
     * @return null
     */
    public function run($loop = 0) {
        if ($this->log) {
            $this->log->logDebug('Initializing cron', null, self::LOG_NAME);
        }

        if (empty($this->jobs)) {
            if ($this->log) {
                $this->log->logDebug('No jobs registered, returning', null, self::LOG_NAME);
            }

            return;
        }

        $runTime = array();
        $runOrder = $this->getRunOrder(time());

        if ($this->log) {
            $this->log->logDebug('Registered jobs:', null, self::LOG_NAME);

            foreach ($runOrder as $jobId => $nextRunTime) {
                $this->log->logDebug((string) $this->jobs[$jobId], 'next runtime: ' . date('Y-m-d H:i:s', $nextRunTime), self::LOG_NAME);
            }
        }

        $index = 1;
        do {
            if ($loop && $this->log) {
                $this->log->logDebug('Loop ' . $index, null, self::LOG_NAME);
            }

            $sleepTime = 0;
            $executed = false;
            $time = time();

            foreach ($runOrder as $jobId => $nextRunTime) {
                if ($nextRunTime > $time) {
                    if (!$executed) {
                        $sleepTime = $nextRunTime - $time;
                    }

                    break;
                }

                $executed = true;
                $job = $this->jobs[$jobId];
                $jobString = (string) $job;

                $lastRunTime = isset($runTime[$jobId]) ? $runTime[$jobId] : null;

                if ($this->log) {
                    $this->log->logDebug('Executing ' . $jobString, 'last runtime: ' . ($lastRunTime ? date('Y-m-d H:i:s', $lastRunTime) : '---'), self::LOG_NAME);
                }

                try {
                    $this->invoker->invoke($job->getCallback());
                } catch (Exception $exception) {
                    if ($this->log) {
                        $this->log->logException($exception, self::LOG_NAME);
                    }
                }

                $runTime[$jobId] = $time;
                $runOrder[$jobId] = $job->getNextRunTime();

                if ($this->log) {
                    $this->log->logDebug('Done with ' . $jobString, 'next runtime: ' . date('Y-m-d H:i:s', $runOrder[$jobId]), self::LOG_NAME);
                }
            }

            if ($sleepTime) {
                if ($this->log) {
                    $this->log->logDebug('Sleeping ' . $sleepTime . ' seconds', 'Wake up at: ' . date('Y-m-d H:i:s', $time - $sleepTime), self::LOG_NAME);

                }
                sleep($sleepTime);
            } else {
                asort($runOrder);
            }

            $index++;
        } while ($loop == 0 || $index <= $loop);
    }

    /**
     * Gets an array with the run order of the jobs
     * @param int $time the current time in seconds
     * @return array Array with the job id as key and the next run time as value
     */
    private function getRunOrder($time) {
        $runOrder = array();

        foreach ($this->jobs as $jobId => $job) {
            $runOrder[$jobId] = $job->getNextRunTime($time);
        }
        asort($runOrder);

        return $runOrder;
    }

}
