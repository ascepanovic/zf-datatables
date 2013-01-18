<?php
/**
 * Standard controller to handle jquery datatables.
 *
 * @author James Johnson
 */

abstract class App_Controller_Datatable extends App_Controller_Action {
    /**
     * @see App_Log_Event
     */
    protected $_eventLog = null;

    /**
     * Place holder for join lefts.
     * @var array
     */
    protected $_joinLefts = array();

    public function init ()
    {
    	parent::init();

        /**
         * Setup which actions are ajax actions.
         */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('datasource', 'json')
                    ->setAutoJsonSerialization(false)
                    ->initContext();

        /**
         * Add variables needed in view.
         */
        $this->assignOptions2View();
    }

    public function indexAction ()
    {
        $options = $this->getDatatableOptions();

        /**
         * Load dynamic values for datatable select filter.
         *
         * @example
     	 *	<columnSearch> <!-- Add search box for single column -->
    	 *		<enable>1</enable>
         *  	<type>select</type>
         *  	<name>username_search</name>
         *
         *		<!-- Load dynamic values from this model and column -->
         *		<!-- Needs to return an array with associative index -->
         *  	<loadValues>
         *  		<model>Model_DbTable_Users</model>
         *  		<method>getUserList</method>
         *  	</loadValues>
         *
         *   	<class>search_init</class> <!-- needed for js hooks -->
         *   	<method>like</method> <!-- control PHP search method, like or single -->
         *   </columnSearch>
         */
        foreach ($options['columns'] as $key1=>$item) {
            if ((bool)$item['columnSearch'] && isset($item['columnSearch']['loadValues']['method'])) {
                $model = new $item['columnSearch']['loadValues']['model']();

                $result = $model->$item['columnSearch']['loadValues']['method']();

                $options['columns'][$key1]['columnSearch']['values'] = array();
                foreach ($result as $key2=>$item2) {
                    $options['columns'][$key1]['columnSearch']['values'][] = array('label'=>$item2, 'value'=>$key2);
                }
            }
        }

        if ($options['editable']) {
            $form = new $options['addForm']['class']();

            // set form id so that datatables can find it
            $form->setAttrib('id', 'formAddNewRow');

            $this->view->form = $form;
        }

        $this->assignOptions2View($options);
    }

