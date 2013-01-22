<?php

/**
 * Static jGrowl functions.
 *
 * @author James Johnson <jjohnson@cbiz.com>
 * @package SurveyBiz
 */

class App_Jgrowl
{
    protected $_messages = array();

    /**
     * Array to hold strings for pop ups using a black box.
     * @var array
     */
    protected $_blackBox = array();

    protected $_greenBox = array();
    protected $_redBox = array();

    /**
     * @var App_Filter_EscapeJs
     */
    protected $_filter = null;

    /**
     * Add message class to messages array.
     *
     * @param object App_Jgrowl_xxxx
     */
    public function addMessage($object) {
        $this->_messages[] = $object;
    }

    /**
     * Generate for jGrowl code.
     *
     * @param boolean $pre Add inline css for monospaced message.
     * @return string
     */
    public function getjGrowlCode($pre=false) {
        if ($pre) {
            $out = '
<style type="text/css">
/* <![CDATA[ */
div.jGrowl div.jGrowl-notification, div.jGrowl div.jGrowl-closer {
	white-space:			pre;
//	font-family:			monospace;
	background-color: 		#000;
	color: 					#fff;
	opacity: 				.85;
	filter: 				alpha(opacity = 85);
	zoom: 					1;
	width: 					100%;
}
div.jGrowl div.jGrowl-closer {
	color: 					#000;
}
div.jGrowl div.errorMessage {
    background-color: 		red;
}
div.jGrowl div.successMessage {
    background-color: 		green;
}
/* ]]> */
</style>';
        }

        $messages = null;
        foreach ($this->getMessages() as $object) {
            if (!is_object($object)) {
                if (is_array($object)) {
                    foreach ($object as $item) {
                        $temp = new App_Jgrowl_Normal((string)$item);
                        $messages .= $temp->getJgrowlCode();
                    }
                } else {
                    $object = new App_Jgrowl_Normal((string)$object);
                    $messages .= $object->getJgrowlCode();
                }
            } else {
                $messages .= $object->getJgrowlCode();
            }
        }

        $out .= '<script type="text/javascript">
//<![CDATA
jQuery(document).ready(function(){
    jQuery.jGrowl.defaults.position = \'center\';
    jQuery.jGrowl.defaults.life = 10000;'.$messages.'});//]]></script>';

        return $out;
    }

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

    /**
     * Get messages from message array.
     *
     * @return array
     */
    public function getMessages() {
        return $this->_messages;
    }
}
