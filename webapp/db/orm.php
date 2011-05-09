<?php
namespace webapp\db;

require_once dirname(__FILE__) . '/../../vendor/adodb5/adodb.inc.php';

class Connection
{
	public $dsn;
	public $scheme;
	public $server;
	public $user;
	public $pass;
	public $db;

	private $frontend;
	public $isConnect = false;

	private $tables = array();

	private static $instance;

	public function __construct($dsn=null)
	{
		if (!class_exists('ADOConnection')) {
			throw new \Exception('db.Connection requires ADODB php library');
			return;
		}
		if ($dsn != null) {
			$this->connect($dsn);
		}
	}

	public function connect($dsn)
	{
		$this->dsn = $dsn;
		$components = parse_url($dsn);
		$this->scheme = $components['scheme'];
		$this->server = $components['host'];
		$this->user = $components['user'];
		$this->pass = $components['pass'];
		$this->db = substr($components['path'],1);

		$this->frontend = \ADONewConnection($dsn);
		if ($this->frontend) {
			$this->frontend->SetFetchMode(\ADODB_FETCH_ASSOC);
			$this->isConnect = true;
			$this->tables = $this->frontend->MetaTables('TABLES');
		}
	}

	public function getConnection()
	{
		return $this->frontend;
		return null;
	}

	public function __call($method, $args)
	{
		$result = call_user_func_array(array(&$this->frontend, $method), $args);
		if ($method == 'Execute' && false !== stripos($args[0], 'DROP TABLE')) {
			$this->tables = $this->frontend->MetaTables('TABLES');
		}
		return $result;
	}

	public function addTable($table)
	{
		if (!$this->tableExists($table)) {
			$this->tables[] = $table;
		}
	}

	public function tableExists($table)
	{
		return in_array($table, $this->tables);
	}

	public function __toString()
	{
		return sprintf('<db.Connection dsn="%s">', $this->dsn);
	}
}

class RecordSet implements \Iterator, \Countable, \ArrayAccess
{
	protected $data;
	protected $cursor = 0;

	function __construct($data=null)
	{
		if (null != $data) {
			$this->data = data;
		}
	}

	// Iterator implements -------------------------------
	public function current()
	{
		return $this->data[$this->cursor];
	}

	public function key()
	{
		return $this->cursor;
	}

	public function next()
	{
		++$this->cursor;
	}

	public function rewind()
	{
		$this->cursor = 0;
	}

	public function valid()
	{
		return isset($this->data[$this->cursor]);
	}
	// ---------------------------------------------------

	// Countable implements ------------------------------
	public function count()
	{
		return count($this->data);
	}
	// ---------------------------------------------------

	// ArrayAccess implementations -----------------------
	public function offsetExists($offset)
	{
		return ($offset >= 0 && $offset < $this->count());
	}

	public function offsetGet($offset)
	{
		if (null != $this->data) {
			return $this->data[$offset];
		}
		return null;
	}

	public function offsetSet($offset, $value)
	{
		$this->data[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}
	// ---------------------------------------------------
}

class Query extends RecordSet
{
	protected $cmd = 'SELECT';
	protected $entity;
	protected $fields = '*';

	protected $condition = '';	// used by 'query' method
	protected $conditionArray = array();	// used by 'filter' method
	protected $sort = '';
	protected $range = '';

	function __construct($entity, $fields='*')
	{
		//is_subclass_of($entity, 'db\Model')
		//is_subclass_of($entity, 'db\Query')
		$this->entity = $entity;
		$this->fields = $fields;
	}

	public function getTable()
	{
		if (is_subclass_of($this->entity, 'webapp\db\Model')) {
			return call_user_func(array($this->entity, 'getTableName'));
		} else if (is_subclass_of($this->entity, 'webapp\db\Query')) {
			return '(' . $this->entity->getSQL() . ')';
		} else if (is_string($this->entity)) {
			return $this->entity;
		} else {
			throw new \Exception('Query entity is invalid');
			return null;
		}
	}

	private function quote($val)
	{
		return "'" . str_replace("'", "\\'", $val) . "'";
	}

