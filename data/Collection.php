<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\data;

/**
 * The `Collection` class extends the generic `lithium\util\Collection` class to provide
 * context-specific features for working with sets of data persisted by a backend data store. This
 * is a general abstraction that operates on arbitrary sets of data from either relational or
 * non-relational data stores.
 */
abstract class Collection extends \lithium\util\Collection {

	/**
	 * A reference to this object's parent `Document` object.
	 *
	 * @var object
	 */
	protected $_parent = null;

	/**
	 * If this `Collection` instance has a parent document (see `$_parent`), this value indicates
	 * the key name of the parent document that contains it.
	 *
	 * @see lithium\data\Collection::$_parent
	 * @var string
	 */
	protected $_pathKey = null;

	/**
	 * The fully-namespaced class name of the model object to which this entity set is bound. This
	 * is usually the model that executed the query which created this object.
	 *
	 * @var string
	 */
	protected $_model = null;

	/**
	 * A reference to the query object that originated this entity set; usually an instance of
	 * `lithium\data\model\Query`.
	 *
	 * @see lithium\data\model\Query
	 * @var object
	 */
	protected $_query = null;

	/**
	 * A pointer or resource that is used to load entities from the backend data source that
	 * originated this collection.
	 *
	 * @var resource
	 */
	protected $_result = null;

	/**
	 * Indicates whether the current position is valid or not. This overrides the default value of
	 * the parent class.
	 *
	 * @var boolean
	 * @see lithium\util\Collection::valid()
	 */
	protected $_valid = true;

	/**
	 * Contains an array of backend-specific statistics generated by the query that produced this
	 * `Collection` object. These stats are accessible via the `stats()` method.
	 *
	 * @see lithium\data\Collection::stats()
	 * @var array
	 */
	protected $_stats = array();

	/**
	 * By default, query results are not fetched until the collection is iterated. Set to `true`
	 * when the collection has begun iterating and fetching entities.
	 *
	 * @see lithium\data\Collection::rewind()
	 * @see lithium\data\Collection::_populate()
	 * @var boolean
	 */
	protected $_hasInitialized = false;

	/**
	 * Indicates whether this array was part of a document loaded from a data source, or is part of
	 * a new document, or is in newly-added field of an existing document.
	 *
	 * @var boolean
	 */
	protected $_exists = false;

	/**
	 * If the `Collection` has a schema object assigned (rather than loading one from a model), it
	 * will be assigned here.
	 *
	 * @see lithium\data\Schema
	 * @var object
	 */
	protected $_schema = null;

	/**
	 * Holds an array of values that should be processed on initialization.
	 *
	 * @var array
	 */
	protected $_autoConfig = array(
		'data', 'model', 'result', 'query', 'parent', 'stats', 'pathKey', 'exists', 'schema'
	);

	/**
	 * Class constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$defaults = array('data' => array(), 'model' => null);
		parent::__construct($config + $defaults);
	}

	protected function _init() {
		parent::_init();

		foreach (array('data', 'classes', 'model', 'result', 'query') as $key) {
			unset($this->_config[$key]);
		}
		if ($schema = $this->schema()) {
			$exists = isset($this->_config['exists']) ? $this->_config['exists'] : null;
			$pathKey = $this->_pathKey;
			$this->_data = $schema->cast($this, $this->_data, compact('exists', 'pathKey'));
		}
	}

	/**
	 * Configures protected properties of a `Collection` so that it is parented to `$parent`.
	 *
	 * @param object $parent
	 * @param array $config
	 * @return void
	 */
	public function assignTo($parent, array $config = array()) {
		foreach ($config as $key => $val) {
			$this->{'_' . $key} = $val;
		}
		$this->_parent =& $parent;
	}

	/**
	 * Returns the model which this particular collection is based off of.
	 *
	 * @return string The fully qualified model class name.
	 */
	public function model() {
		return $this->_model;
	}
	
	/**
	 * Returns the object's parent `Document` object.
	 *
	 * @return object
	 */
	public function parent() {
		return $this->_parent;
	}
	
