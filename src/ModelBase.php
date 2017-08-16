<?php


namespace Mishterk\WP\Tools\DB;


abstract class ModelBase {


	/** @var \wpdb */
	public $db;

	/** @var Adaptor */
	public $adaptor;

	/** @var bool Set this to true in your child class if you want to allow dropping of this table on plugin deactivation */
	public $drop_on_deactivation = false;

	/** @var string Table version. Use this for updating table schema */
	public $version = '0.0.1';


	/**
	 * Model constructor.
	 *
	 * @param array $dependencies Optional array for overriding default dependencies
	 */
	public function __construct( Array $dependencies = [] ) {
		global $wpdb;

		$dependencies = array_merge( [
			'db'      => '',
			'adaptor' => '',
		], $dependencies );

		$this->db      = $dependencies['db'] ?: $wpdb;
		$this->adaptor = $dependencies['adaptor'] ?: new Adaptor();
	}

	/**
	 * Must return the schema for this table (CREATE TABLE SQL Statement)
	 *
	 * @return mixed
	 */
	abstract function schema();


	/**
	 * Must return the table name without the prefix
	 *
	 * @return mixed
	 */
	abstract function table_name();


	/**
	 * Returns an associative array of columns and their formats. e.g; ['col_name' => '%s', 'col2_name' => '%d']
	 *
	 * @return array
	 */
	abstract function columns();


	/**
	 * If columns have defaults, the required format here is ['col_name' => 'default_val']
	 *
	 * Note: if using the timestamp_field() method to generate a timestamp field in your model's schema, it's not
	 * necessary to set default values for that particular field.
	 *
	 * @return array
	 */
	abstract function column_defaults();


	/**
	 * Returns an array containing the primary key. Multiple columns are supported for composite keys, but should be
	 * in order of their composition to ensure optimal query performance.
	 *
	 * @return string|array
	 */
	abstract function primary_key();


	/**
	 * Returns the full table name (with WP table prefix)
	 *
	 * @return string
	 */
	public function full_table_name() {
		return $this->db->prefix . $this->table_name();
	}


	/**
	 * Creates/updates the table using our adaptor's dbDelta()
	 *
	 * @return bool
	 */
	public function create_table() {
		$result = $this->adaptor->dbDelta( $this->schema() );

		return isset( $result[ $this->full_table_name() ] );
	}


	/**
	 * Removes the table from the DB
	 *
	 * @param bool $force
	 *
	 * @return bool TRUE if it did, FALSE if it didn't
	 */
	public function drop_table( $force = false ) {
		if ( $this->drop_on_deactivation OR $force ) {
			return $this->db->query( "DROP TABLE {$this->full_table_name()};" );
		}

		return false;
	}


	/**
	 * Creates a timestamp schema field
	 *
	 * @param string $name The field/column name
	 * @param bool $update Whether or not the timestamp updates when the row is updated
	 *
	 * @return string
	 */
	function timestamp_field( $name = 'created_at', $update = false ) {
		return "$name DATETIME DEFAULT CURRENT_TIMESTAMP" . ( $update ? " ON UPDATE CURRENT_TIMESTAMP" : '' );
	}


	/**
	 * Checks if this table exists
	 *
	 * @return bool
	 */
	public function table_exists() {
		$table = $this->full_table_name();
		$query = "SHOW TABLES LIKE '%s'";

		return $this->db->get_var( $this->db->prepare( $query, $table ) ) === $table;

	}


	/**
	 * Get the last insert ID
	 *
	 * @return int
	 */
	public function insert_id() {
		return $this->db->insert_id;
	}


	/**
	 * Inserts a new row
	 *
	 * @param array $data Associative array to insert in the format ['column_name' => 'value']
	 *
	 * @return bool
	 */
	public function insert( Array $data ) {
		$data    = $this->set_missing_defaults( $data );
		$data    = $this->remove_extraneous_fields( $data );
		$formats = $this->get_ordered_formats( $data );

		return (bool) $this->db->insert( $this->full_table_name(), $data, $formats );
	}


