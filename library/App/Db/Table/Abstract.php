<?php
abstract class App_Db_Table_Abstract extends Zend_Db_Table_Abstract
{
    public function __construct($config = null)
    {
        if (isset($this->_use_adapter) && null !== $this->_use_adapter) {
            $config = Zend_Registry::get($this->_use_adapter);
        }

        $this->setDatabaseName();

        return parent::__construct($config);
    }

    /**
     * Setup table name.
     *
     * @param string $table
     */
    public function setTableName($table)
    {
        $this->_name = $table;
        parent::_setupTableName($table);
    }

    /**
     * Get table name used in this class.
     *
     * @param bool $addDatabaseName 
     * @return string
     */
    public function getTableName($addDatabaseName=false) {
        if ($addDatabaseName) {
            return $this->getDatabaseName().'.'.$this->_name;
        }
        
        return $this->_name;
    }

    /**
     * Set database name.
     *
     * @param string $table
     */
    public function setDatabaseName()
    {
        if (isset($this->_use_adapter) && null !== $this->_use_adapter) {
            $dbAdapter = Zend_Registry::get($this->_use_adapter);
        } else {
            $dbAdapter = Zend_Db_Table::getDefaultAdapter();
        }

        $dbConfig = $dbAdapter->getConfig();

        $this->_databaseName = $dbConfig['dbname'];
    }

    /**
     * Get database name used in this class.
     *
     * @return string
     */
    public function getDatabaseName() {
        return $this->_databaseName;
    }
}