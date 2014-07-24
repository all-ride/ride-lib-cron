<?php

namespace ride\library\cron;

use \ride\library\log\listener\EchoLogListener;
use \ride\library\log\Log;
use \ride\library\reflection\ReflectionHelper;

use \PHPUnit_Framework_TestCase;

class CronTest extends PHPUnit_Framework_TestCase {

    private $jobCalls;
    private $jobCalls2;

    public function testRun() {
//      $this->markTestSkipped();

        $this->jobCalls = 0;
        $this->jobCalls2 = 0;
        $loop = 8;

        $log = new Log();
        $log->addLogListener(new EchoLogListener());

        $cron = new Cron(new ReflectionHelper());
        $cron->setLog($log);
        $cron->registerJob(array($this, 'jobCallback'));
        $cron->registerJob(array($this, 'jobCallbackWithSleep'), date('i') + 2);

        $cron->run($loop);

        $estimatedCalls = (int) floor($loop / 2);

        $this->assertEquals($estimatedCalls, $this->jobCalls);
        $this->assertEquals(1, $this->jobCalls2);
    }

    public function testRunKeepsRunningWhenJobThrowsException() {
//        $this->markTestSkipped();

        $this->jobCalls = 0;
        $loop = 8;

        $cron = new Cron(new ReflectionHelper());
        $cron->registerJob(array($this, 'jobCallbackWithException'));

        $cron->run($loop);

        $estimatedCalls = (int) floor($loop / 2);

        $this->assertEquals($estimatedCalls, $this->jobCalls);
    }

    public function jobCallback() {
        $this->jobCalls++;
    }

    public function jobCallbackWithSleep() {
        $this->jobCalls2++;
        sleep(5);
    }

    public function jobCallbackWithException() {
        $this->jobCalls++;
        throw new \Exception('Faulty job');
    }

    public function log($title, $description, $level, $log) {
//      echo "\n" . date('Y-m-d H:i:s', time()) . ' - ' . $title . ($description ? ' - ' . $description : '');
    }

}