	public function filter($cond, $val=null)
	{
		if (is_subclass_of($filter, 'webapp\db\Model')) {
		} else if (is_subclass_of($filter, 'webapp\db\Query')) {
		} else {
			// $wizards = Person::all()->filter('age >=', 25)->filter('experience =', false)->fetch();
			$q = clone $this;
			if (is_string($cond) && null != $val) {
				$q->conditionArray[] = $cond . ' ' . (is_string($val) ? $this->quote($val) : $val);
			} else if (is_string($cond) && null == $val) {
				$q->conditionArray[] = $cond;
			} else if (is_array($cond)) {
				// some implementations...
			}
			return $q;
		}
		return $this;
	}

	public function order($order)
	{
		$q = clone $this;
		if ($order{0} == '-' && strstr($order, 'DESC') === false) {
			$order = substr($order, 1) . ' DESC';
		}
		$q->sort = $order;
		return $q;
	}

	public function limit($offset, $amount=0)
	{
		$q = clone $this;
		if ($amount == 0) {
			$q->range = (string) $offset;
		} else {
			$q->range = $offset . ', ' . $amount;
		}
		return $q;
	}

	public function query($sentence)
	{
		// $wizards = Person::all()->query('WHERE age >= 25 AND experience = false')->fetch();
		//if (0 == count($this->conditionArray)) {
			$token = array('WHERE', 'ORDER BY', 'GROUP BY', 'LIMIT');
			// TODO: split by token and fill values
			$this->condition = $sentence;
			return $this;
		//}
	}

	public function fetch()
	{
		$sql = $this->getSQL();
		$this->data = array();
		$result = Model::getConnection()->query($sql);
		if ($result) {
			if (is_subclass_of($this->entity, 'webapp\db\Model')) {
				$modelClass = new \ReflectionClass($this->entity);
				foreach ($result->GetRows() as $row) {
					$record = $modelClass->newInstanceArgs(array($row, true));
					$this->data[] = $record;
				}
			} else {
				foreach ($result->GetRows() as $row) {
					$this->data[] = (object) $result->GetRows();
				}
			}
		}
		$this->cursor = 0;
		return new RecordSet($this->data);
	}

	public function getSQL()
	{
		// cmd
		$cmd = $this->cmd;

		// $fields
		$fields = $this->fields;

		// table
		$table = 'FROM ' . $this->getTable();

		// where
		if (0 < count($this->conditionArray)) {
			$where = 'WHERE ' . implode(' AND ', $this->conditionArray);
		} else if ('' != $this->condition) {
			$where = 'WHERE ' . $this->condition;	// XXX mange 'WHERE' string
		} else {
			$where = '';
		}

		// order
		if ('' != $this->sort) {
			$order = 'ORDER BY ' . $this->sort;
		} else {
			$order = '';
		}

		// limit
		if ('' != $this->range) {
			$limit = 'LIMIT ' . $this->range;
		} else {
			$limit = '';
		}
		$arr = array($cmd, $fields, $table, $where, $order, $limit);
		return implode(' ', $arr);
	}

	public function __toString()
	{
		return sprintf('<db.Query sql="%s">', $this->getSQL());
	}

	// Itarabable implements -----------------------------
	public function valid()
	{
		if (null == $this->data) {
			$this->fetch();
		}
		return isset($this->data[$this->cursor]);
	}
	// ---------------------------------------------------

	// Countable implements ------------------------------
	public function count()
	{
		if (null == $this->data) {
			$q = clone $this;
			$q->fields = 'COUNT(*)';
			$result = Model::getConnection()->GetOne($q->getSQL());
			return (int) $result;
		}
		return count($this->data);
	}
	// ---------------------------------------------------

	// ArrayAccess implementations -----------------------
	public function offsetGet($offset)
	{
		if (null != $this->data) {
			return $this->data[$offset];
		} else {
			$q = clone $this;
			$row = Model::getConnection()->GetRow($q->limit($offset, 1)->getSQL());
			if (is_subclass_of($this->entity, 'webapp\db\Model')) {
				$modelClass = new \ReflectionClass($this->entity);
				return $modelClass->newInstanceArgs(array($row, true));
			} else {
				return (object) $row;
			}
		}
		return null;
	}

	public function offsetSet($offset, $value)
	{
		return;
	}

	public function offsetUnset($offset)
	{
		return;
	}
	// ---------------------------------------------------

}

class JoinQuery extends Query
{
	protected $joins;

