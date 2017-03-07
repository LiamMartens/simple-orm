<?php
    class DB {
        public static $columnChar = '';
        /** @var mixed $_pdo */
        protected static $_pdo = false;
        /** @var string $_dbname */
        private static $_dbname = '';
        /** @var string $_dbhost */
        private static $_dbhost = 'localhost';
        /** @var int $_dbport */
        private static $_dbport = 3306;
        /** @var string $_dbuser */
        private static $_dbuser = 'root';
        /** @var string $_dbpass */
        private static $_dbpass = '';

        /**
        * Checks environment keys for a value or
        * uses default value
        *
        * @param array $keys
        * @param mixed $default
        *
        * @return mixed
        */
        private static function envcheck(array $keys, $default) {
            foreach($keys as $key) {
                $value = getenv($key);
                if($value !== false) {
                    return $value;
                }
            }
            return $default;
        }

        /**
        * Configures the ORM with DB credentials
        *
        * @param array $options
        */
        public static function configure(string $type, array $options = []) {
            $upper = strtoupper($type);
            self::$_dbname = self::envcheck([ 'DBNAME', $upper.'_DBNAME', 'MARIADB_DBNAME' ], isset($options['dbname']) ? $options['dbname'] : '');
            self::$_dbhost = self::envcheck([ 'DBHOST', $upper.'_HOST', 'MARIADB_HOST' ], isset($options['host']) ? $options['host'] : 'localhost');
            self::$_dbport = self::envcheck([ 'DBPORT', $upper.'_PORT', 'MARIADB_PORT' ], isset($options['port']) ? $options['port'] : 3306);
            self::$_dbuser = self::envcheck([ 'DBUSER', $upper.'_USER', 'MARIADB_USER' ], isset($options['user']) ? $options['user'] : 'root');
            self::$_dbpass = self::envcheck([ 'DBPASS', $upper.'_PASS', 'MARIADB_PASS' ], isset($options['pass']) ? $options['pass'] : '');
            // create new database connection
            self::$_pdo = new PDO($type.':host='.self::$_dbhost.';port='.self::$_dbport.';dbname='.self::$_dbname, self::$_dbuser, self::$_dbpass);
            // swtich type
            switch($type) {
                case 'mysql':
                    DB::$columnChar = '`';
                    break;
            }
        }

        /**
        * Executes a query and returns the statemenrt
        *
        * @param string $sql
        * @param array $params
        */
        public static function query(string $sql, array $params = []) {
            $stmt = self::$_pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
    }

    interface IQuery {
        /**
        * Gets the SQL of the part of the query
        *
        * @param bool $first Defines whether the query part is a first part can be used to define whether and/or needs to be pefixed
        *
        * @return string
        */
        public function getSQL() : string;
        
        /**
        * Gets the values assigned to the part of the query
        *
        * @return array
        */
        public function getValues() : array;
    }
    
    class Where implements IQuery {
        const TYPE_AND = 'and';
        const TYPE_OR = 'or';

        /** @var string $_type */
        protected $_type = 'and';
        /** @var string $_condition */
        protected $_condition = '';
        /** @var array $_values */
        protected $_values = [];
        /** @var bool $_first */
        protected $_first = false;

        public function __construct(string $type, string $condition, array $values = [], bool $first = false) {
            $this->_type = $type;
            $this->_condition = $condition;
            $this->_values = $values;
            $this->_first = $first;
        }

        public function getSQL() : string {
            $start = ($this->_first) ? '' : $this->_type;
            return ' '.$start.' '.$this->_condition;
        }

        public function getValues() : array {
            return $this->_values;
        }

        public function __toString() : string {
            return $this->getSQL();
        }
    }

    class WhereGroup implements IQuery {
        const TYPE_AND = 'and';
        const TYPE_OR = 'or';

        /** @var string $_type */
        protected $_type = 'and';
        /** @var array $_wheres Contains all the where statements in the group */
        protected $_wheres = [];
        /** @var bool $_first */
        protected $_first = false;

        public function __construct(string $type = self::TYPE_AND, bool $first = false) {
            $this->_type = $type;
            $this->_first = $first;
        }

        /**
        * Adds a Where statement to the WhereGroup
        *
        * @param string|Where $where Where statement or and/or type
        * @param string $condition The where condition
        * @param array $values Assigned values
        */
        public function addWhere($where, string $condition = '', array $values = []) {
            if($where instanceof Where) {
                $this->_wheres[] = $where;
            } else {
                $this->_wheres[] = new Where($where, $condition, $values, count($this->_wheres)==0);
            }
        }

        /**
        * Adds AND Where
        *
        * @param string|Where Where condition or Where object
        * @param array Array of values to assign to the where stmt
        */
        public function andWhere($where, array $values = []) {
            $this->addWhere(Where::TYPE_AND, $where, $values);
        }

        /**
        * Adds OR Where
        *
        * @param string|Where Where condition or Where object
        * @param array Array of values to assign to the where stmt
        */
        public function orWhere($where, array $values = []) {
            $this->addWhere(Where::TYPE_OR, $where, $values);
        }

        public function getSQL(bool $first = false) : string {
            $prefix = ($first) ? '' : $this->_type;
            $prefix .= ' (';
            $suffix = ') ';

            $conditions = '';
            $whereFirst = true;
            foreach($this->_wheres as $where) {
                $conditions.=$where->getSQL();
                $whereFirst = false;
            }

            return $prefix.$conditions.$suffix;
        }

        public function getValues() : array {
            $values = [];
            foreach($this->_wheres as $where) {
                $values = array_merge($values, $where->getValues());
            }
            return $values;
        }

        public function __toString() : string {
            return $this->getSQL();
        }
    }

    class Order implements IQuery {
        const D_ASC = 'ASC';
        const D_DESC = 'DESC';

        protected $_direction = 'ASC';
        protected $_column = '';    

        public function __construct(string $by, string $column) {
            $this->_direction = $by;
            $this->_column = $column;
        }

        public function getSQL() : string {
            return DB::$columnChar.$this->_column.DB::$columnChar.' '.$this->_direction;
        }

        public function getValues() : array {
            return [];
        }

        public function __toString() : string {
            return $this->getSQL();
        }
    }

    class Model extends DB implements IQuery {
        /** @var array stores the Model object properties */
        public $properties = false;
        /** @var boolean Whether it exists already or not */
        protected $_exists = false;
        /** @var array contains the where groups */
        protected $_whereGroups = [];
        /** @var int Contains the limit */
        protected $_limit = false;
        /** @var array Contains the columns to select */
        protected $_columns = [];
        /** @var array Contains the orders */
        protected $_orders = [];

        public function __construct($exists = false) {
            $this->properties = new stdClass();
            $this->_exists = $exists;
        }

        public static function __callStatic($m, $args) {
            $class = get_called_class();
            $obj = new $class();
            $method = '_'.$m;
            call_user_func_array([ $obj, $method ] , $args);
            return $obj;
        }

        public function __call($m, $args) {
            $method = '_'.$m;
            call_user_func_array([ $this, $method ], $args);
            return $this;
        }

        protected function getPrimaryColumn() : string {
            $class = get_called_class();
            if(property_exists($class, '_primary')) {
                return $class::$_primary;
            }
            return 'id';
        }

        /**
        * Gets the table name based on the class name or
        * based on the static property $_tblname
        *
        * @return string
        */
        protected function getTableName() : string {
            $class = get_called_class();
            if(property_exists($class, '_tblname')) {
                return $class::$_tblname;
            }
            return (strtolower($class).'s');
        }

        /**
        * Sets exists status
        */
        protected function exists() {
            $this->_exists = true;
        }

        /**
        * Sets the columns to select
        *
        * @param array $cols
        * @param bool $append
        */
        protected function columns(array $cols, bool $append = false) {
            if($append==true) {
                $this->_columns = array_merge($cols, $this->_columns);
            } else {
                $this->_columns = $cols;
            }
        }

        /**
        * Adds where group to the model query
        *
        * @param function $wherefunc
        */
        protected function _andWhere($wherefunc) {
            $this->_whereGroups[] = $wherefunc(new WhereGroup(WhereGroup::TYPE_AND, count($this->_whereGroups)==0));
        }

        /**
        * Adds a where group (or) to the model query
        *
        * @param function $wherefunc
        */
        protected function _orWhere($wherefunc) {
            $this->_whereGroups[] = $wherefunc(new WhereGroup(WhereGroup::TYPE_OR, count($this->_whereGroups)==0));
        }

        /**
        * Adds ascending order
        *
        * @param string $by
        */
        protected function _orderAsc($by) {
            $this->_orders[] = new Order(Order::D_ASC, $by);
        }

        /**
        * Adds descending order
        *
        * @param string $by
        */
        protected function _orderDesc($by) {
            $this->_orders[] = new Order(Order::D_DESC, $by);
        }

        /**
        * Sets the query limit
        *
        * @param int $n
        */
        protected function _limit(int $n) {
            $this->_limit = $n;
        }

        /**
        * Executes a select statement and finds records
        *
        * @return array(Model)
        */
        protected function _find() {
            $class = get_called_class();
            $columns = (empty($this->_columns)) ? '*' : DB::$columnChar.implode(DB::$columnChar.','.DB::$columnChar, $this->_columns).DB::$columnChar;
            $records = self::query('SELECT '.$columns.' FROM '.$this->getTableName().' '.$this->getSQL(), $this->getValues())->fetchAll(PDO::FETCH_ASSOC);
            $entries = [];
            foreach($records as $r) {
                $entr = new $class();
                $entr->exists();
                foreach($r as $k => $v) {
                    $entr->properties->{$k} = $v;
                }
                $entries[] = $entr;
            }
            return $entries;
        }

        /**
        * Executes a select statement and sets limit to 1
        *
        * @return Model|false
        */
        protected function _findOne() {
            $this->limit(1);
            $entries = $this->find();
            if(empty($entries)) {
                return false;
            }
            return $entries[0];
        }

        /**
        * Saves an object
        */
        protected function _save() {
            $primary = $this->getPrimaryColumn();
            if(!isset($this->properties->{$primary})&&$this->_exists) {
                throw new Exception('No valid primary key column found');
            }
            $values = [];
            if($this->_exists) {
                // execute update statement instead of insert
                $sql = 'UPDATE '.$this->getTableName().' SET ';
                $first = true;
                foreach($this->properties as $k => $v) {
                    $key = ':'.md5(random_bytes(4));
                    if(!$first) { $sql .= ' ,'; }
                    $sql .= DB::$columnChar.$k.DB::$columnChar.'='.$key;
                    $values[$key] = $v;
                    $first = false;
                }
                $key = ':'.md5(random_bytes(4));
                $sql .= ' WHERE '.DB::$columnChar.$primary.DB::$columnChar.'='.$key;
                $values[$key] = $this->properties->{$primary};
                self::query($sql, $values);
            } else {
                // create new record
                $sql = 'INSERT INTO '.$this->getTableName().' (';
                $vals = '(';
                $first = true;
                foreach($this->properties as $k => $v) {
                    $key = ':'.md5(random_bytes(4));
                    if(!$first) {
                        $sql .= ' ,';
                        $vals .= ' ,';
                    }
                    $sql .= DB::$columnChar.$k.DB::$columnChar;
                    $values[$key] = $v;
                    $vals .= $key;
                    $first = false;
                }
                $sql .= ') VALUES '.$vals.')';
                $stmt = self::query($sql, $values);
                if($stmt->rowCount()>0) {
                    // set last insert
                    $this->exists();
                    $this->properties->{$primary} = self::$_pdo->lastInsertId();
                }
            }

        }

        public function getSQL() : string {
            $query = '';
            $whereFirst = true;
            foreach($this->_whereGroups as $wg) {
                $query.=$wg->getSQL($whereFirst);
                $whereFirst = false;
            }
            $query = (trim($query)=='') ? '' : ' WHERE '.$query;
            // add order
            if(!empty($this->_orders)) {
                $query .= ' ORDER BY '.implode(',', $this->_orders);
            }
            // add limit
            if($this->_limit !== false) {
                $query .= ' LIMIT '.$this->_limit;
            }
            return $query;
        }

        public function getValues() : array {
            $values = [];
            foreach($this->_whereGroups as $wg) {
                $values = array_merge($values, $wg->getValues());
            }
            return $values;
        }
    }

    /*DB::configure('mysql', [
        'dbname' => 'stackoverflow',
        'user' => 'stackuser',
        'pass' => 'stackpass'
    ]);*/