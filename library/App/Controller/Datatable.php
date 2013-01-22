<?php
/**
 * Standard controller to handle jquery datatables.
 *
 * @author James Johnson
 */

abstract class App_Controller_Datatable extends App_Controller_Action {
    /**
     * Datatables class
     */
    protected $_datatableClass = null;

    /**
     * Session namespace name.
     */
    protected $_namespace = 'datatable';

    /**
     * @see App_Session_Namespace
     */
    protected $_appNamespaces = null;

    /**
     * @see App_Log_Event
     */
    protected $_eventLog = null;

    /**
     * @see App_DbTable
     */
    protected $_dbModel = null;

    /**
     * Place holder for join lefts.
     * @var array
     */
    protected $_joinLefts = array();

    /**
     * Options for db table model
     * @var null
     */
    protected $_dbModelOptions = null;

    /**
     * Params used when redirecting user.
     * @var null
     */
    protected $_goBackOptions = array();

    function preDispatch ()
    {
        //
    }

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

        /**
         * Enable profilter on development machines.
         */
        if ('development' === APPLICATION_ENV) {
            $db = $this->getDbModel()->getAdapter();

            // Instantiate the profiler in your bootstrap file
            $profiler = new Zend_Db_Profiler_Firebug('Datatable Queries:');
            // Enable it
            $profiler->setEnabled(true);
            // Attach the profiler to your db adapter
            $db->setProfiler($profiler);
        }
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

//        if ($options['editable']) {
//            $form = new $options['addForm']['class']();
//
//            // set form id so that datatables can find it
//            $form->setAttrib('id', 'formAddNewRow');
//
//            $this->view->form = $form;
//        }