	function __construct()
	{
		// entity must be Model!
		$args = func_get_args();
		$this->entity = array_shift($args);	// the first Model
		if (!is_subclass_of($this->entity, 'webapp\db\Model')) {
			throw new \Exception('JoinQuery accepts only subclass of db.Model');
			return;
		}
		$this->joins = $args;	// rest all Models

		// * -> table1.field1, table1.field2, table2.field1...
		$t1 = call_user_func(array($this->entity, 'getTableName'));
		$fields = call_user_func(array($this->entity, 'getFieldNames'));
		array_walk($fields, function(&$field) use ($t1) {
			$field = $t1 . '.' . $field;
		});

		foreach ($this->joins as $rest) {
			if (!is_subclass_of($rest, 'webapp\db\Model')) {
				throw new \Exception('JoinQuery accepts only subclass of db.Model');
				return;
			}
			$tt = call_user_func(array($rest, 'getTableName'));
			$_fields = call_user_func(array($rest, 'getFieldNames'));
			array_walk($_fields, function(&$field) use ($tt) {
				$field = $tt . '.' . $field;
			});
			$fields = array_merge($fields, $_fields);
		}
		$this->fields = join(', ', $fields);

	}

	public function getTable()
	{
		$t1 = parent::getTable();

		$tables = array();
		$cond = array();
		foreach ($this->joins as $rest) {
			$tt = call_user_func(array($rest, 'getTableName'));
			$tables[] = $tt;
			$cond[] = "{$tt}.id = {$t1}.{$tt}_id";
		}
		// FROM t1 LEFT JOIN (t2, t3, t4) ON (t2.a=t1.a AND t3.b=t1.b AND t4.c=t1.c)
		$ret = $t1 . ' LEFT JOIN (' . join(', ', $tables) . ') ON ' .
			'(' . join(' AND ', $cond) . ')';
		return $ret;
	}
}

class ReferenceProperty
{
	private $model;

	public function __construct($model)
	{
		$this->model = $model;
	}

	public function getModel()
	{
		return $this->model;
	}

	public function getFieldName()
	{
		return call_user_func(array($this->model, 'getTableName')) . '_id';
	}

	public function __toString()
	{
		return sprintf('<db.ReferenceProperty model="%s">', $this->model);
	}
}

class ReferenceListProperty extends Query
{
	private $weakReference;
	private $parent;

	public function __construct($entity, $parent=null, $weakReference=true)
	{
		super::__construct($entity);
		if (null != $parent) {
			$klass = get_class($parent);
			$this->filter('ref_'.call_user_func($klass, 'getTableName').'_id =', $parent->id);
		}
		$this->parent = $parent;
		$this->weakReference = $weakReference;
		if (!$weakReference) {
			$this->fetch();
		}
	}

	public function __toString()
	{
		return '<db.ReferenceList <Person#1>, <Person#2>, <Person#3>, ... 10 more>';
	}
}

abstract class Model
{
	public static $connection;
	public static $autoDDL = false;

	protected static $table;

	protected static $fields = array();
	protected static $indexes = array();

	protected $values = array();
	protected $changes = array();

	protected $managed;

	function __construct($values=null, $managed=false)
	{
		$this->managed = $managed;
		if (null != $values) {
			$this->values = $values;
		}
		if (!$managed) {
			$this->changes = $values;
		}
	}

	public function isManaged()
	{
		return $managed;
	}

	public static function hasRelatedField()
	{
		foreach (static::$fields as $field => $type) {
			if ('@' == substr($type, 0, 1) && '[]' != substr($type, -2)) return true;
		}
		return false;
	}

	public static function getRelatedModels()
	{
		$ret = array();
		foreach (static::$fields as $field => $type) {
			if ('@' == substr($type, 0, 1) && '[]' != substr($type, -2)) {
				$ret[] = substr($type, 1);
			}
		}
		return $ret;
	}

	private static function isRealField($field)
	{
		$type = static::$fields[$field];
		return (null != $type && '@' != substr($type, 0, 1));
	}

	public static function getConnection()
	{
		return static::$connection;
	}

	public static function getTableName()
	{
		if (null != static::$table) {
			return static::$table;
		} else {
			return str_replace('\\','_', get_called_class());
		}
	}

	public static function getFields()
	{
		if (isset(static::$more_fields)) {
			return array_merge(static::$fields, static::$more_fields);
		}
		return static::$fields;
	}