	/**
	 * Updates an existing row based on an array of where conditions in the format ['col_id' => '1', 'col_name' => 'example']
	 *
	 * @param array $data
	 * @param array $where
	 *
	 * @return bool
	 */
	public function update( Array $data, Array $where ) {
		$data    = $this->set_missing_defaults( $data );
		$data    = $this->remove_extraneous_fields( $data );
		$formats = $this->get_ordered_formats( $data );

		return (bool) $this->db->update( $this->full_table_name(), $data, $where, $formats );
	}


	/**
	 * Inserts a row if it doesn't already exist, updates it if it does.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function insert_or_update( Array $data ) {
		$data        = $this->set_missing_defaults( $data );
		$data        = $this->remove_extraneous_fields( $data );
		$formats     = $this->get_ordered_formats( $data );
		$fields      = array_map( function ( $v ) {
			$v = esc_sql( $v );

			return "`$v`";
		}, array_keys( $data ) );
		$values      = array_values( $data );
		$fields_str  = implode( ',', $fields );
		$n           = count( $values );
		$formats_str = implode( ',', array_slice( $formats, 0, $n ) );

		$SQL   = "INSERT INTO {$this->full_table_name()} ($fields_str) VALUES ($formats_str) ON DUPLICATE KEY UPDATE";
		$query = $this->db->prepare( $SQL, $values );

		$c = 0;
		foreach ( $fields as $field ) {
			$c ++;
			$query .= " $field = VALUES($field)";
			$query .= ( $n === $c ? ';' : ',' );
		}

		return (bool) $this->db->query( $query );
	}

// todo - maybe make this so
//	public function insert_many( Array $data ) {
//		// todo - loop through all data arrays merging them onto default structured array
//		$cols     = $this->columns();
//		$defaults = $this->column_defaults();
////		$data = array_map( function ( $item ) {
////			$d = $this->normalize_data( $item );
////			$f = array_slice( $this->get_ordered_formats( $d ), 0, count( $d ) );
////
////			return [ 'data' => $d, 'formats' => $f ];
////		}, $data );
////
////
////
////		return false;
//	}


	/**
	 * Takes an array of input data (single row) and plugs in missing defaults as set in the column_defaults() method
	 *
	 * @see column_defaults()
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function set_missing_defaults( Array $data ) {
		return array_merge( $this->column_defaults(), $data );
	}


	/**
	 * Takes an array of input data (single row) and removes any extraneous fields that aren't definied in the columns()
	 * method.
	 *
	 * @see columns()
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function remove_extraneous_fields( Array $data ) {
		return array_intersect_key( $data, $this->columns() );
	}


	/**
	 * Takes a normalised array of input data (single row) in any order and returns a correctly ordered formats array
	 * for passing to our DB object
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_ordered_formats( Array $data ) {
		$formats = $this->columns();
		$keys    = array_keys( $data );
		$formats = array_merge( array_flip( $keys ), $formats );

		return $formats;
	}


	/**
	 * Counts all rows in the table
	 *
	 * @return null|string
	 */
	public function count() {
		$result = (int) $this->db->get_var( "SELECT count(*) FROM {$this->full_table_name()};" );

		return $result ?: 0;
	}


	/**
	 * Checks inbound keys to ensure we have the correct number. This is mainly for use by $this->get(), which allows
	 * for single keys and arrays of keys to be passed in, depending on the model's primary key structure.
	 *
	 * @param int|string|array $key_or_array
	 *
	 * @return bool
	 */
	public function validate_inbound_primary_key( $key_or_array ) {
		$primaryKey  = $this->primary_key();
		$nPrimaryKey = count( $primaryKey );
		if ( is_array( $key_or_array ) ) {
			if ( $nPrimaryKey !== count( $key_or_array ) ) {
				return false;
			}
			if ( $this->is_associative_array( $key_or_array ) ) {
				return empty( array_diff_key( $key_or_array, array_flip( $primaryKey ) ) );
			}

			return true;
		} else {
			return $nPrimaryKey === 1;
		}
	}