	public function schema($field = null) {
		$schema = array();

		switch (true) {
			case ($this->_schema):
				$schema = $this->_schema;
			break;
			case ($model = $this->_model):
				$schema = $model::schema();
			break;
		}
		if ($schema) {
			return $field ? $schema->fields($field) : $schema;
		}
	}

	/**
	 * Returns a boolean indicating whether an offset exists for the
	 * current `Collection`.
	 *
	 * @param string $offset String or integer indicating the offset or
	 *               index of an entity in the set.
	 * @return boolean Result.
	 */
	public function offsetExists($offset) {
		return ($this->offsetGet($offset) !== null);
	}

	/**
	 * Reset the set's iterator and return the first entity in the set.
	 * The next call of `current()` will get the first entity in the set.
	 *
	 * @return object Returns the first `Entity` instance in the set.
	 */
	public function rewind() {
		$this->_valid = (reset($this->_data) || count($this->_data));

		if (!$this->_valid && !$this->_hasInitialized) {
			$this->_hasInitialized = true;

			if ($entity = $this->_populate()) {
				$this->_valid = true;
				return $entity;
			}
		}
		return current($this->_data);
	}

	/**
	 * Overrides parent `find()` implementation to enable key/value-based filtering of entity
	 * objects contained in this collection.
	 *
	 * @param mixed $filter Callback to use for filtering, or array of key/value pairs which entity
	 *              properties will be matched against.
	 * @param array $options Options to modify the behavior of this method. See the documentation
	 *              for the `$options` parameter of `lithium\util\Collection::find()`.
	 * @return mixed The filtered items. Will be an array unless `'collect'` is defined in the
	 * `$options` argument, then an instance of this class will be returned.
	 */
	public function find($filter, array $options = array()) {
		if (is_array($filter)) {
			$filter = $this->_filterFromArray($filter);
		}
		return parent::find($filter, $options);
	}

	/**
	 * Overrides parent `first()` implementation to enable key/value-based filtering.
	 *
	 * @param mixed $filter In addition to a callback (see parent), can also be an array where the
	 *              keys and values must match the property values of the objects being inspected.
	 * @return object Returns the first object found matching the filter criteria.
	 */
	public function first($filter = null) {
		return parent::first(is_array($filter) ? $this->_filterFromArray($filter) : $filter);
	}

	/**
	 * Creates a filter based on an array of key/value pairs that must match the items in a
	 * `Collection`.
	 *
	 * @param array $filter An array of key/value pairs used to filter `Collection` items.
	 * @return closure Returns a closure that wraps the array and attempts to match each value
	 *         against `Collection` item properties.
	 */
	protected function _filterFromArray(array $filter) {
		return function($item) use ($filter) {
			foreach ($filter as $key => $val) {
				if ($item->{$key} != $val) {
					return false;
				}
			}
			return true;
		};
	}

	/**
	 * Returns meta information for this `Collection`.
	 *
	 * @return array
	 */
	public function meta() {
		return array('model' => $this->_model);
	}

	/**
	 * Applies a callback to all data in the collection.
	 *
	 * Overridden to load any data that has not yet been loaded.
	 *
	 * @param callback $filter The filter to apply.
	 * @return object This collection instance.
	 */
	public function each($filter) {
		if (!$this->closed()) {
			while ($this->next()) {}
		}
		return parent::each($filter);
	}

	/**
	 * Applies a callback to a copy of all data in the collection
	 * and returns the result.
	 *
	 * Overriden to load any data that has not yet been loaded.
	 *
	 * @param callback $filter The filter to apply.
	 * @param array $options The available options are:
	 *              - `'collect'`: If `true`, the results will be returned wrapped
	 *              in a new `Collection` object or subclass.
	 * @return object The filtered data.
	 */
	public function map($filter, array $options = array()) {
		$defaults = array('collect' => true);
		$options += $defaults;

		if (!$this->closed()) {
			while ($this->next()) {}
		}
		$data = parent::map($filter, $options);

		if ($options['collect']) {
			foreach (array('_model', '_schema', '_pathKey') as $key) {
				$data->{$key} = $this->{$key};
			}
		}
		return $data;
	}