    /**
     * Get data for ajax call from datatable.
     *
     * @param string $where Additional where for SQL.
     */
    public function datasourceAction ($where = null)
    {
        $options = $this->getDatatableOptions();

        // make datatable option available in view
        $this->assignOptions2View();

    	$model = new $options['dbModel']();

    	$columns = array();
    	$naturalSorts = array();
    	$joinLefts = array();
    	foreach ($options['columns'] as $item) {
    	    /**
    	     * Need blank column when missing since everything is indexed numerically.
    	     */
    	    if (!$item['data']['read']['column']) {
    	        $columns[] = new Zend_Db_Expr("'link'");
    	        $naturalSorts[] = false;
    	    } else {
    	        $columns[] = $this->addTableName2Column($model->getTableName(),$item['data']['read']['column']);
    		    $naturalSorts[] = (bool)$item['naturalSort'];
    	    }

    	    /**
    	     * Make list of left joins.
    	     */
    	    if ($item['data']['joinLeft']['table']) {
    	        if ($item['data']['joinLeft']['as']) {
    	            $table = $item['data']['joinLeft']['table'].' as '.$item['data']['joinLeft']['as'];
    	        } else {
    	            $table = $item['data']['joinLeft']['table'];
    	        }
                $joinLefts[$table] = $item['data']['joinLeft'];
    	    }
    	}

    	$this->setJoinLefts($joinLefts);

    	$select = $model->select()
        		        ->from($model->getTableName(),$columns)
                        ->setIntegrityCheck(false);

        // add left joins
        $select = $this->addJoinLefts($select);

        if ($where != null) {
            $select->where($where);
        }

	    // server side processing
        if ($options['bServerSide'] == "true") {
            // GET params
            $input = $this->getRequest()->getParams();

        	// create similar select statement to get display record count
        	$countDisplaySelect = $model->select()
        		                        ->from($model->getTableName(),'COUNT(*)')
        		                        ->setIntegrityCheck(false);

            // add left joins
            $countDisplaySelect = $this->addJoinLefts($countDisplaySelect);

            if ($where != null) {
                $countDisplaySelect->where($where);
            }

            // searches
            if ($input['sSearch'] != "") {
                $atoms = array();
                foreach ($options['columns'] as $item) {
                    if ((bool)$item['searchable']) {
                        // COLLATE utf8_general_ci used to make search case insensitive
                        $atoms[] = $model->getAdapter()->quoteInto($item['data']['read']['column'].' LIKE ? COLLATE utf8_general_ci', '%'.$input['sSearch'].'%');
                    }
            	}

            	if (count($atoms) > 0) {
                	$select->where(implode(' OR ',$atoms));
                	$countDisplaySelect->where(implode(' OR ',$atoms));
            	}
            }

            // single column searches
            $i = 0;
            foreach ($options['columns'] as $key=>$item) {
                $searchable = (bool)$item['columnSearch']['enable'];
                $string = $input['sSearch_'.$i];

                if ($searchable && $string !== "" && $string !== false && !is_null($string)) {
                    switch ($item['columnSearch']['method']) {
                        case 'like':
                            $select->where($item['data']['read']['column'].' LIKE ? COLLATE utf8_general_ci' ,'%'.$string.'%');
                            $countDisplaySelect->where($item['data']['read']['column'].' LIKE ? COLLATE utf8_general_ci' ,'%'.$string.'%');
                            break;
                        case 'single':
                        default:
                            $select->where($item['data']['read']['column'].' = ?',$string);
                            $countDisplaySelect->where($item['data']['read']['column'].' = ?',$string);
                            break;
                    }
                }

                if (!(bool)$item['hidden']) {
                    $i++;
                }
            }

            // limit records for this response
            $select->limit($input['iDisplayLength'], $input['iDisplayStart']);

            // get total record count before filtering
            $totalRecordSelect = $model->select()
                                       ->from($model->getTableName(),'COUNT(*)')
                                       ->setIntegrityCheck(false);

            // add left joins
            $totalRecordSelect = $this->addJoinLefts($totalRecordSelect);

            if ($where != null) {
                $totalRecordSelect->where($where);
            }

            $this->view->iTotalRecords = (int) $model->getAdapter()->fetchOne($totalRecordSelect);

            // get display record count and add to view
            $this->view->iTotalDisplayRecords = (int) $model->getAdapter()->fetchOne($countDisplaySelect);

            // An unaltered copy of sEcho sent from the client side. This parameter will change with each draw (it is basically a draw count) - so it is important that this is implemented. Note that it strongly recommended for security reasons that you 'cast' this parameter to an integer in order to prevent Cross Site Scripting (XSS) attacks.
            $this->view->sEcho = (int) $input['sEcho'];
        }

        if ($input['iSortingCols']) {
            $sortColumn = $columns[(int)$input['iSortCol_0']];
            $naturalSort = $naturalSorts[(int)$input['iSortCol_0']];

            switch (strtolower($input['sSortDir_0'])) {
                case 'asc':
                    $sortDirection = 'ASC';
                    break;
                default:
                    $sortDirection = 'DESC';
                    break;
            }

            if ($naturalSort) {
                // hack to make MySQL naturally sort numeric columns
                $select->order('LENGTH('.$sortColumn.') '.$sortDirection.', '.$sortColumn.' '.$sortDirection);
            } else {
                $select->order($sortColumn.' '.$sortDirection);
            }
        } else {
            foreach ($options['columns'] as $key=>$item) {
                if ($item['isSortDefault'] == "true" || $item['isSortDefault'] == 1) {
                    $sortColumn = $item['data']['read']['column'];
                    $sortDirection = 'ASC';

                    if ((bool)$item['naturalSort']) {
                        // hack to make MySQL naturally sort numeric columns
                        $select->order('LENGTH('.$sortColumn.') '.$sortDirection.', '.$sortColumn.' '.$sortDirection);
                    } else {
                        $select->order($sortColumn.' '.$sortDirection);
                    }
                }
            }
        }

        // Datatable plugins do not like tables based on a non-numeric index so
        // we tell the db to give us a numeric index.
        $model->getAdapter()->setFetchMode(Zend_Db::FETCH_ASSOC);

		$stmt = $model->getAdapter()->query($select);
        $stmt->setFetchMode(Zend_Db::FETCH_NUM);

		$this->view->stmt = $stmt;

	    // server side processing
        if ($options['bServerSide'] == "true") {
            $input = $this->getRequest()->getParams();

            $select->limit($input['iDisplayLength'], $input['iDisplayStart']);
        }

    }

