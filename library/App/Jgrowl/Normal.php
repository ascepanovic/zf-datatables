<?php
/**
 * Created by JetBrains PhpStorm.
 * User: webdev
 * Date: 6/17/11
 * Time: 10:34 AM
 * To change this template use File | Settings | File Templates.
 */
 
class App_Jgrowl_Normal extends App_Jgrowl_Common {
    public function getJgrowlCode() {
        $out = null;

        foreach ($this->getMessages() as $string) {
            $out .= 'jQuery.jGrowl(\''.$this->getFilter()->filter($string).'\',{ sticky: true });';
        }

        return $out;
    }
}