	/**
	 * Sorts the objects in the collection, useful in situations where
	 * you are already using the underlying datastore to sort results.
	 *
	 * Overriden to load any data that has not yet been loaded.
	 *
	 * @param mixed $field The field to sort the data on, can also be a callback
	 * to a custom sort function.
	 * @param array $options The available options are:
	 *              - No options yet implemented
	 * @return $this, useful for chaining this with other methods.
	 */
	public function sort($field = 'id', array $options = array()) {
		$this->offsetGet(null);

		if (is_string($field)) {
			$sorter = function ($a, $b) use ($field) {
				if (is_array($a)) {
					$a = (object) $a;
				}

				if (is_array($b)) {
					$b = (object) $b;
				}

				return strcmp($a->$field, $b->$field);
			};
		} else if (is_callable($field)) {
			$sorter = $field;
		}

		return parent::sort($sorter, $options);
	}

	/**
	 * Converts the current state of the data structure to an array.
	 *
	 * @return array Returns the array value of the data in this `Collection`.
	 */
	public function data() {
		return $this->to('array');
	}

	/**
	 * Adds the specified object to the `Collection` instance, and assigns associated metadata to
	 * the added object.
	 *
	 * @param string $offset The offset to assign the value to.
	 * @param mixed $data The entity object to add.
	 * @return mixed Returns the set `Entity` object.
	 */
	public function offsetSet($offset, $data) {
		if (is_array($data) && ($schema = $this->schema())) {
			$data = $schema->cast($this, $data);
		}
		return $this->_data[] = $data;
	}
	
	/**
	 * Return's the pointer or resource that is used to load entities from the backend 
	 * data source that originated this collection. This is useful in many cases for
	 * additional methods related to debugging queries.
	 * 
	 * @return object The pointer or resource from the data source
	*/
	public function result() {
		return $this->_result;
	}

	/**
	 * Gets the stat or stats associated with this `Collection`.
	 *
	 * @param string $name Stat name.
	 * @return mixed Single stat if `$name` supplied, else all stats for this
	 *               `Collection`.
	 */
	public function stats($name = null) {
		if ($name) {
			return isset($this->_stats[$name]) ? $this->_stats[$name] : null;
		}
		return $this->_stats;
	}

	/**
	 * Executes when the associated result resource pointer reaches the end of its data set. The
	 * resource is freed by the connection, and the reference to the connection is unlinked.
	 *
	 * @return void
	 */
	public function close() {
		if (!empty($this->_result)) {
			$this->_result = null;
		}
	}

	/**
	 * Checks to see if this entity has already fetched all available entities and freed the
	 * associated result resource.
	 *
	 * @return boolean Returns true if all entities are loaded and the database resources have been
	 *         freed, otherwise returns false.
	 */
	public function closed() {
		return empty($this->_result);
	}

	/**
	 * Ensures that the data set's connection is closed when the object is destroyed.
	 *
	 * @return void
	 */
	public function __destruct() {
		$this->close();
	}

	/**
	 * A method to be implemented by concrete `Collection` classes which, provided a reference to a
	 * backend data source, and a resource representing a query result cursor, fetches new result
	 * data and wraps it in the appropriate object type, which is added into the `Collection` and
	 * returned.
	 *
	 * @param mixed $data Data (in an array or object) that is manually added to the data
	 *              collection. If `null`, data is automatically fetched from the associated backend
	 *              data source, if available.
	 * @param mixed $key String, integer or array key representing the unique key of the data
	 *              object. If `null`, the key will be extracted from the data passed or fetched,
	 *              using the associated `Model` class.
	 * @return object Returns a `Record` or `Document` object, or other `Entity` object.
	 */
	abstract protected function _populate($data = null, $key = null);
}

?>