        $this->assignOptions2View($options);
    }

    /**
     * Get data for ajax call from datatable.
     *
     * @param string $where Additional where for SQL.
     */
    public function datasourceAction ($where = null, Zend_Db_Table_Select $select = null)
    {
        if ($this->hasResourceLayout()) {
            $this->_helper->layout->disableLayout();
        }

        $options = $this->getDatatableOptions();
    	$columnMetadata = $this->getColumnMetadata();

        // make datatable option available in view
        $this->assignOptions2View();

        $result = $select instanceof Zend_Db_Table_Select;
        if (!$result) {
            $select = $this->buildSelect($where,$this->getRequest()->getParams());
        }

	    // server side processing
        if ($options['bServerSide'] == "true") {
            if (isset($options['dbModel']['distinct']) && $options['dbModel']['distinct'] == "1") {
                $primaryColumn = 'DISTINCT '.$this->getDbPrimaryKey(true);
            } else {
                $primaryColumn = $this->getDbPrimaryKey(true);
            }
            /**
             * Determine if select has an group by
             */
            $currentGroup = $select->getPart('group');
            if (count($currentGroup) > 0) {
                $hasCurrentGroup = true;
            } else {
                $hasCurrentGroup = false;
            }

            $model = $this->getDbModel();
            // get total record count before filtering
            $totalRecordSelect = clone $select;
            $totalRecordSelect->reset(Zend_Db_Select::COLUMNS)
                              ->reset(Zend_Db_Select::WHERE)
                              ->columns("COUNT($primaryColumn)")
                              ->reset(Zend_Db_Select::ORDER)
                              ->reset(Zend_Db_Select::LIMIT_COUNT)
                              ->reset(Zend_Db_Select::LIMIT_OFFSET)
                              ->reset(Zend_Db_Select::GROUP);
//                              ->limit(1);
            if (isset($options['dbModel']['distinct']) && $options['dbModel']['distinct'] == "1") {
                $totalRecordSelect->distinct(true);
            }
            if ($where != null) {
                $totalRecordSelect->where($where);
            }

            try {
                // use a different select statement if using a group by
                if ($hasCurrentGroup) {
                    $totalRecordSelect->group($currentGroup);
                    $totalRecordSelect = 'select count(*) from ('.$totalRecordSelect->__toString().') as temp';
                } else {
                    $totalRecordSelect->limit(1);
                }
                $this->view->iTotalRecords = (int) $model->getAdapter()->fetchOne($totalRecordSelect);

                $countDisplaySelect = clone $select;
                $countDisplaySelect->reset(Zend_Db_Select::COLUMNS)
                                   ->columns("COUNT($primaryColumn)")
                                   ->reset(Zend_Db_Select::ORDER)
                                   ->reset(Zend_Db_Select::LIMIT_COUNT)
                                   ->reset(Zend_Db_Select::LIMIT_OFFSET)
                                   ->reset(Zend_Db_Select::GROUP);
                // use a different select statement if using a group by
                if ($hasCurrentGroup) {
                    $countDisplaySelect->group($currentGroup);
                    $countDisplaySelect = 'select count(*) from ('.$countDisplaySelect->__toString().') as temp';
                } else {
                    $countDisplaySelect->limit(1);
                }
                // get display record count and add to view
                $this->view->iTotalDisplayRecords = (int) $model->getAdapter()->fetchOne($countDisplaySelect);

                // An unaltered copy of sEcho sent from the client side. This parameter will change with each draw (it is basically a draw count) - so it is important that this is implemented. Note that it strongly recommended for security reasons that you 'cast' this parameter to an integer in order to prevent Cross Site Scripting (XSS) attacks.
                $this->view->sEcho = (int) $this->getParam('sEcho');
            } catch (Exception $e) {
                error_log($e->getMessage());
                error_log($e->getTraceAsString());

                throw new Exception($e->getMessage(),$e->getCode());
            }
        }

        try {
    		$stmt = $model->getAdapter()->query($select);
            // Datatable plugins do not like tables based on a non-numeric index so
            // we tell the db to give us a numeric index.
    		$stmt->setFetchMode(Zend_Db::FETCH_NUM);
    		$this->view->stmt = $stmt;
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());

            throw new Exception($e->getMessage(),$e->getCode());
        }

    }

    /**
     * Remove record from database.
     *
     * Results will be passed for 1 hop in session namespace.
     */
    public function deleteAction ()
    {
        if ($this->hasResourceLayout()) {
            $this->_helper->layout->disableLayout();
        }
        $this->_helper->viewRenderer->setNoRender(true);

        $options = $this->getDatatableOptions();
        if ($options['editable'] == 1) {
            /**
             * We will use this to notify next controller success or failure.
             */
            $deleteColumn = $this->getDeleteKey();
            $deleteParam = $this->getDeleteParam();

            $model = $this->getDbModel();

            $aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),null,$deleteColumn,(int)$this->getRequest()->$deleteParam);
            $search = $aTmp['search'];
            $replace = $aTmp['replace'];

            $where = $model->getAdapter()->quoteInto($model->getAdapter()->quoteIdentifier($deleteColumn).' = ?', (int)$this->getRequest()->$deleteParam);

            if ($options['delete']['where'] != "") {
                $where .= ' and ' . str_replace($search,$replace,$options['delete']['where']);
            }

            $result = false;
            if ($where) {
                $result = $model->$options['delete']['method']($where);
            }

            if ($result) {
                $eventLog = $this->getEventLog();
                $eventLog->setLine(__LINE__);
                $eventLog->add((string)$options['delete']['success']);
            } else {
                $eventLog = $this->getEventLog();
                $eventLog->setLine(__LINE__);
                $eventLog->add((string)$options['delete']['error']);
            }

            if (!$result) {
                echo (string)$options['delete']['error'];
                return false;
            }

            echo 'ok';
            return true;
        }

        return false;
    }

    /**
     * Add record to database.
     *
     * @param array $extraFormOptions Optioanl Key/Value array passed to form.
     */
    public function addAction ($extraFormOptions=array())
    {
        $options = $this->getDatatableOptions();
        $idColumn = $this->getDbPrimaryKey();

        if ($this->getRequest()->isPost()) {
            //grab post data
            $data = $this->getRequest()->getPost();
            $columnMetadata = $this->getColumnMetadata();

            $form = new $options['addForm']['class'](array_merge($extraFormOptions,array('idColumn'=>$idColumn,'id'=>false,'data'=>$data)));
            $model = $this->getDbModel();

            if ($options['addForm']['useFormFiltersOnly'] != 1) {
                /**
                 * Find and use validators found in xml config.
                 *
                 * Key (ie column name) must exists in xml config or no filter or validator
                 * will be used.
                 */
                foreach ($data as $key=>$item) {
                	$columnMetadataKey = false;
                	foreach ((array)$options['columns'] as $key2 => $item) {
                        if (isset($item['data']['write']['column']) && $item['data']['write']['column'] === $key) {
                            $columnMetadataKey = $key2;
                            break;
                	    }
                	}

                	if (isset($columnMetadata->zendFilter) && isset($columnMetadata->zendFilter[$columnMetadataKey])) {
               	    	$aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),$columnMetadata->write[$columnMetadataKey],$idColumn,(int)$data[$idColumn]);
                    	$search = $aTmp['search'];
                    	$replace = $aTmp['replace'];

                	    if (isset($columnMetadata->zendFilter[$columnMetadataKey]['name']) && $columnMetadata->zendFilter[$columnMetadataKey]['name']) {
                	        $filters = array($columnMetadata->zendFilter[$columnMetadataKey]);
                	    } else {
                	        $filters = $columnMetadata->zendFilter[$columnMetadataKey];
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
                	$columnMetadataKey = false;
                	foreach ((array)$options['columns'] as $key2 => $item) {
                        if (isset($item['data']['write']['column']) && $item['data']['write']['column'] === $key) {
                            $columnMetadataKey = $key2;
                            break;
                	    }
                	}

            	    if (isset($columnMetadata->zendValidate) && isset($columnMetadata->zendValidate[$columnMetadataKey])) {
               	    	$aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),$columnMetadata->write[$columnMetadataKey],$idColumn,(int)$data[$idColumn]);
                    	$search = $aTmp['search'];
                    	$replace = $aTmp['replace'];

                    	if (isset($columnMetadata->zendValidate[$columnMetadataKey]['name']) && $columnMetadata->zendValidate[$columnMetadataKey]['name']) {
                	        $validators = array($columnMetadata->zendValidate[$columnMetadataKey]);
                	    } else {
                	        $validators = $columnMetadata->zendValidate[$columnMetadataKey];
                	    }

                	    $form = $this->addValidatorsToForm($form,$validators,$key,$search,$replace);
                	}
                }
            }

            if ($data['goback'] !== 'Cancel' && $form->isValid($data)) {
                /**
                 * Process form and data via user supplied method.
                 */
                if (!empty($options['addForm']['processSaveForm'])) {
                    $data = $this->$options['addForm']['processSaveForm']($form->getValues(),$form);
                } else {
                    $data = $form->getValues();
                }

                // clean up post data
                unset($data[$idColumn],$data['submit']);

                // add new record
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
                    $flashMessenger->addMessage(new App_Jgrowl_Success((string)$options['addForm']['success']));
                    return $result;
                } else {
                    $flashMessenger->addMessage(new App_Jgrowl_Error((string)$options['addForm']['error']));
                    return $result;
                }
            }
        } else {
            $form = new $options['addForm']['class'](array_merge($extraFormOptions,array('idColumn'=>$idColumn,'id'=>false,array())));

            /**
             * Process form and data via user supplied method.
             */
            if (!empty($options['addForm']['processDisplayForm'])) {
                $row = $this->$options['addForm']['processDisplayForm'](array(),$form);
            }
            $form->populate((array)$row);
        }

        if ($data['goback'] === 'Cancel') {
            $this->_goback();
        }

        $this->view->form = $form;
    }

    /**
     * Update record in database.
     */
    public function updateAction ()
    {
        if ($this->hasResourceLayout()) {
            $this->_helper->layout->disableLayout();
        }
        $this->_helper->viewRenderer->setNoRender(true);

        $options = $this->getDatatableOptions();
        $columnMetadata = $this->getColumnMetadata();
        $idColumn = $this->getDbPrimaryKey();

        try {
            if ($options['editable'] == 1) {
                // make datatable option available in view
                $options = $this->getDatatableOptions();

                // GET params
                $input = $this->getRequest();
                $id = $input->id;
                $value = $originalValue = $input->value;

            	$columnMetadataKey = false;
            	foreach ((array)$options['columns'] as $key2 => $item) {
            	    if ($item['label'] === $input->columnName) {
                        $columnMetadataKey = $key2;
                        break;
            	    }
            	}

            	// unable to find requested column?
            	if (!$columnMetadata->write[$columnMetadataKey]) {
                    $this->getResponse()
                         ->setHttpResponseCode(500)
                         ->appendBody("Unable to locate database.\n");
            	}

            	// is this a select box?  verify incoming data
            	$allowedResponse = false;
            	if ($columnMetadata->allowedResponse[$columnMetadataKey]) {
            	    $allowedResponse = json_decode($columnMetadata->allowedResponse[$columnMetadataKey]);
            	}

            	// check to see if incoming value is in list of allowed responses
            	if ($allowedResponse) {
            	    if ($allowedResponse->$value){
            	        //
            	    } else {
                        $this->getResponse()
                             ->setHttpResponseCode(500)
                             ->appendBody("Unable to locate response.\n");
            	    }
            	}

            	$model = $this->getDbModel();

       	    	$aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),$columnMetadata->write[$columnMetadataKey],$idColumn,(int)$input->$idColumn);
            	$search = $aTmp['search'];
            	$replace = $aTmp['replace'];

            	// run response through a filter?
            	if (isset($columnMetadata->zendFilter) && isset($columnMetadata->zendFilter[$columnMetadataKey])) {
            	    if (!isset($columnMetadata->zendFilter[$columnMetadataKey][0])) {
                	    $filterConfig = array($columnMetadata->zendFilter[$columnMetadataKey]);
            	    } else {
            	        $filterConfig = $columnMetadata->zendFilter[$columnMetadataKey];
            	    }
            	    $value = $this->filterData($value,$filterConfig,$search,$replace);
            	}

            	// validate response?
        	    if (isset($columnMetadata->zendValidate[$columnMetadataKey]) && $columnMetadata->zendValidate[$columnMetadataKey]) {
                	if (!isset($columnMetadata->zendValidate[$columnMetadataKey][0])) {
            	        $validatorConfig = array($columnMetadata->zendValidate[$columnMetadataKey]);
            	    } else {
            	        $validatorConfig = $columnMetadata->zendValidate[$columnMetadataKey];
            	    }
                    $result = $this->validateData($value,$validatorConfig,$search,$replace);
                    if (!$result->isValid) {
                        foreach ($result->messages as $message) {
                            echo $message.PHP_EOL;
                        }
        	            return false;
                    }
            	}

                $data[$columnMetadata->write[$columnMetadataKey]] = $value;

            	$where = $model->getAdapter()->quoteInto($idColumn.' = ?', $id);

        	    if (isset($columnMetadata->writeWhere[$columnMetadataKey]) && $columnMetadata->writeWhere[$columnMetadataKey] != "") {
        	        $where .= ' and ' . str_replace($search,$replace,$columnMetadata->writeWhere[$columnMetadataKey]);
        	    }

        	    // datatable will check to see if returned value is the same as the send value
        	    // if not then it sets an alert so we load the old value and echo if it's the
        	    // same preventing the alert.
            	$rows = $model->fetchRow($where);

            	if ($rows->{$columnMetadata->write[$columnMetadataKey]} === $value) {
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
     *
     * @param array $extraFormOptions Optioanl Key/Value array passed to edit form.
     * @param array $returnParams Array of associative keys to return when redirecting user
     */
    public function editAction (array $extraFormOptions = array(), array $returnParams=array())
    {
        $options = $this->getDatatableOptions();
        $columnMetadata = $this->getColumnMetadata();
        $idColumn = $this->getDbPrimaryKey();
        $data = $this->getRequest()->getPost();

        if ($this->getRequest()->isPost() && $data['goback'] !== 'Cancel') {
            //grab post data
            $model = $this->getDbModel();

			try {
	            $select = $model->select()
	        	    	        ->from($this->getDbTableName())
	        	    	        ->where($model->getAdapter()->quoteIdentifier($idColumn).' = ?',$data[$idColumn]);

	            $stmt = $model->getAdapter()->query($select);
	            $row = $stmt->fetch();
			} catch (Exception $e) {
                $eventLog = $this->getEventLog();
                $eventLog->setLine(__LINE__);
                $eventLog->add((string)$options['editForm']['error'], Zend_Log::ERR);
                error_log($e->getMessage());
                error_log($e->getTraceAsString());
				$flashMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
                $flashMessenger->addMessage(new App_Jgrowl_Error((string)$options['editForm']['error']));
                $this->_goback();
            }

            $form = new $options['editForm']['class'](array_merge(array('idColumn'=>$idColumn,'id'=>$row['id'],'row'=>$row,'data'=>$data),$extraFormOptions));

            /**
             * Find and use validators found in xml config.
             */
            foreach ($data as $key=>$item) {
            	$columnMetadataKey = false;
            	foreach ((array)$options['columns'] as $key2 => $item) {
            	    if (isset($item['data']['write']['column']) && $item['data']['write']['column'] === $key) {
                        $columnMetadataKey = $key2;
                        break;
            	    }
            	}

            	if ($options['editForm']['useFormFiltersOnly'] != 1) {
                	if (isset($columnMetadata->zendFilter[$columnMetadataKey]) && $columnMetadata->zendFilter[$columnMetadataKey]) {
            	    	$aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),$columnMetadata->write[$columnMetadataKey],$idColumn,(int)$data[$idColumn]);
                    	$search = $aTmp['search'];
                    	$replace = $aTmp['replace'];

                	    if (isset($columnMetadata->zendFilter[$columnMetadataKey]['name']) && $columnMetadata->zendFilter[$columnMetadataKey]['name']) {
                	        $filters = array($columnMetadata->zendFilter[$columnMetadataKey]);
                	    } else {
                	        $filters = $columnMetadata->zendFilter[$columnMetadataKey];
                	    }

                	    $form = $this->addFiltersToForm($form,$filters,$key,$search,$replace);
                	}
            	}

            	if ($options['editForm']['useFormValidatorsOnly'] != 1) {
                	if (isset($columnMetadata->zendValidate[$columnMetadataKey]) && $columnMetadata->zendValidate[$columnMetadataKey]) {
            	    	$aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),$columnMetadata->write[$columnMetadataKey],$idColumn,(int)$data[$idColumn]);
                    	$search = $aTmp['search'];
                    	$replace = $aTmp['replace'];

                	    if (isset($columnMetadata->zendValidate[$columnMetadataKey]['name']) && $columnMetadata->zendValidate[$columnMetadataKey]['name']) {
                	        $validators = array($columnMetadata->zendValidate[$columnMetadataKey]);
                	    } else {
                	        $validators = $columnMetadata->zendValidate[$columnMetadataKey];
                	    }

                	    $form = $this->addValidatorsToForm($form,$validators,$key,$search,$replace);
                	}
            	}
            }

            if ($data['goback'] !== 'Cancel' && $form->isValid($data)) {
                // update data in table
                $where = $model->getAdapter()->quoteInto($model->getAdapter()->quoteIdentifier($idColumn).' = ?',$data[$idColumn]);

            	$aTmp = $this->getSearchReplaceArrays($this->getDbTableName(),'',$idColumn,(int)$data[$idColumn]);
            	$search = $aTmp['search'];
            	$replace = $aTmp['replace'];

        	    if ($options['edit']['where'] != "") {
        	        $where .= ' and ' . str_replace($search,$replace,$options['edit']['where']);
        	    }

                /**
                 * Process form and data via user supplied method.
                 */
                if (!empty($options['editForm']['processSaveForm'])) {
                    $data = $this->$options['editForm']['processSaveForm']($form->getValues(),$form,$row);
                } else {
                    $data = $form->getValues();
                }

                // clean up post data
                unset($data['submit']);

        	    $result = false;
	            if ($where && is_array($data) && count($data) > 0) {
                    $result = $model->$options['editForm']['method']($data,$where);
	            }

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
                    $flashMessenger->addMessage(new App_Jgrowl_Success((string)$options['editForm']['success']));
                    return $result;
                } else {
                    $flashMessenger->addMessage(new App_Jgrowl_Error((string)$options['editForm']['error']));
                    return $result;
                }
            }
        } else if ($data['goback'] !== 'Cancel')  {
            $id = $this->getRequest()->getParam($idColumn);

            $model = $this->getDbModel();

            $select = $model->select()
        	    	        ->from($this->getDbTableName())
        	    	        ->where($model->getAdapter()->quoteIdentifier($idColumn).' = ?',$id);

            $stmt = $model->getAdapter()->query($select);
            $row = $stmt->fetch();

            $form = new $options['editForm']['class'](array_merge(array('idColumn'=>$idColumn,'id'=>$row['id'],'row'=>$row),$extraFormOptions));

            /**
             * Process form and data via user supplied method.
             */
            if (!empty($options['editForm']['processDisplayForm'])) {
                $row = $this->$options['editForm']['processDisplayForm']($row,$form);
            }
            $form->populate((array)$row);
        }

        if ($data['goback'] === 'Cancel') {
            $this->_goback($returnParams);
        }

        $this->view->form = $form;
    }

    protected function _goback(array $params=array()) {
        if (Zend_Controller_Front::getInstance()->getRequest()->getModuleName() === 'playground') {
            $front = Zend_Controller_Front::getInstance();
            $front->setBaseUrl('');
        }

        $this->_helper->redirector->gotoSimple('index',
                                               $this->getRequest()->getControllerName(),
                                               $this->getRequest()->getModuleName(),
											array_merge($this->getGoBackOptions(),$params));
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

        $inflector = new Zend_Filter_Inflector(':page');
        $inflector->setRules(array(
            ':page'  => array('Word_CamelCaseToDash', 'StringToLower'),
        ));

        $string   = $this->getRequest()->getControllerName();
        $this->view->scriptDirectory = $inflector->filter(array('page' => $string));
        $this->view->columnMetadata = $this->getColumnMetadata();
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
            foreach ($this->getJoinLefts() as $key=>$item) {
                if (isset($item['model']) && !empty($item['model'])) {
                    $model = new $item['model']();
                    $item['table'] = $model->getTableName();
                }
                if ($item['alias']) {
                    $table = $item['table'].' as '.$item['alias'];
                } else {
                    $table = $item['table'];
                }
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
        return $this->_joinLefts = array_merge($array,$this->_joinLefts);
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

        return $column;
    }

    public function validateaddformAction() {
        if ($this->hasResourceLayout()) {
            $this->_helper->layout->disableLayout();
        }
        $this->_helper->viewRenderer->setNoRender();

        $options = $this->getDatatableOptions();
        $form = new $options['addForm']['class']();
        $form->isValid($this->_getAllParams());
        $json = $form->getMessages();

        header('Content-type: application/json');

        echo Zend_Json::encode($json);
    }

    /**
     * Filter string using Zend Filters.  Used in updateAction().
     *
     * @param string $value Value to filter
     * @param array $filters Array of filters to use with arguments. (name=>Filter Name, arguments=>json encoded argmuments
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
            $filterOptions = array('class'=>$item['name']);
            if (is_array($params)) {
                $filterOptions['args'] = $params;
            }

            $reflection = new App_ReflectionInstance($filterOptions);
            $filter = $reflection->createInstance();
            $result = $filter->filter($value);
        }

        return $result;
    }

    /**
     * Filter string using Zend Filters.  Used in updateAction().
     *
     * @param string $value Value to filter
     * @param array $validators Array of validators to use with arguments. (name=>Validator Name, arguments=>json encoded argmuments
     * @param array $search Search array for parameter replacements
     * @param array $replace Replace array for parameter replacements
     * @return string
     */
    protected function validateData($value = null,array $validators = array(),array $search = array(),array $replace = array()) {
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
            $validator = $reflection->createInstance();
            $result = $validator->isValid($value);

            if (!$result) {
                $resultset = new stdClass();
                $resultset->isValid = $result;
                $resultset->messages = $validator->getMessages();
                // break on validation fail
                return $resultset;
            }
        }

        $resultset = new stdClass();
        $resultset->isValid = true;
        return $resultset;
    }

    /**
     * Validate string using Zend Validate
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

    /**
     * Start/return event log.
     */
    public function getEventLog() {
        if (null === $this->_eventLog) {
            $options = array('username'=>'datatables',
                             'file' => basename(__FILE__));
            $this->_eventLog = new App_Log_Event($options);
        }

        return $this->_eventLog;
    }

	/**
     * Get session namespace for this controller.
     */
    public function getSessionNamespace() {
        return $this->getSessionNamespaces()->getNamespace($this->_namespace);
    }


    /**
     * Get App_Session_Namespace service class.
     */
    protected function getSessionNamespaces() {
        if (null === $this->_appNamespaces) {
            $this->_appNamespaces = new App_Session_Namespace();
        }

        return $this->_appNamespaces;
    }

    /**
     * Get dbtable model instance.
     *
     * @throws Exception
     * @return Model_Db_Table
     */
    protected function getDbModel() {
        if (null === $this->_dbModel) {
            $options = $this->getDatatableOptions();
            $this->_dbModel = new $options['dbModel']['class']($this->getDbModelOptions());
        }

        return $this->_dbModel;
    }

    /**
     * Set options to used when initiating the model for this datatable.
     * @param $options
     * @return App_Controller_Datatable
     */
    public function setDbModelOptions($options) {
        $this->_dbModelOptions = $options;
        return $this;
    }

    /**
     * Get options used for initiating the model for this datatable.
     * @return mixed
     */
    public function getDbModelOptions() {
        return $this->_dbModelOptions;
    }
    /**
     * Get table name
     *
     * @throws Exception
     * @return string
     */
    protected function getDbTableName() {
        $options = $this->getDatatableOptions();

    	if (isset($options['dbModel']['alias']) && !empty($options['dbModel']['alias'])) {
            $table = array($options['dbModel']['alias']=>$this->getDbModel()->getTableName());
    	} else {
    	    $table = $this->getDbModel()->getTableName();
    	}

    	return $table;
    }

    /**
     * Build column data array from datatable options.
     *
     * @throws Exception
     * @return App_Controller_Datatable_Columns
     */
    protected function getColumnMetadata() {
        $options = $this->getDatatableOptions();
        $columnMetadata = new App_Controller_Datatable_Columns();
    	foreach ($options['columns'] as $key => $item) {
    	    if ($item['hidden'] === '1') {
    	        $columnMetadata->hidden[$key] = true;
    	    } else {
    	        $columnMetadata->hidden[$key] = false;
    	    }

    	    /**
    	     * Determine/track column as entries.
    	     */
    	    if (isset($item['data']['read']['column'])) {
        	    $readColumn = $item['data']['read']['column'];
        	    if (!empty($item['data']['read']['as'])) {
        	        $colAs = $item['data']['read']['as'];
        	    } else {
        	        $colAs = false;
        	    }
        	    $columnMetadata->as[$key] = $colAs;
        	    if ($colAs) {
        	        $readColumn = new Zend_Db_Expr($readColumn.' as '.$colAs);
        	    }
        	    /**
        	     * Need blank column when missing since everything is indexed numerically.
        	     */
        	    if (!$item['data']['read']['column']) {
        	        $columnMetadata->readRaw[$key] = 'link';
        	        $columnMetadata->read[$key] = new Zend_Db_Expr("'link'");
        	        $columnMetadata->naturalSort[$key] = false;
        	    } else if (isset($item['data']['read']['zendDbExpr']) && $item['data']['read']['zendDbExpr'] == 1) {
        	        $columnMetadata->readRaw[$key] = $item['data']['read']['column'];
        	        $columnMetadata->read[$key] = new Zend_Db_Expr($readColumn);
        	        $columnMetadata->naturalSort[$key] = (bool)$item['naturalSort'];
        	    } else {
        	        $columnMetadata->readRaw[$key] = $item['data']['read']['column'];
        	        $columnMetadata->read[$key] = $readColumn;
        		    $columnMetadata->naturalSort[$key] = (bool)$item['naturalSort'];
        	    }

        	    if (isset($item['data']['search']['column']) && !empty($item['data']['search']['column'])) {
        	        // use read column if search column isn't available
        	        $columnMetadata->search[$key] = $item['data']['search']['column'];
        	    } else {
        	        $columnMetadata->search[$key] = $item['data']['read']['column'];
        	    }
    	    } else {
    	        $columnMetadata->read[$key] = new Zend_Db_Expr("'blank'");
    	        $columnMetadata->readRaw[$key] = 'blank';
    	        $columnMetadata->naturalSort[$key] = false;
    	        $columnMetadata->search[$key] = new Zend_Db_Expr("'blank'");
    	    }

    	    if (isset($item['data']['write']['column']) && !empty($item['data']['write']['column'])) {
    	        $columnMetadata->write[$key] = $item['data']['write']['column'];
    	    }

    	    if (isset($item['data']['write']['where']) && !empty($item['data']['write']['where'])) {
    	        $columnMetadata->writeWhere[$key] = $item['data']['write']['where'];
    	    }

    	    if (isset($item['allowedResponse']) && !empty($item['allowedResponse'])) {
    	        $columnMetadata->allowedResponse[$key] = $item['allowedResponse'];
    	    } else {
    	        $columnMetadata->allowedResponse[$key] = null;
    	    }

    	    if (isset($item['zendFilter']) && $item['zendFilter']) {
    	        $columnMetadata->zendFilter[$key] = $item['zendFilter'];
    	    }

    	    if (isset($item['zendValidate']) && $item['zendValidate']) {
    	        $columnMetadata->zendValidate[$key] = $item['zendValidate'];
    	    }

    	    if ($item['isSortDefault'] == "true" || $item['isSortDefault'] == 1) {
    	        $columnMetadata->defaultSortColumn = $item['data']['read']['column'];

    	        if (isset($item['sortDirection']) && $item['sortDirection']) {
                    $columnMetadata->defaultSortDirection = $item['sortDirection'];
    	        } else {
    	            $columnMetadata->defaultSortDirection = 'ASC';
    	        }
    	    }
    	}

    	return $columnMetadata;
    }

    protected function getJoinLeftsConfig() {
        $options = $this->getDatatableOptions();
        $joinLefts = array();

        if (is_array($options['joinLeft']) && !isset($options['joinLeft'][0])) {
            $joinLefts[] = $options['joinLeft'];
        } else if (is_array($options['joinLeft']) && isset($options['joinLeft'][0])) {
            foreach ($options['joinLeft'] as $key1=>$item) {
        	    /**
        	     * Make list of left joins.
        	     */
        	    if ($item['table'] || $item['model']) {
                    $joinLefts[$key1] = $item;
        	    }
        	}
        }
    	return $joinLefts;
    }

    /**
     * Get table primary key.  Can be optional speicifed in config xml.
     *
     * @param boolean $alias Add alias?
     * @throws Exception
     * @return string
     */
    protected function getDbPrimaryKey($alias=false) {
        $options = $this->getDatatableOptions();
        if (!isset($options['dbModel']['primary']) || empty($options['dbModel']['primary'])) {
            $primary =  $this->getDbPrimaryKeyFromModel();
        } else {
            $primary = $options['dbModel']['primary'];
        }
        if ($alias && isset($options['dbModel']['alias']) && !empty($options['dbModel']['alias'])) {
            $primary = $options['dbModel']['alias'].'.'.$primary;
        }
        return $primary;
    }

    /**
     * Get delete table key.  Will use primary key if not specified in config xml.
     *
     * @throws Exception
     * @return string
     */
    protected function getDeleteKey() {
        $options = $this->getDatatableOptions();
        if (!isset($options['delete']['column']) || empty($options['delete']['column'])) {
            return $this->getDbPrimaryKeyFromModel();
        }
        return $options['delete']['column'];
    }

    /**
     * Get name of delete GET param.  Will use primary key if not specified in config xml.
     *
     * @throws Exception
     * @return string
     */
    protected function getDeleteParam() {
        $options = $this->getDatatableOptions();
        if (!isset($options['delete']['param']) || empty($options['delete']['param'])) {
            return $this->getDbPrimaryKeyFromModel();
        }
        return $options['delete']['param'];
    }

    protected function getDbPrimaryKeyFromModel() {
        $options = $this->getDatatableOptions();
        $tmp = $this->getDbModel()->info('primary');
        if (count($tmp) <> 1) {
            throw new Exception('DB Model may only have 1 primary key.');
        }
        $keys = array_keys($tmp);
        return $tmp[$keys[0]];
    }

    /**
     * Build select statement.
     *
     * @param string $where Additional where for SQL.
     */
    public function buildSelect ($where = null, $input)
    {
        $options = $this->getDatatableOptions();
    	$columnMetadata = $this->getColumnMetadata();

    	// store joinlefts from config to class
    	$this->setJoinLefts($this->getJoinLeftsConfig());

    	$model = $this->getDbModel();
    	$select = $model->select()
        		        ->from($this->getDbTableName(),$columnMetadata->read)
                        ->setIntegrityCheck(false);

        if (isset($options['dbModel']['distinct']) && $options['dbModel']['distinct'] == 1) {
            $select->distinct(true);
        }
        foreach (array('group','order') as $item) {
            if (isset($options['dbModel'][$item]['prepend']) && !empty($options['dbModel'][$item]['prepend'])) {
                $select->$item($options['dbModel'][$item]['prepend']);
            }
        }

        // add left joins
        $select = $this->addJoinLefts($select);

        if ($where != null) {
            $select->where($where);
        }

	    // server side processing
        if ($options['bServerSide'] == "true") {
            $select = $this->buildSearchWhere($input, $select);
            // limit records for this response
            $select->limit($input['iDisplayLength'], $input['iDisplayStart']);
        }

        if ($input['iSortingCols']) {
            $sortColumn = $columnMetadata->search[(int)$input['iSortCol_0']];
            $naturalSort = $columnMetadata->naturalSort[(int)$input['iSortCol_0']];

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
            if ($columnMetadata->defaultSortColumn == true) {
                $sortColumn = $columnMetadata->defaultSortColumn;
                $sortDirection = $columnMetadata->defaultSortDirection;
                $natSort = $columnMetadata->naturalSort;

                if ((bool)$natSort) {
                    // hack to make MySQL naturally sort numeric columns
                    $select->order('LENGTH('.$sortColumn.') '.$sortDirection.', '.$sortColumn.' '.$sortDirection);
                } else {
                    $select->order($sortColumn.' '.$sortDirection);
                }
            }
        }

        foreach (array('group','order') as $item) {
            if (isset($options['dbModel'][$item]['append']) && !empty($options['dbModel'][$item]['append'])) {
                $select->$item($options['dbModel'][$item]['append']);
            }
        }

        return $select;
    }

    /**
     * Build where portion of select statement
     *
     * @param string $where Additional where for SQL.
     */
    public function buildSearchWhere ($input, Zend_Db_Table_Select $select)
    {
        $options = $this->getDatatableOptions();
    	$columnMetadata = $this->getColumnMetadata();
    	$model = $this->getDbModel();

	    // server side processing
        if ($options['bServerSide'] == "true") {
            // searches
            if ($input['sSearch'] != "") {
                $atoms = array();
                foreach ($options['columns'] as $key=>$item) {
                    if ((bool)$item['searchable']) {
                        // COLLATE utf8_general_ci used to make search case insensitive
                        $atoms[] = $model->getAdapter()->quoteInto($columnMetadata->search[$key].' LIKE ? COLLATE utf8_general_ci', $input['sSearch']);
                    }
            	}

            	if (count($atoms) > 0) {
                	$select->where(implode(' OR ',$atoms));
            	}
            }

            // single column searchs
            $i = 0;
            foreach ($options['columns'] as $key=>$item) {
                $searchable = (bool)$item['columnSearch']['enable'];
                $string = $input['sSearch_'.$i];

                if ($searchable && $string !== "" && $string !== false && !is_null($string)) {
                    $column = $columnMetadata->search[$key];
                    switch ($item['columnSearch']['method']) {
                        case 'like':
                            $select->where($column.' LIKE ? COLLATE utf8_general_ci' ,$string);
                            break;
                        case 'single':
                        default:
                            $select->where($column.' = ?',$string);
                            break;
                    }
                }

                if (!(bool)$columnMetadata->hidden[$key]) {
                    $i++;
                }
            }
        }

        return $select;
    }

    /**
     * Set options used when redirecting.
     *
     * @param array $array
     * @return App_Controller_Datatable
     */
    public function setGoBackOptions(array $array) {
        $this->_goBackOptions = $array;
        return $this;
    }

    /**
     * Get options used when redirecting.
     *
     * @return array
     */
    public function getGoBackOptions() {
        return $this->_goBackOptions;
    }

    public function hasResourceLayout() {
        $front = Zend_Controller_Front::getInstance();
        $bootstrap = $front->getParam("bootstrap");
        return $bootstrap->hasResource('layout');
    }
}