	public static function getFieldNames()
	{
		return array_keys(static::getFields());
	}

	public static function getIndexes()
	{
		if (isset(static::$more_indexes)) {
			return array_merge(static::$indexes, static::$more_indexes);
		}
		return static::$indexes;
	}

	public static function get($id)
	{
		$query = static::all()->filter('id =', $id);
		return $query[0];
		return null;
	}

	public static function create()
	{
		create_table(get_called_class());
	}

	public static function drop()
	{
		drop_table(get_called_class());
	}

	public static function alter($modifies)
	{
		// TODO: implement alter table
		throw new \Exception('alter table method is not implemented yet');
	}

	public static function all()
	{
		if (static::hasRelatedField()) {
			$args = array(get_called_class());
			$args = array_merge($args, static::getRelatedModels());
			$queryReflection = new \ReflectionClass('webapp\db\JoinQuery');
			$query = $queryReflection->newInstanceArgs($args);
			return $query;
		} else {
			return new Query(get_called_class());
		}
	}

	public static function columns($columns)
	{
		$column_arr = explode(',', $columns);
		if (!in_array('id', $column_arr)) {
			array_splice(&$column_arr, 0, 0, 'id');
		}
		$columns = implode(', ', $column_arr);
		return new Query(get_called_class(), $columns);
	}

	public static function query($sentence)
	{
		$q = new Query(get_called_class());
		$q->query($sentence);
		return $q;
	}

	public static function filter($filter)
	{
	}

	public function __isset($field)
	{
		return array_key_exists($field, $this->values);
	}

	public function __get($field)
	{
		if (array_key_exists($field, $this->values)) {
			if (is_a(static::$fields[$field], 'webapp\db\ReferenceProperty')) {
				// return refclass(klass)::get(this->value);
			} else if (is_a(static::$fields[$field], 'webapp\db\ReferenceListProperty')) {
				// return refclass(klass)::filter('reftable_id =', this->id)
			}
			return $this->values[$field];
		} else {
			throw new \Exception('Field not found ' . $field);
		}
		return null;
	}

	public function __set($field, $value)
	{
		if ($field == 'id') {
			throw new \Exception('id can not be modified');
			return;
		}
//		if (!in_array($field, array_keys(static::getFields()))) {
//			throw new \Exception("field $field is not exists");
//			return;
//		}

		if ($this->values[$field] != $value) {	// no changes
			$this->values[$field] = $value;

			if (static::isRealField($field)) {
				$this->changes[$field] = $value;
			} else {
				if (is_subclass_of($value, 'webapp\db\Model') && !$value->isManaged()) {
					$value->put();
				}
				$this->changes[$field.'_id'] = $value->id;
			}
			$this->managed = false;
		}
	}

	public function put()
	{
		if ($this->managed) return;
		if (count($this->changes) > 0) {
			/*
			while ($value = current($this->changes)) {
				print_r($value);
				if (is_subclass_of($value, 'db\Model')) {
					echo key($this->changes);
				}
				next($this->changes);
			}
			*/
			/*
			for ($i = 0, $cnt = count($this->changes); $i < $cnt; $i++) {
			}
			*/
		}
		if (in_array('id', array_keys($this->values))) {	// update
			$result = static::$connection->AutoExecute(
				static::getTableName(),
				$this->changes,
				'UPDATE',
				'id = ' . $this->id
			);
			if ($result) {
				$this->changes = array();
				$this->managed = true;
				return $this->id;
			}
		} else {
			$result = static::$connection->AutoExecute(
				static::getTableName(),
				$this->changes,
				'INSERT'
			);
			if ($result) {
				$insertID = static::$connection->Insert_ID();
				if (false === $insertID) {	// for not supported db (eg. sqlite)
					$insertID = static::$connection->GetOne("SELECT MAX(id) FROM " . static::getTableName());
				}
				$this->values['id'] = (int) $insertID;
				$this->changes = array();
				$this->managed = true;
				return $insertID;
			}
		}
		return false;
	}

	public function delete()
	{
		$sql = sprintf("DELETE FROM %s WHERE id=?", static::getTableName());
		$result = static::getConnection()->Execute($sql, array($this->id));
		return $result;
	}

