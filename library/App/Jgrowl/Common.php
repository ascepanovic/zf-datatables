<?php
/**
 * Created by JetBrains PhpStorm.
 * User: webdev
 * Date: 6/17/11
 * Time: 10:51 AM
 * To change this template use File | Settings | File Templates.
 */
 
abstract class App_Jgrowl_Common {
    /**
     * @var array
     */
    protected $_messages = array();

    /**
     * @var App_Filter_EscapeJs
     */
    protected $_filter = null;

    public function __construct($message=null) {
        if (!is_nulL($message)) {
            $this->addMessage($message);
        }
    }

    /**
     * Add message to message array.
     *
     * @param  $message
     * @return void
     */
    public function addMessage($message) {
        if (is_array($message)) {
            $this->_messages[] = implode(PHP_EOL,$message);
        } else {
            $this->_messages[] = $message;
        }
    }

    /**
     * Get messages from message array.
     *
     * @return array
     */
    public function getMessages() {
        return $this->_messages;
    }

    /**
     * Get jGrowl code.
     *
     * @return string
     */
    abstract public function getJgrowlCode();

    /**
     * Get javascript escape filter.
     *
     * @return App_Filter_EscapeJs|null
     */
    public function getFilter() {
        if ($this->_filter === null) {
            $this->_filter = new App_Filter_EscapeJs();
        }

        return $this->_filter;
    }
}
