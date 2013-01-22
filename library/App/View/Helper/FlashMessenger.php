<?php

class App_View_Helper_FlashMessenger extends Zend_View_Helper_Abstract {
	public function flashMessenger() {
	    $flashMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
		if (is_array($flashMessenger->getMessages()) && count($flashMessenger->getMessages()) > 0) {
    		$jGrowl = new App_Jgrowl();

    		foreach ($flashMessenger->getMessages() as $msg) {
                if (is_string($msg)) {
                    $jGrowl->addMessage(new App_Jgrowl_Normal($msg));
                } else {
                    $jGrowl->addMessage($msg);
                }
    		}

    		return $jGrowl->getjGrowlCode(false);
	    }
	}
}
