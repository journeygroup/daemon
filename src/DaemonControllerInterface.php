<?php

namespace Journey\Daemon;

interface DaemonControllerInterface
{
    /**
     * Used before the daemon begins its runtime loop to give the controller an opportunity to know its parent instance
     * @param Daemon $daemon  The daemon object
     */
    public function setDaemon(Daemon $daemon);


    /**
     * Internal signal/semaphor used to speaking to the daemon process
     * @return Mixed   truthy values continue execution, falsy values terminate
     */
    public function signal();


    /**
     * Receives and processes notifications from the daemon process
     * @return 
     */
    public function notify($message);


    /**
     * Receives and processes any and all notifications
     * @return none
     */
    public function process();
}
