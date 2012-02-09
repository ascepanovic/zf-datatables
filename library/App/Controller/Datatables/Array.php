<?php
/**
 * Standard controller to handle jquery datatables.  This controller
 * will use PHP to sort and filter dataset.  This will allow use of PHP
 * to modify result sets before displaying to the user.  Disadavantage is
 * we'll have to scan all records in the table.  If you have a large table
 * this may not want to use this.
 *
 * @see App_Controller_Datatable
 * @author James Johnson
 */

abstract class App_Controller_Datatable_array extends App_Controller_Datatable {
    /**
     * Get data for ajax call from datatable.
     *
     * @param string $where Additional where for SQL.
     */
    public function datasourceAction ($where = null)
    {
        $this->_helper->layout->disableLayout();

        $options = $this->getDatatableOptions();

        // make datatable option available in view
        $this->assignOptions2View();

    	$model = new $options['dbModel']();

        /**
         * Process rowset through model methods defined in config.xml
         */
        $result = $model->$options['dbModelMethod']($options,$where);

        if (null !== $result) {
            /**
             * Use php's sorting on results from a php method.  Otherwise we use SQL's sorting.
             */
            if ($options['bServerSide'] == "true") {
                // GET params
                $input = $this->getRequest()->getParams();

                $totalRecords = count($result['records']);

                /**
                 * Filter records start
                 */
                $columnSearch = array();

                // global search
                if ($input['sSearch'] != "") {
                    $columnSearch['all'] = $input['sSearch'];
                }

                // single column searchs
                $i = 0;
                foreach ($options['columns'] as $key=>$item) {
                    $searchable = (bool)$item['columnSearch']['enable'];
                    $string = $input['sSearch_'.$i];

                    if ($searchable && $string !== "" && $string !== false && !is_null($string)) {
                        switch ($item['columnSearch']['method']) {
                            case 'like':
                                $columnSearch['like'][$key] = $string;
                                break;
                            case 'single':
                            default:
                                $columnSearch['single'][$key] = strtolower($string);
                                break;
                        }
                    }

                    if (!(bool)$item['hidden']) {
                        $i++;
                    }
                }

                $result['records'] = $this->searchArray($columnSearch, $options['columns'], $result['records']);
                /**
                 * Filter records stop
                 */

				/**
                 * Sort records
                 */
                if ((int)$input['iSortCol_0'] > -1) {
                    // Obtain a list of columns
                    foreach ($result['records'] as $key => $row) {
                        $sort[$key]  = $row[(int)$input['iSortCol_0']];
                    }

                    switch (strtolower($input['sSortDir_0'])) {
                        case 'asc':
                            $sortDirection = SORT_ASC;
                            break;
                        default:
                            $sortDirection = SORT_DESC;
                            break;
                    }

                    // Sort the data with volume descending, edition ascending
                    // Add $data as the last parameter, to sort by the common key
                    array_multisort($sort, $sortDirection,  SORT_STRING, $result['records']);
                }

                /**
                 * Limit results
                 */
                $paginator = new Zend_Paginator(new Zend_Paginator_Adapter_Array($result['records']));
                $currentPage = $input['iDisplayStart'] / $input['iDisplayLength']+1;
                $paginator->setCurrentPageNumber($currentPage);
                $paginator->setItemCountPerPage($input['iDisplayLength']);

                $result['records'] = $paginator->getCurrentItems();

                /**
                 * Add counters to view
                 */
                // total # of records, with filters & no/paginator
                $this->view->iTotalDisplayRecords = (int) $paginator->getTotalItemCount();

                // total # of records, without filters or paginator
                $this->view->iTotalRecords = (int) $totalRecords;

                // An unaltered copy of sEcho sent from the client side. This parameter will change with each draw (it is basically a draw count) - so it is important that this is implemented. Note that it strongly recommended for security reasons that you 'cast' this parameter to an integer in order to prevent Cross Site Scripting (XSS) attacks.
                $this->view->sEcho = (int) $input['sEcho'];
            }

            /**
             * Assign data to template/view.
             */
            $this->view->records = $result['records'];
            $this->view->stats = $result['stats'];

        }
    }

	/**
	 * Filter responses in array.
	 *
	 * @param array $search Search data
	 * @param array $columns Column data
	 * @param arrat $array Data set
	 * @return array New data set
	 */
	public function searchArray(array $search, array $columns, array $array) {
	    $out = array();

	    /**
	     * Do global search first.
	     */
        if (isset($search['all']) && $search['all']) {
            foreach ($array as $key=>$row) {
                $keep = false;
                foreach ($row as $col=>$item) {
                    if ((bool)$columns[$col]['searchable']) {
                        if (stripos($item,$search['all']) !== false) {
                            $keep = true;
                        }
                    }
                }

                if ($keep) {
                    $out[] = $row;
                }
            }
        } else {
            $out = $array;
        }

        /**
         * Column specific searches.
         */
	    foreach ($columns as $key=>$item) {
	        if (isset($search['like'][$key]) && $search['like'][$key]) {
	            $array = $out;
	            $out = array();
	            foreach ($array as $row) {
	                $keep = false;

	                if (stripos($row[$key],$search['like'][$key]) !== false) {
                        $keep = true;
                    }

                    if ($keep) {
                        $out[] = $row;
                    }
	            }
	        }

	        if (isset($search['single'][$key]) && $search['single'][$key]) {
	            $array = $out;
	            $out = array();
	            foreach ($array as $row) {
	                $keep = false;

	                if (strtolower($row[$key]) == $search['single'][$key]) {
                        $keep = true;
                    }

                    if ($keep) {
                        $out[] = $row;
                    }
	            }
	        }
	    }

	    return $out;
	}
}
