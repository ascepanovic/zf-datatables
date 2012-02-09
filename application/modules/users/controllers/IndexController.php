<?php
/**
 *
 * @author James Johnson
 */

class Users_IndexController extends App_Controller_Datatable {
    /**
     * @see App_Log_Event
     */
    protected $_eventLog = null;

    
    function preDispatch ()
    {
        // your predispatch

        parent::preDispatch();
    }

    public function init() {
        // your init

        parent::init();
    }

    public function datasourceAction ()
    {
        $where = null;
        
        parent::datasourceAction($where);
    }

    public function addAction() {
        $result = parent::addAction();

        // do something extra
        
        if ($result === false || $result > 0) {
            $this->_goback();
        }
    }

    public function deleteAction() {
        $result = parent::deleteAction();

        // do something extra
    }

    public function updateAction() {
        $result = parent::updateAction();

        // do something extra
    }

    public function editAction() {
        $result = parent::editAction();

        // do something extra
    }

    /**
     * Start/return event log.
     */
    public function getEventLog() {
        if (null === $this->_eventLog) {
            $options = array('username'=>'UsersIndexController',
                             'file' => basename(__FILE__));
            $this->_eventLog = new App_Log_Event($options);
        }

        return $this->_eventLog;
    }
}
