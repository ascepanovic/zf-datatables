<?php
/**
 * Validate phone number format.  Must be "555-555-1212".
 *
 * @category  Zend
 * @package   Zend_Validate
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */
class App_Validate_Phone extends Zend_Validate_Abstract
{
    const INVALID = 'phoneInvalid';
    const BAD_LENGTH = 'phoneLength';
    const NOT_PHONE = 'notPhoneNumber';
    const ONLY_DIGITS = 'onlyDigits';

    /**
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID => "Invalid phone number given. Bad Format.",
        self::BAD_LENGTH => "Invalid phone number given. Expected 10 digits (0-9).",
        self::NOT_PHONE => "'%value%' does not appear to be a valid phone number.",
        self::ONLY_DIGITS => "'%value%' does not appear to be a valid phone number.",
    );

    protected $_locale;

    /**
     * Constructor for the integer validator
     *
     * @param string|Zend_Config|Zend_Locale $locale
     */
    public function __construct($locale = null)
    {
        if ($locale instanceof Zend_Config) {
            $locale = $locale->toArray();
        }

        if (is_array($locale)) {
            if (array_key_exists('locale', $locale)) {
                $locale = $locale['locale'];
            } else {
                $locale = null;
            }
        }

        if (empty($locale)) {
            require_once 'Zend/Registry.php';
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                $locale = Zend_Registry::get('Zend_Locale');
            }
        }

        if ($locale !== null) {
            $this->setLocale($locale);
        }
    }

    /**
     * Returns the set locale
     */
    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * Sets the locale to use
     *
     * @param string|Zend_Locale $locale
     */
    public function setLocale($locale = null)
    {
        require_once 'Zend/Locale.php';
        $this->_locale = Zend_Locale::findLocale($locale);
        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value is a valid integer
     *
     * @param  string|integer $value
     * @return boolean
     */
    public function isValid($value)
    {
        //part of zend framework
        $this->_setValue((string) $value);

    	if (!is_string($value)) {
            $this->_error(self::INVALID);
            return false;
        }

        //make sure we have 10 digits and 2 "-"
        $len = strlen($value);
        if($len != 12) {
        	$this->_error(self::BAD_LENGTH);
        	return false;
        }

        $array = explode('-',$value);

        //make sure we have all digits in 3 parts seperated by "-"
        if (!is_numeric($array[0]) || !is_numeric($array[1]) || !is_numeric($array[2])) {
            $this->_error(self::ONLY_DIGITS);
            return false;
        }

        //make sure we have 3 parts seperated by "-"
        if (strlen($array[0]) === 3 && strlen($array[1]) === 3 && strlen($array[2]) === 4) {
            return true;
        }

        $this->_error(self::NOT_PHONE);
        return false;
    }
}
