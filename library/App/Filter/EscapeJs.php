<?php
/**
 * CNS
 *
 * @category   App
 * @package    App_Filter
 * @version    $Id$
 */

class App_Filter_EscapeJs implements Zend_Filter_Interface
{
    /**
     * Returns the string $value, converting characters to their corresponding HTML entity
     * equivalents where they exist.  Also escape's single and double quotes for javascript
     * arguments.
     *
     * @param  string $value
     * @return string
     */
    public function filter($value)
    {
        $string = Zend_Filter::filterStatic($value, 'HtmlEntities');

        return strtr($string, array('\\'=>'\\\\',"'"=>"\\'",'"'=>'\\"',"\r"=>'\\r',"\n"=>'\\n','</'=>'<\/'));
    }
}