    /**
     * Remove record from database.
     */
    public function deleteAction ()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        $options = $this->getDatatableOptions();
        $idColumn = $options['columns'][0]['data']['write']['column'];

    	$model = new $options['dbModel']();

    	$aTmp = $this->getSearchReplaceArrays($model->getTableName(),null,$idColumn,(int)$this->getRequest()->$idColumn);
    	$search = $aTmp['search'];
    	$replace = $aTmp['replace'];

	    $where = $model->getAdapter()->quoteInto($idColumn.' = ?', (int)$this->getRequest()->$idColumn);

	    if ($options['delete']['where'] != "") {
	        $where .= ' and ' . str_replace($search,$replace,$options['delete']['where']);
	    }

        $result = $model->$options['delete']['method']($where);

        if ($result) {
            $eventLog = $this->getEventLog();
            $eventLog->setLine(__LINE__);
            $eventLog->add((string)$options['delete']['success']);
        } else {
            $eventLog = $this->getEventLog();
            $eventLog->setLine(__LINE__);
            $eventLog->add((string)$options['delete']['error']);
        }

    	if ($options['editable'] != 1) {
            $flashMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
            if ($result) {
                $flashMessenger->addMessage((string)$options['delete']['success']);
            } else {
                $flashMessenger->addMessage((string)$options['delete']['error']);
            }

    	    $this->_goback();
    	}

    	if (!$result) {
    	    echo 'Failed to remove record.';
    	    return false;
    	}

