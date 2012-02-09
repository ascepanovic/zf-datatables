<?php
/**
 * Filter string to return a standardized string.
 *
 * @category   App
 * @package    App_Filter
 * @version    $Id$
 */

class App_Filter_Phone implements Zend_Filter_Interface
{
    /**
     * Strip all characters except digits then format phone number as
     * "555-555-1212".  If checks fail the return original string.  You must use
     * a validator to make sure it's a valid phone #.
     *
     * @param string $value
     * @param string $delimiter Defaults to '-'
     * @return string
     */
    public function filter($value,$delimiter='-')
    {
        $filter = new Zend_Filter_Digits();

        $digits = $filter->filter($value);

        $len = strlen($digits);

        /**
         * If this is an 11 digit # then check to see if the first digit is a 1.
         * If so then remove.  Otherwise fail.
         */
        if ($len == 11) {
            $firstDigit = substr($digits,0,1);

            if ($firstDigit == 1) {
                $digits = substr($digits,1);
                
                $len = strlen($digits);
            } else {
                return $digits;
            }
        }


        /**
         * Check to make sure we have a 10 digit number.
         */
        if ($len != 10) {
            return $digits;
        }

        /**
         * Return formatted string.
         */
        $phoneNumber = substr($digits,0,3) . $delimiter . substr($digits,3,3) . $delimiter . substr($digits,6);

        return $phoneNumber;
    }
}
