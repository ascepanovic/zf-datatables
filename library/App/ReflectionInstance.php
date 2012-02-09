<?php
/** 
 * Used to create instances of classes.  Useful to passing an unspecifed array of arguments.
 * 
 * @author James Johnson
 */
class App_ReflectionInstance
{
    /**
     * Class name used when calling Reflection.
     * 
     * @var string
     */
    protected $_name = null; 

    /**
     * Argments used when calling Reflection.
     * 
     * @var array
     */
    protected $_args = null; 
    
    /**
     * Must call constructor with array of options.
     * class=> Class name
     * args=> Optional arguments
     * 
     * @param array $options
     */
    function __construct (array $options) {
        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        } elseif (!is_array($options)) {
            throw new Zend_Exception ('Invalid options to '.__CLASS__.'::'.__FUNCTION__.'() provided');
        }

        if (isset($options['class'])) {
        	$this->setClass($options['class']);
        } else {
            throw new Zend_Exception ('Invalid options to '.__CLASS__.'::'.__FUNCTION__.'() provided');
        }
        
        if (isset($options['args'])) {
        	$this->setArgs($options['args']);
        }
    } 
    
    function createInstance () { 
        $reflectionClass = new ReflectionClass($this->getClass()); 
        
        if (is_array($this->getArgs()) && count($this->getArgs()) > 0) {
            return $reflectionClass->newInstanceArgs($this->getArgs());
        } else {
            return $reflectionClass->newInstance();
        } 
    }

    /**
     * Set class name to be used.
     * 
     * @param string $name
     */
    public function setClass($name) {
        $this->_name = $name;
    }
    
    /**
     * Get class name to be used.
     * 
     * @return string
     */
    public function getClass() {
        return $this->_name;
    }

    /**
     * Set class name to be used.
     * 
     * @param array $args
     */
    public function setArgs(array $args) {
        $this->_args = $args;
    }
    
    /**
     * Get class name to be used.
     * 
     * @return array
     */
    public function getArgs() {
        return $this->_args;
    }
} 
