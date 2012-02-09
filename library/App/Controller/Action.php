<?php

class App_Controller_Action extends Zend_Controller_Action {
    /**
     * Start/return event log.
     */
    public function getEventLog($username='none') {
        if (null === $this->_eventLog) {
            $options = array('username'=>$username,
                             'file' => basename(__FILE__));
            $this->_eventLog = new App_Log_Event($options);
        }

        return $this->_eventLog;
    }
}