	/**
	 * Checks if an array is associative. This will return true if any or all items have an associative key.
	 *
	 * @param $array
	 *
	 * @return bool
	 */
	protected function is_associative_array( $array ) {
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}


	/**
	 * Finds a single row based on its primary key. If the model has a single key, a single value is expected. If the model's
	 * primary key is a composite, an array with the exact same number of values is expected. This will also accept
	 * an associative array. @see $this->primary_key()
	 *
	 * @param mixed $key_or_array
	 *
	 * @return array|bool|null Array representing the row on success; bool FALSE on failure; NULL of row could not be found.
	 */
	public function find( $key_or_array ) {
		if ( ! $this->validate_inbound_primary_key( $key_or_array ) ) {
			trigger_error( 'Error: arguments provided to $this->get() were incorrect. Check the primary key/s required for this model.' );

			return false;
		}

		$key_or_array = ( is_array( $key_or_array ) and $this->is_associative_array( $key_or_array ) )
			? array_merge( array_flip( $this->primary_key() ), $key_or_array )
			: $key_or_array = array_combine( $this->primary_key(), (array) $key_or_array );

		$where = $this->build_where_clause( (array) $key_or_array );
		$query = "SELECT * FROM `{$this->full_table_name()}` {$where};";

		return $this->db->get_row( $query, ARRAY_A ) ?: false;
	}


	/**
	 * Takes a value and guesses its format in preparation for WP's subset of sprintf() formats.
	 *
	 * @param $value
	 *
	 * @return string '%s'|'%f'|'%d'
	 */
	public function guess_format( $value ) {
		if ( is_numeric( $value ) ) {
			return ( floor( $value ) == $value ) ? '%d' : '%f';
		}

		return '%s';
	}


	/**
	 * Gets the columns defined format, if it exists in the array returned by $this->columns(), or FALSE if column
	 * is not defined.
	 *
	 * @param $column
	 *
	 * @return bool|string
	 */
	public function column_format( $column ) {
		$formats = $this->columns();

		return isset( $formats[ $column ] )
			? $formats[ $column ]
			: false;
	}


	/**
	 * Builds a WHERE clause with given key value array. Not dependent on the current model's defied columns, but it will
	 * attempt to match formats to defined column formats before making a guess.
	 *
	 * @param array $args Associate array in the format ['column_name' => 'value']
	 *
	 * @return string
	 */
	public function build_where_clause( Array $args ) {
		$clause = '';
		$c      = 0;
		foreach ( $args as $key => $val ) {
			$format = $this->column_format( $key ) ?: $this->guess_format( $val );
			$clause .= ( $c === 0 ) ? 'WHERE' : ' AND';
			$clause .= $this->db->prepare( " `$key` = $format", $val );
			$c ++;
		}

		return $clause;
	}


	/**
	 * Finds multiple rows based on provided associative array
	 *
	 * @param array $args
	 *
	 * @return array|bool
	 */
	public function find_where( Array $args, $limit = 0 ) {
		if ( ! $this->is_associative_array( $args ) ) {
			return false;
		}

		$where = $this->build_where_clause( $args );
		$query = "SELECT * FROM {$this->full_table_name()} $where";

		if ( $limit > 0 ) {
			$query .= $this->db->prepare( " LIMIT %d", $limit );
		}

		$query .= ';';

		return $this->db->get_results( $query, ARRAY_A ) ?: [];
	}

//	public function get_by() {
//	}
//
//	public function get_column() {
//	}
//
//	public function get_column_by() {
//	}

// TODO deletes single row based on primary key (see find method for foundation)
//public function delete( ){
//}


	/**
	 * Deletes rows based on provided associative array
	 *
	 * @param array $args
	 *
	 * @return bool
	 */
	public function delete_where( Array $args ) {
		if ( ! $this->is_associative_array( $args ) ) {
			return false;
		}

		$where = $this->build_where_clause( $args );
		$query = "DELETE FROM {$this->full_table_name()} $where;";

		return (bool) $this->db->query( $query );
	}


}