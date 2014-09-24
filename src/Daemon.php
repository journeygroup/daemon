<?php

namespace Journey\Daemon;

use Closure;

class Daemon
{
    
    private $micropause = 1000;

    private $controller;

    private $cpuCheckFrequency = 60;

    private $lastCpuCheck = 0;

    private $cpuTarget = 20;

    private $throttleSensitivity = 100;


    /**
     * Instantiate and start the daemon loop
     * @param DaemonControllerInterface $controller controller object
     */
    public function __construct(DaemonControllerInterface $controller)
    {
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
            
            usleep($throttle());
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
            
            if ($load[0] >= $this->cpuTarget) {
                $this->micropause += $this->throttleSensitivity;
                $this->controller->notify('Daemon throttling down');
            } else {
                $this->micropause -= $this->throttleSensitivity;
                $this->controller->notify('Daemon throttling up');
            }

            $this->lastCpuCheck = time();
        }

        // Never allow micropause to dip below 1 microsecond
        if ($this->micropause < 1) {
            $this->micropause = 1;
        }

        return $this->micropause;
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
}