	public function __toString()
	{
		if (null != $this->id) {
			return sprintf('<%s#%s>', str_replace('\\','.',get_class($this)), $this->id);
		} else {
			return sprintf('<%s table="%s">', str_replace('\\','.',get_class($this)), static::getTableName());
		}
	}
}
/* Usage of db\Model

Movie::filter(Director::get(1));
	// means -> SELECT * FROM Movie, Director WHERE Movie.orm_Director_id = Director.id
	// AND Director.id = 1

Director::get(1)->movies;
	// means -> SELECT * FROM Director WHERE id=1  ==> $a
	// SELECT * FROM Movie WHERE orm_Director_id = $a->id;

Movie::filter(Director::filter("name='김기덕'));
	// if unique(name):
	// SELECT * FROM Director WHERE name='김기덕' => $a
	// SELECT * FROM Movie WHERE orm_Director_id = $a->id;
	// else:
	// SELECT * FROM Director WHERE name='김기덕'  ==> [id1, id2, id3, ...]
	// SELECT * FROM Movie WHERE orm_Director_id in;
*/

function create_table($model, $runSQL=false)
{
	$conn = call_user_func(array($model, 'getConnection'));
	$table = call_user_func(array($model, 'getTableName'));
	$fields = call_user_func(array($model, 'getFields'));
	if ($conn->tableExists($table)) {
		//return;
	}
	if (0 == count($fields)) {
		throw new \Exception("There is no field definitions in model");
		return;
	}
	if (array_key_exists('id', $fields)) {
		throw new \Exception("'id' is reserved field type and automatically created");
		return;
	}
	if ($conn->scheme == 'sqlite') {
		// XXX: sqlite treats only 'INTEGER NOT NULL PK' as auto-incremental field
		$id_fieldtype = 'INTEGER NOT NULL PRIMARY KEY';
	} else {
		$id_fieldtype = 'I PRIMARY AUTO';
	}
	$_fields = array_merge(array('id'=>$id_fieldtype), $fields);
	foreach ($_fields as $field => $type) {
		if ('@' == substr($type, 0, 1)) {
			if ('[]' == substr($type, -2)) {
				// reference list property (? to Many)
				// if table exists:
					// ALTER TABLE ref_table ADD COLUMN {currtable}_id I;
					// ALTER TABLE ref_table ADD CONSTRAINT fk_{currtable}_id
					//     FOREIGN KEY ({currtable}_id) REFERENCES {currtable}(id)
				// else
					// {ref_model}::$fields[] = ReferenceListProperty({
				$field = null;
			} else {
				// reference property (One to One)
				$field .= "_id";
				$type = 'I';
			}
		}
		if (null != $field) {
			$metatype = str_replace(
				array(
					'string', 'char',
					'text', 'varchar', 'long text', 'longtext',
					'binary', 'blob',
					'date',
					'datetime', 'time',
					'integer', 'int',
					'long', 'number', 'float',

					'required', 'not null', 'notnull', '!',
					'default'
				),
				array(
					'C', 'C',
					'X', 'X', 'XL', 'XL',
					'B', 'B',
					'D',
					'T', 'T',
					'I', 'I',
					'N', 'N', 'N',

					'NOTNULL', 'NOTNULL', 'NOTNULL', ' NOTNULL',
					'DEFAULT'
				),
				$type
			);
			$column = $field . ' ' . $metatype;
			$columns[] = $column;
		}
	}

/*
	$sql = sprintf("CREATE TABLE %s (
	%s
);\n",	$table, implode(",\n\t", $columns));
	//echo $sql;
*/
	$dict = \NewDataDictionary($conn->getConnection());
	$cols = implode(",\n", $columns);
	$sqlarr = $dict->CreateTableSQL($table, $cols);
	$result = $dict->ExecuteSQLArray($sqlarr);
	if ($result) {
		$conn->addTable($table);
	} else {
		print_r($conn->ErrorMsg());
	}
}

function drop_table($model)
{
	$table = call_user_func(array($model, 'getTableName'));
	$conn = call_user_func(array($model, 'getConnection'));
	$sql = sprintf("DROP TABLE %s", $table);
	$conn->query($sql);
}

function create_all()
{
	$classes = get_declared_classes();
	if (count($classes) > 0) {
		foreach ($classes as $class) {
			if (is_subclass_of($class, 'webapp\db\Model')) {
				$ref = new \ReflectionClass($class);
				call_user_func(array($class, 'create'));
			}
		}
	}
}
?>
