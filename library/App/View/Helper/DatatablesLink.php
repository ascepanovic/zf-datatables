<?php
/**
 * Replace tags in links for datatables.
 *
 * @author james
 *
 */
class App_View_Helper_DatatablesLink extends Zend_View_Helper_Abstract {
    /**
     * Replace tags in string.
     *
     * @param mixed $id string to be replace {id}
     * @author james
     * @return void
     */
	public function DatatablesLink($string='', $id='') {
	    if ($string != '') {
	        $request = Zend_Controller_Front::getInstance()->getRequest();
	        $module = $request->getModuleName();
	        $action = $request->getActionName();

            $baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();

	        if ($module) {
	            $baseUrl .= '/'.$module;
	        }

	        // Remove scriptname, eg. index.php from baseUrl
            $baseUrl = $this->_removeScriptName($baseUrl);

            $aSearch = array('{baseUrl}','{id}');
            $aReplace = array($baseUrl,$id);

            return str_replace($aSearch, $aReplace, $string);
	    }
	}

    /**
     * Remove Script filename from baseurl
     *
     * @param  string $url
     * @return string
     */
    protected function _removeScriptName($url)
    {
        if (!isset($_SERVER['SCRIPT_NAME'])) {
            // We can't do much now can we? (Well, we could parse out by ".")
            return $url;
        }

        if (($pos = strripos($url, basename($_SERVER['SCRIPT_NAME']))) !== false) {
            $url = substr($url, 0, $pos);
        }

        return $url;
    }
}
