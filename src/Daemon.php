<?php

namespace Journey\Daemon;

use Closure;

class Daemon
{
    
    private $pause = 100000;

    private $controller;

    private $cpuCheckFrequency = 60;

    private $lastCpuCheck = 0;

    private $cpuTarget = 20;

    private $cpuCount = 1;

    private $throttleSensitivity = 1000;


    /**
     * Instantiate and start the daemon loop
     * @param DaemonControllerInterface $controller controller object
     */
    public function __construct(DaemonControllerInterface $controller)
    {
        $this->setCpuCores();
        $this->controller = $controller;
        $this->controller->setDaemon($this);
        $this->lastCpuCheck = time();
        $this->loop();
    }



    /**
     * Execute the primary daemon runtime loop
     * @return none
     */
    private function loop()
    {
        $c = $this->controller;

        if (is_callable([$c, 'throttle'])) {
            $throttle = function () use ($c) {
                return $c->throttle();
            };
        } else {
            $throttle = function () {
                return $this->throttle();
            };
        }

        // The entire runtime loop
        while ($c->signal()) {

            $c->process();
            
            time_nanosleep(0, $throttle());
        }
    }



    /**
     * Determine the throttle execution time, available to the public
     * @return integer   microseconds
     */
    public function throttle()
    {
        if ((time() - $this->lastCpuCheck) >= $this->cpuCheckFrequency) {
            $load = sys_getloadavg();
            
            if ($load[0]/$this->cpuCount >= $this->cpuTarget) {
                $this->pause += $this->throttleSensitivity;
                $this->controller->notify('Daemon throttling down');
            } else {
                $this->pause -= $this->throttleSensitivity;
                $this->controller->notify('Daemon throttling up');
            }

            $this->lastCpuCheck = time();
        }

        // Never allow pause to dip below 1 microsecond
        if ($this->pause < 1) {
            $this->pause = 1;
        }

        return $this->pause;
    }



    /**
     * Set the target cpu usage for the system. The daemon will attempt to keep this percentage
     * @param Integer $percent [description]
     */
    public function setCpuTarget($percent)
    {
        $this->cpuTarget = $percent;
    }



    /**
     * Set the micro time adjustment
     * @param Integer $microseconds   number of microseconds by which we change the throttle
     */
    public function setThrottleSensitivity($microseconds)
    {
        $this->throttleSensitivity = $microseconds;
    }



    /**
     * Set how frequency the cpu usage should be checked for throttling
     * @param Integer $seconds  number of seconds between polls
     */
    public function setCpuCheckFrequency($seconds)
    {
        $this->cpuCheckFrequency = $seconds;
    }


    /**
     * Attempts to read the number of cpu cores and set it internally
     */
    public function setCpuCores()
    {
        $numCpus = 1;
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $numCpus = count($matches[0]);
        } else if ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if (false !== $process) {
                fgets($process);
                $numCpus = intval(fgets($process));
                pclose($process);
            }
        } else {
            $process = @popen('sysctl -a', 'rb');
            if (false !== $process) {
                $output = stream_get_contents($process);
                preg_match('/hw.ncpu: (\d+)/', $output, $matches);
                if ($matches) {
                    $numCpus = intval($matches[1][0]);
                }
                pclose($process);
            }
        }
        $this->cpuCount = $numCpus;
    }
}
