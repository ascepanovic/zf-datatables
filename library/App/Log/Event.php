<?php
/**
 * Class to create log files.
 *
 * @example
 *            $options = array('customerId'=>$s->sessCustomerID(),
                             'surveyId'=>$sur_id,
                             'username'=>$s->sessUsername(),
                             'file' => basename(__FILE__),
                             'line' => __LINE__);
            $eventLog = new App_Log_Event($options);
            $eventLog->add('Cleared survey responses.');
 *
 * @author jjohson@cbiz.com
 */

class App_Log_Event  extends Zend_Log {
    protected $_writer = null;
    protected $_username = null;
    protected $_file = null;
    protected $_line = null;

    /**
     * @param array $options customerId, surveyId, username
     * @param null $writer
     */
	public function __construct(array $options, $writer = null) {
        $this->setFile($options['file']);
        $this->setLine((int)$options['line']);

        if (is_null($writer)) {
            $writer = new Zend_Log_Writer_Stream(APPLICATION_PATH.'/logs/event.log');
        }

        // set writer in this class so we can use it again
        $this->setWriter($writer);

        parent::__construct($writer);
	}

    public function getUsername() {
        return $this->_username;
    }

    public function setUsername($in) {
        $this->_username = $in;
    }

    public function getFile() {
        return $this->_file;
    }

    public function setFile($in) {
        $this->_file = $in;
    }

    public function getLine() {
        return $this->_line;
    }

    public function setLine($in) {
        $this->_line = $in;
    }

    public function getWriter() {
        return $this->_writer;
    }

    public function setWriter($writer) {
        $this->_writer = $writer;
    }

    /**
     * When someone makes a change to a survey we log an entry for tracking purposes.
     * This can be used to find who deleted a survey, clear responses, etc.
     *
     * @param string $string
     * @param $type ie Zend_Log::INFO
     * @return void
     */
    public function add($string, $type = Zend_Log::INFO) {
        $this->log($this->getFormattedString($string), $type);
    }

    /**
     * Return formatted string with survey id, username, and string.
     *
     * @param $string
     * @return string
     */
    public function getFormattedString($string) {
        $atoms = array();
        $format = array();

        $file = $this->getFile();
        if ($file) {
            $format[] = '[File: %s]';
            $atoms[] = $file;
        }

        $line = $this->getLine();
        if ($line) {
            $format[] = '[Line %s]';
            $atoms[] = $line;
        }

        $username = $this->getUsername();
        if ($username != '' && $username != 'unknown') {
            $format[] = '(%s)';
            $atoms[] = $username;
        }

        $format[] = '%s';
        $atoms[] = $string;

        return vsprintf(implode(' ',$format),$atoms);
    }
}
