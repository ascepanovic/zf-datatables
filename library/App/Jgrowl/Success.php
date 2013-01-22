<?php
/**
 * Created by JetBrains PhpStorm.
 * User: webdev
 * Date: 6/17/11
 * Time: 10:24 AM
 * To change this template use File | Settings | File Templates.
 */

class App_Jgrowl_Success extends App_Jgrowl_Common {
    public function getJgrowlCode() {
        $out = null;

        foreach ($this->getMessages() as $string) {
            $out .= 'jQuery.jGrowl(\''.$this->getFilter()->filter($string).'\',{ life: 10000, theme: \'successMessage\' });';
        }

        return $out;
    }
}
