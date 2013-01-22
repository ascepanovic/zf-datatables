<?php
/**
 * Replace tags in links for datatables.
 *
 * 2012-10-22 Added rootZfUrl to allow getting the baseUrl with no module,controller, or action.
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
            $controller = $request->getControllerName();

            $baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
            $moduleUrl = $baseUrl;
            $controllerUrl = $baseUrl;

            if ($module) {
                $moduleUrl .= '/'.$module;
                $controllerUrl .= '/'.$module;
            }

            if ($controller) {
                $controllerUrl .= '/'.$controller;
            }

            // Remove scriptname, eg. index.php from baseUrl
            $baseUrl = $this->_removeScriptName($baseUrl);
            $moduleUrl = $this->_removeScriptName($moduleUrl);
            $controllerUrl = $this->_removeScriptName($controllerUrl);

            $aSearch = array('{baseUrl}','{moduleUrl}','{controllerUrl}');
            $aReplace = array($baseUrl,$moduleUrl,$controllerUrl);

            if (is_array($id)) {
                foreach ($id as $key=>$item) {
                    if (is_numeric($key)) {
                        $aSearch[] = '{id'.$this->view->escape($key).'}';
                    } else {
                        $aSearch[] = '{'.$this->view->escape($key).'}';
                    }
                    $aReplace[] = $this->view->escape($item);
                }
            } else {
                $aSearch[] = '{id}';
                $aReplace[] = $this->view->escape($id);
            }

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