    	echo 'ok';
    	return true;
    }

    /**
     * Add record to database.
     */
    public function addAction ()
    {
        $options = $this->getDatatableOptions();
        $idColumn = $options['columns'][0]['data']['write']['column'];

        if ($this->getRequest()->isPost()) {
            //grab post data
            $data = $this->getRequest()->getPost();

            $form = new $options['addForm']['class']();
            $model = new $options['dbModel']();

            if ($options['addForm']['useFormFiltersOnly'] != 1) {
                /**
                 * Find and use validators found in xml config.
                 * 
                 * Key (ie column name) must exists in xml config or no filter or validator
                 * will be used.
                 */
                foreach ($data as $key=>$item) {
                	$columnData = false;
                	foreach ((array)$options['columns'] as $item) {
                	    if ($item['data']['write']['column'] === $key) {
                            $columnData = $item;
                	    }
                	}
    
                	if (isset($columnData['zendFilter']) && $columnData['zendFilter']) {
            	    	$aTmp = $this->getSearchReplaceArrays($model->getTableName(),$columnData['data']['write']['column'],$idColumn,(int)$data[$idColumn]);
                    	$search = $aTmp['search'];
                    	$replace = $aTmp['replace'];
    
                	    if (isset($columnData['zendFilter']['name'])) {
                	        $filters = array($columnData['zendFilter']);
                	    } else {
                	        $filters = $columnData['zendFilter'];
                	    }

                	    $form = $this->addFiltersToForm($form,$filters,$key,$search,$replace);
                	}
                }
            }
            
            if ($options['addForm']['useFormValidatorsOnly'] != 1) {
                /**
                 * Find and use validators found in xml config.
                 * 
                 * Key (ie column name) must exists in xml config or no filter or validator
                 * will be used.
                 */
                foreach ($data as $key=>$item) {
                	$columnData = false;
                	foreach ((array)$options['columns'] as $item) {
                	    if ($item['data']['write']['column'] === $key) {
                            $columnData = $item;
                	    }
                	}
    
                	if (isset($columnData['zendValidate']) && $columnData['zendValidate']) {
            	    	$aTmp = $this->getSearchReplaceArrays($model->getTableName(),$columnData['data']['write']['column'],$idColumn,(int)$data[$idColumn]);
                    	$search = $aTmp['search'];
                    	$replace = $aTmp['replace'];
    
                	    if (isset($columnData['zendValidate']['name'])) {
                	        $validators = array($columnData['zendValidate']);
                	    } else {
                	        $validators = $columnData['zendValidate'];
                	    }
    
                	    $form = $this->addValidatorsToForm($form,$validators,$key,$search,$replace);
                	}
                }
            }

            if ($data['goback'] !== 'Cancel' && $form->isValid($data)) {
                // add new record
                unset($data[$idColumn],$data['submit']);

                $result = $model->$options['addForm']['method']($data);

                if ($result) {
                    $eventLog = $this->getEventLog();
                    $eventLog->setLine(__LINE__);
                    $eventLog->add((string)$options['addForm']['success']);
                } else {
                    $eventLog = $this->getEventLog();
                    $eventLog->setLine(__LINE__);
                    $eventLog->add((string)$options['addForm']['error']);
                }

                $flashMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
                if ($result) {
                    $flashMessenger->addMessage((string)$options['addForm']['success']);

                    return $result;
                } else {
                    $flashMessenger->addMessage((string)$options['addForm']['error']);

                    return $result;
                }
            }
        } else if ($data['goback'] !== 'Cancel')  {
            $model = new $options['dbModel']();

            $form = new $options['addForm']['class']();
        }

        if ($data['goback'] === 'Cancel') {
            return false;
        }

        $this->view->form = $form;
    }

    /**
     * Update record in database.
     */
    public function updateAction ()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        $options = $this->getDatatableOptions();
        $idColumn = $options['columns'][0]['data']['read']['column'];

        try {
            if ($options['editable'] == 1) {
                // make datatable option available in view
                $options = $this->getDatatableOptions();

                // GET params
                $input = $this->getRequest();
                $id = $input->id;
                $value = $originalValue = $input->value;

            	$columnData = false;
            	foreach ($options['columns'] as $item) {
            	    if ($item['label'] === $input->columnName) {
                        $columnData = $item;
            	    }
            	}

            	// unable to find requested column?
            	if (!$columnData) {
                    $this->getResponse()
                         ->setHttpResponseCode(500)
                         ->appendBody("Unable to locate database.\n");
            	}

            	// is this a select box?  verify incoming data
            	$allowedResponse = false;
            	if ($columnData['allowedResponse']) {
            	    $allowedResponse = json_decode($columnData['allowedResponse']);
            	}

            	if ($allowedResponse) {
            	    if ($allowedResponse->$value){
            	        //
            	    } else {
                        $this->getResponse()
                             ->setHttpResponseCode(500)
                             ->appendBody("Unable to locate response.\n");
            	    }
            	}

            	$model = new $options['dbModel']();

    	    	$aTmp = $this->getSearchReplaceArrays($model->getTableName(),$columnData['data']['write']['column'],$idColumn,$id);
            	$search = $aTmp['search'];
            	$replace = $aTmp['replace'];

            	// run response through a filter?
            	if (isset($columnData['zendFilter']) && $columnData['zendFilter']) {
            	    if (isset($columnData['zendFilter']['name'])) {
            	        $filters = array($columnData['zendFilter']);
            	    } else {
            	        $filters = $columnData['zendFilter'];
            	    }
            	    
            	    $value = $this->filterData($value,(array)$filters,$search,$replace);
            	}

            	// validate response?
            	if (isset($columnData['zendValidate']) && $columnData['zendValidate']) {
            	    if (isset($columnData['zendValidate']['name'])) {
            	        $validators = array($columnData['zendValidate']);
            	    } else {
            	        $validators = $columnData['zendValidate'];
            	    }

            	    foreach ((array)$validators as $item) {
                        $validator = $this->getValidator($item,$search,$replace);
            	        $result = $validator->isValid($value);

            	        if (!$result) {
            	            // result is invalid; print the reasons
                            foreach ($validator->getMessages() as $message) {
                                echo $message.PHP_EOL;
                            }
            	            return false;
            	        }
            	    }
            	}

                $data[$columnData['data']['write']['column']] = $value;

            	$where = $model->getAdapter()->quoteInto($idColumn.' = ?', $id);

        	    if ($columnData['data']['write']['where'] != "") {
        	        $where .= ' and ' . str_replace($search,$replace,$columnData['data']['write']['where']);
        	    }

        	    // datatable will check to see if returned value is the same as the send value
        	    // if not then it sets an alert so we load the old value and echo if it's the
        	    // same preventing the alert.
            	$rows = $model->fetchRow($where);

            	if ($rows->$columnData['data']['write']['column'] === $value) {
            	    echo $originalValue;
            	    return false;
            	}

            	$result = $model->update($data, $where);
            }

        	if (!$result && !is_null($result)) {
        	    echo 'Failed to update record.';

        	    return false;
        	} else {
        	    // datatable will check to see if returned value is the same as the send value
        	    // if not then it sets an alert
        	    echo $originalValue;

        	    return true;
        	}
        } catch (Exception $e) {
            if (APPLICATION_ENV === 'development') {
                echo $e->getMessage();
            }
            echo 'Failed to update record.';

            return false;
        }
    }

    /**
     * Edit record in database.
     */
    public function editAction ()
    {
        $options = $this->getDatatableOptions();
        $idColumn = $options['columns'][0]['data']['read']['column'];

        if ($this->getRequest()->isPost()) {
            //grab post data
            $data = $this->getRequest()->getPost();
            $model = new $options['dbModel']();

            $select = $model->select()
        	    	        ->from($model->getTableName())
        	    	        ->where($idColumn.' = ?',$data[$idColumn]);

            $stmt = $model->getAdapter()->query($select);
            $row = $stmt->fetch();

            $form = new $options['editForm']['class'](array('idColumn'=>$idColumn,'id'=>$row['id']));

            /**
             * Find and use validators found in xml config.
             */
            foreach ($data as $key=>$item) {
            	$columnData = false;
            	foreach ((array)$options['columns'] as $item) {
            	    if ($item['data']['write']['column'] === $key) {
                        $columnData = $item;
            	    }
            	}

            	if (isset($columnData['zendValidate']) && $columnData['zendValidate']) {
        	    	$aTmp = $this->getSearchReplaceArrays($model->getTableName(),$columnData['data']['write']['column'],$idColumn,(int)$data[$idColumn]);
                	$search = $aTmp['search'];
                	$replace = $aTmp['replace'];

            	    if (isset($columnData['zendValidate']['name'])) {
            	        $validators = array($columnData['zendValidate']);
            	    } else {
            	        $validators = $columnData['zendValidate'];
            	    }

            	    $form = $this->addValidatorsToForm($form,$validators,$key,$search,$replace);
            	}
            }

            if ($data['goback'] !== 'Cancel' && $form->isValid($data)) {
                // update data in table
                $where = $model->getAdapter()->quoteInto($idColumn.' = ?',$data[$idColumn]);

            	$aTmp = $this->getSearchReplaceArrays($model->getTableName(),'',$idColumn,(int)$data[$idColumn]);
            	$search = $aTmp['search'];
            	$replace = $aTmp['replace'];

        	    if ($options['edit']['where'] != "") {
        	        $where .= ' and ' . str_replace($search,$replace,$options['edit']['where']);
        	    }

                unset($data[$idColumn],$data['submit']);

                $result = $model->$options['editForm']['method']($data,$where);

                if ($result) {
                    $eventLog = $this->getEventLog();
                    $eventLog->setLine(__LINE__);
                    $eventLog->add((string)$options['editForm']['success']);
                } else {
                    $eventLog = $this->getEventLog();
                    $eventLog->setLine(__LINE__);
                    $eventLog->add((string)$options['editForm']['error']);
                }

                $flashMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
                if ($result) {
                    $flashMessenger->addMessage((string)$options['editForm']['success']);
                } else {
                    $flashMessenger->addMessage((string)$options['editForm']['error']);
                }

                $this->_goback();
            }
        } else if ($data['goback'] !== 'Cancel')  {
            $id = $this->getRequest()->getParam($idColumn);

            $model = new $options['dbModel']();

            $select = $model->select()
        	    	        ->from($model->getTableName())
        	    	        ->where($idColumn.' = ?',$id);

            $stmt = $model->getAdapter()->query($select);
            $row = $stmt->fetch();

            $form = new $options['editForm']['class'](array('idColumn'=>$idColumn,'id'=>$row['id']));
            $form->populate($row);
        }

        if ($data['goback'] === 'Cancel') {
            $this->_goback();
        }

        $this->view->form = $form;
    }

    protected function _goback() {
        if (Zend_Controller_Front::getInstance()->getRequest()->getModuleName() === 'playground') {
            $front = Zend_Controller_Front::getInstance();
            $front->setBaseUrl('');
        }

        $this->_helper->redirector->gotoSimple('index',
                                               $this->getRequest()->getControllerName(),
                                               $this->getRequest()->getModuleName());
    }

    /**
     * Options to use for datatable instance.
     *
     * @author james
     * @see App_Datatable::setXXXX
     * @return array
     */
    public function getDatatableOptions () {
        $controller = $this->getRequest()->getControllerName();
        $options = new Zend_Config_Xml($this->getFrontController()->getModuleDirectory().'/configs/'.$controller.'.xml', APPLICATION_ENV);

        return $options->toArray();
    }

    /**
     * Assign datatable options so we can use them in the view.
     *
     * @author james
     * @return void
     */
    protected function assignOptions2View($options = null) {
        if (!is_null($options)) {
            $this->view->datatableOptions = $options;
        } else {
            $this->view->datatableOptions = $this->getDatatableOptions();
        }

        $this->view->controller = $this->getRequest()->getControllerName();
        $this->view->module = $this->getRequest()->getModuleName();
    }

    /**
     * Build search and replace array sets.
     *
     * @param string $tableName
     * @param string $writeColumn
     * @param string $idColumn
     * @param int $id
     */
    public function getSearchReplaceArrays($tableName,$writeColumn,$idColumn,$id) {
        $search = array('__TABLE__','__DATACOLUMN__','__IDCOLUMN__','__IDVALUE__');
        $replace = array($tableName,$writeColumn,$idColumn,(int)$id);

        return array('search'=>$search,'replace'=>$replace);
    }

    /**
     * Add validators stored in xml config to zend form.
     *
     * @param object $form Form object
     * @param array $filters List of filters
     * @param string $element Element of form to use.
     * @param array $search Search array for parameter replacements
     * @param array $replace Replace array for parameter replacements
     */
    protected function addFiltersToForm($form,array $filters=array(),$element,array $search=array(),array $replace=array()) {
	    foreach ((array)$filters as $item) {
	        $result = json_decode($item['arguments']);
	        if (!$result) {
	            $params = $item['arguments'];
	        } else {
	            $params = (array)$result;
	        }

            /**
             * Convert objects to arrays and replace strings so validators
             * can be templated.
             */
            if (is_array($params) && count($params) > 0) {
	            foreach ($params as $key2=>$item2) {
    	            if (is_object($item2)) {
    	                $array = array();
    	                foreach ((array)$item2 as $key3=>$item3) {
    	                    $array[$key3] = str_replace($search, $replace, $item3);
    	                }
	                    $params[$key2] = $array;
    	            } else {
	                    $params[$key2] = str_replace($search, $replace, $item2);

	                }
    	        }
	        }

	        // Create validator instance using params from config.
            $filterOptions = array('class'=>$item['name']);
	        if (is_array($params)) {
	            $filterOptions['args'] = $params;
	        }

	        $reflection = new App_ReflectionInstance($filterOptions);
            $filter = $reflection->createInstance();

            $form->$element->addFilter($filter);
	    }

        return $form;
    }
    
    /**
     * Add validators stored in xml config to zend form.
     *
     * @param object $form Form object
     * @param array $validators List of validators
     * @param string $element Element of form to use.
     * @param array $search Search array for parameter replacements
     * @param array $replace Replace array for parameter replacements
     */
    protected function addValidatorsToForm($form,array $validators=array(),$element,array $search=array(),array $replace=array()) {
	    foreach ((array)$validators as $item) {
	        $result = json_decode($item['arguments']);
	        if (!$result) {
	            $params = $item['arguments'];
	        } else {
	            $params = (array)$result;
	        }

            /**
             * Convert objects to arrays and replace strings so validators
             * can be templated.
             */
            if (is_array($params) && count($params) > 0) {
	            foreach ($params as $key2=>$item2) {
    	            if (is_object($item2)) {
    	                $array = array();
    	                foreach ((array)$item2 as $key3=>$item3) {
    	                    $array[$key3] = str_replace($search, $replace, $item3);
    	                }
	                    $params[$key2] = $array;
    	            } else {
	                    $params[$key2] = str_replace($search, $replace, $item2);

	                }
    	        }
	        }

	        // Create validator instance using params from config.
            $validateOptions = array('class'=>$item['name']);
	        if (is_array($params)) {
	            $validateOptions['args'] = $params;
	        }
	        $reflection = new App_ReflectionInstance($validateOptions);
            $validator = $reflection->createInstance();

            $form->$element->addValidator($validator);
	    }

        return $form;
    }

    /**
	 * Add left joins to select statement for App_Controller_Datatable::datasourceAction().
	 *
	 * @see App_Controller_Datatable::datasourceAction()
	 * @param Zend_Db_Select $select Select statement.
     */
    protected function addJoinLefts(Zend_Db_Select $select) {
        // add left joins
        if (count($this->getJoinLefts()) > 0) {
            foreach ($this->getJoinLefts() as $table=>$item) {
                $select->joinLeft($table,(string)$item['condition'],(array)$item['column']);
            }
        }

        return $select;
    }

    /**
     * Set list of join lefts.
     *
     * @param array $array
     */
    protected function setJoinLefts(array $array) {
        return $this->_joinLefts = $array;
    }

    /**
     * Get list of join lefts.
     *
     * @return array
     */
    protected function getJoinLefts() {
        return $this->_joinLefts;
    }

    /**
     * Determine if column is joined with another table.
     *
     * @param string $column
     * @return boolean
     */
    public function columnIsJoined($column) {
        if (strpos($column,'.') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Prepend table table to column name.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     */
    public function addTableName2Column($table,$column) {
        if ($this->columnIsJoined($column) || strpos($column,'(') !== false) {
            // already joined to a table
            return $column;
        }

        return $table.'.'.$column;
    }

    public function validateaddformAction() {
        $this->_helper->viewRenderer->setNoRender();

        $options = $this->getDatatableOptions();
        $form = new $options['addForm']['class']();
        $form->isValid($this->_getAllParams());
        $json = $form->getMessages();

        header('Content-type: application/json');

        echo Zend_Json::encode($json);
    }
    
    /**
     * Filter string using Zend Filters
     * 
     * @param string $value Value to filter
     * @param array $filters Array of filters to use with arguments. (name=>Filter Name, arguments=>json encoded arguments
     * @param array $search Search array for parameter replacements
     * @param array $replace Replace array for parameter replacements
     * @return string
     */
    protected function filterData($value = null,array $filters = array(),array $search = array(),array $replace = array()) {
	    foreach ((array)$filters as $item) {
            $result = json_decode($item['arguments']);
            if (!$result) {
                $params = $item['arguments'];
            } else {
                $params = (array)$result;
            }
    
            /**
             * Convert objects to arrays and replace strings so validators
             * can be templated.
             */
            if (is_array($params) && count($params) > 0) {
                foreach ($params as $key=>$item2) {
    	            if (is_object($item2)) {
    	                $array = array();
    	                foreach ((array)$item2 as $key3=>$item3) {
    	                    $array[$key3] = str_replace($search, $replace, $item3);
    	                }
                        $params[$key] = $array;
    	            } else {
                        $params[$key] = str_replace($search, $replace, $item2);
    
                    }
    	        }
            }
            
            // Create validator instance using params from config.
            $validateOptions = array('class'=>$item['name']);
            if (is_array($params)) {
                $validateOptions['args'] = $params;
            }
            
            $reflection = new App_ReflectionInstance($validateOptions);
            $filter = $reflection->createInstance();
            $value = $filter->filter($value);
        }
        
        return $value;
    }

    /**
     * Filter string using Zend Filters
     * 
     * @param array $validatorOptions Validator options ie arguments and name
     * @param array $search Search array for parameter replacements
     * @param array $replace Replace array for parameter replacements
     * @return string
     */
    protected function getValidator(array $validatorOptions = array(),array $search = array(),array $replace = array()) {
        $result = json_decode($validatorOptions['arguments']);
        if (!$result) {
            $params = $validatorOptions['arguments'];
        } else {
            $params = (array)$result;
        }

        /**
         * Convert objects to arrays and replace strings so validators
         * can be templated.
         */
        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key=>$item2) {
	            if (is_object($item2)) {
	                $array = array();
	                foreach ((array)$item2 as $key3=>$item3) {
	                    $array[$key3] = str_replace($search, $replace, $item3);
	                }
                    $params[$key] = $array;
	            } else {
                    $params[$key] = str_replace($search, $replace, $item2);

                }
	        }
        }

        // Create validator instance using params from config.
        $options = array('class'=>$validatorOptions['name']);
        if (is_array($params)) {
            $options['args'] = $params;
        }
        $reflection = new App_ReflectionInstance($options);
        $validator = $reflection->createInstance();
                
        return $validator;
    }
}
