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
	 * Must return the table name without the prefix. Don't use backticks here – use them directly in your schema.
	 *
	 * @return string
	 */
	abstract function table_name();


	/**
	 * Returns an associative array of columns and their formats. e.g; ['col_name' => '%s', 'col2_name' => '%d']
	 * Don't use backticks here – use them directly in your schema.
	 *
	 * @return array
	 */
	abstract function columns();


	/**
	 * If columns have defaults, the required format here is ['col_name' => 'default_val']
	 * Don't use backticks here – use them directly in your schema.
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
	 * @return array
	 */
	abstract function primary_key();


	/**
	 * Returns the full table name (with WP table prefix). Don't use backticks here – use them directly in your schema.
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
		$name   = $this->full_table_name();

		return isset( $result[ $name ] ) or isset( $result["`$name`"] );
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
			return $this->db->query( "DROP TABLE `{$this->full_table_name()}`;" );
		}

		return $this->handle_error( '', "{$this->full_table_name()} table could not be dropped due to \$this->drop_on_deactivation set to true" );
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
		return "`$name` TIMESTAMP DEFAULT CURRENT_TIMESTAMP" . ( $update ? " ON UPDATE CURRENT_TIMESTAMP" : '' );
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
	 * @param array $row Associative array to insert in the format ['column_name' => 'value']
	 *
	 * @return bool
	 */
	public function insert( Array $row ) {
		$row     = $this->normalise_row( $row );
		$formats = $this->get_ordered_formats( $row );

		return (bool) $this->db->insert( $this->full_table_name(), $row, $formats );
	}


	/**
	 * Updates an existing row based on an array of where conditions in the format ['col_id' => '1', 'col_name' => 'example']
	 *
	 * @param array $row
	 * @param array $where
	 *
	 * @return bool
	 */
	public function update( Array $row, Array $where ) {
		$row     = $this->normalise_row( $row );
		$formats = $this->get_ordered_formats( $row );

		return (bool) $this->db->update( $this->full_table_name(), $row, $where, $formats );
	}


	/**
	 * Takes an array of field names, wraps them in backticks, then implodes them ready for passing to an insert
	 * statement.
	 *
	 * @param array $field_names
	 *
	 * @return string
	 */
	public function prepare_fields_string( Array $field_names ) {
		return implode( ',', array_map( function ( $v ) {
			$v = esc_sql( $v );

			return "`$v`";
		}, $field_names ) );
	}


	/**
	 * Inserts a row if it doesn't already exist, updates it if it does.
	 *
	 * @param array $row
	 *
	 * @return bool
	 */
	public function insert_or_update( Array $row ) {
		$row         = $this->normalise_row( $row );
		$formats     = $this->get_ordered_formats( $row );
		$fields      = array_keys( $row );
		$fields_str  = $this->prepare_fields_string( $fields );
		$values      = array_values( $row );
		$n           = count( $values );
		$formats_str = implode( ',', array_slice( $formats, 0, $n ) );

		$SQL   = "INSERT INTO `{$this->full_table_name()}` ($fields_str) VALUES ($formats_str) ON DUPLICATE KEY UPDATE";
		$query = $this->db->prepare( $SQL, $values );

		$c = 0;
		foreach ( $fields as $field ) {
			$c ++;
			$query .= " `$field` = VALUES(`$field`)";
			$query .= ( $n === $c ? ';' : ',' );
		}

		return (bool) $this->db->query( $query );
	}


	/**
	 * Normalises a single data set (row) by populating defaults where appropriate, and removing extraneous fields.
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function normalise_row( Array $row ) {
		$row = $this->set_missing_defaults( $row );
		$row = $this->remove_extraneous_fields( $row );

		return $row;
	}


	/**
	 * Accepts a multi-dimensional array of data sets (rows) and normalises them all
	 *
	 * @param array $rows
	 *
	 * @return array
	 */
	public function normalise_rows( Array $rows ) {
		return array_map( [ $this, 'normalise_row' ], $rows );
	}


	/**
	 * Validates a multi-dimensional array of data sets (multiple rows) by checking the following;
	 * 1. each row has a valid primary key;
	 * 2. each 'row' has the same number of items;
	 * 3. row keys are in the same order;
	 * 4. the primary key isn't duplicated within the array.
	 *
	 * These are all necessary conditions for inserting many rows at once via an SQL query.
	 *
	 * @param array $rows
	 *
	 * @return bool
	 */
	public function validate_rows( Array $rows ) {

		$counts          = [];
		$keys            = [];
		$keys_serialised = [];
		$primary         = [];

		$primary_key   = array_flip( (array) $this->primary_key() );
		$n_primary_key = count( $primary_key );

		foreach ( $rows as $row ) {
			$prim_key_values = array_intersect_key( $row, $primary_key );
			// 1. bail if row doesn't have primary keys
			if ( count( $prim_key_values ) !== $n_primary_key ) {
				return $this->handle_error( '', 'Primary key/s not in data set' );
			}
			$counts[ count( $row ) ] = 1;
			$primary[]               = serialize( array_merge( $primary_key, array_intersect_key( $row, $primary_key ) ) );
			$_keys                   = array_keys( $row );
			$keys[]                  = $_keys;
			$keys_serialised[]       = serialize( $_keys );
		}

		// 2. check consistent number of items in rows
		if ( count( $counts ) > 1 ) {
			return $this->handle_error( '', 'Rows do not contain consistent number of fields' );
		}

		// 3. check key structure is the same
		if ( count( array_unique( $keys_serialised ) ) > 1 ) {
			return $this->handle_error( '', 'Key structure is not consistent between rows' );
		}

		// 4. check for primary key duplicates
		if ( count( $primary ) !== count( array_unique( $primary ) ) ) {
			return $this->handle_error( '', 'Primary key/s were duplicated across set of rows' );
		}

		return true;
	}


	/**
	 * Inserts multiple rows based on a consistent multi-dimensional array (an array of rows). The data provided needs
	 * to be structured consistently; that is, each row needs to have the same number of items with the keys in the same
	 * order. Each row also needs to contain the primary key (single or composite) and cannot contain key duplicates.
	 *
	 * @param array $rows
	 *
	 * @return bool
	 */
	public function insert_rows( Array $rows ) {

		$rows = $this->normalise_rows( $rows );
		if ( ! $this->validate_rows( $rows ) ) {
			return $this->handle_error( '', 'Rows could not be inserted due to validation error' );
		}

		$formats     = $this->get_ordered_formats( $rows[0] );
		$fields      = array_keys( $rows[0] );
		$fields_str  = $this->prepare_fields_string( $fields );
		$n_rows      = count( $rows );
		$n_fields    = count( $rows[0] );
		$formats_str = implode( ',', array_slice( $formats, 0, $n_fields ) );

		$SQL = "INSERT INTO `{$this->full_table_name()}` ($fields_str) VALUES";

		$c = 0;
		foreach ( $rows as $row ) {
			$c ++;
			$SQL .= $this->db->prepare( " ($formats_str)", $row );
			$SQL .= ( $n_rows === $c ? ';' : ',' );
		}

		return (bool) $this->db->query( $SQL );
	}


	/**
	 * TODO this method is a combinations of copy/paste parts of insert_rows() and insert_or_update(). There is opportunity here for abstraction.
	 *
	 * As per @see insert_rows(), only this method will update any records that are already stored in the table. Use
	 * this only when necessary, as the insert_rows() method has less to do and will, therefore, be a more efficient
	 * option when you know you are dealing with new data.
	 *
	 * @param array $rows
	 *
	 * @return bool|mixed
	 */
	public function insert_or_update_rows( Array $rows ) {
		$rows = $this->normalise_rows( $rows );
		if ( ! $this->validate_rows( $rows ) ) {
			return $this->handle_error( '', 'Rows could not be inserted due to validation error' );
		}

		$formats     = $this->get_ordered_formats( $rows[0] );
		$fields      = array_keys( $rows[0] );
		$fields_str  = $this->prepare_fields_string( $fields );
		$n_rows      = count( $rows );
		$n_fields    = count( $rows[0] );
		$formats_str = implode( ',', array_slice( $formats, 0, $n_fields ) );

		$SQL = "INSERT INTO `{$this->full_table_name()}` ($fields_str) VALUES";

		// prep data sets
		$c = 0;
		foreach ( $rows as $row ) {
			$c ++;
			$SQL .= $this->db->prepare( " ($formats_str)", $row );
			$SQL .= ( $n_rows === $c ? '' : ',' );
		}

		// on duplicate handling
		$SQL .= " ON DUPLICATE KEY UPDATE";

		$c = 0;
		foreach ( $fields as $field ) {
			$c ++;
			$SQL .= " `$field` = VALUES(`$field`)";
			$SQL .= ( $n_fields === $c ? ';' : ',' );
		}

		return (bool) $this->db->query( $SQL );
	}


	/**
	 * Takes an array of input data (single row) and plugs in missing defaults as set in the column_defaults() method
	 *
	 * @see column_defaults()
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function set_missing_defaults( Array $row ) {
		return array_merge( $this->column_defaults(), $row );
	}


	/**
	 * Takes an array of input data (single row) and removes any extraneous fields that aren't definied in the columns()
	 * method.
	 *
	 * @see columns()
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function remove_extraneous_fields( Array $row ) {
		return array_intersect_key( $row, $this->columns() );
	}


	/**
	 * Takes a normalised array of input data (single row) in any order and returns a correctly ordered formats array
	 * for passing to our DB object
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function get_ordered_formats( Array $row ) {
		$formats = $this->columns();
		$keys    = array_keys( $row );
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
				return $this->handle_error( '', 'Primary key did not validate due to incorrect number of elements' );
			}
			if ( $this->is_associative_array( $key_or_array ) ) {
				return empty( array_diff_key( $key_or_array, array_flip( $primaryKey ) ) )
				       or $this->handle_error( '', 'Primary key did not validate due to incorrect keys' );
			}

			return true;
		} else {
			return $nPrimaryKey === 1
			       or $this->handle_error( '', 'Primary key did not validate due to too many elements – expected 1' );
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
			return $this->handle_error( '', 'Error: arguments provided to $this->get() were incorrect. Check the primary key/s required for this model.' );
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
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return array|bool
	 */
	public function find_where( Array $args, $limit = 0, $offset = 0 ) {
		if ( ! $this->is_associative_array( $args ) ) {
			return $this->handle_error( '', __METHOD__ . ' did not run due to $args variable not being an associative array' );
		}

		$where = $this->build_where_clause( $args );
		$query = "SELECT * FROM `{$this->full_table_name()}` $where";

		if ( $limit > 0 ) {
			$query .= $this->db->prepare( " LIMIT %d", $limit );
		}

		if ( $offset > 0 ) {
			$query .= $this->db->prepare( " OFFSET %d", $offset );
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
			return $this->handle_error( '', __METHOD__ . ' did not run due to $args variable not being an associative array' );
		}

		$where = $this->build_where_clause( $args );
		$query = "DELETE FROM `{$this->full_table_name()}` $where;";

		return (bool) $this->db->query( $query );
	}


	/**
	 * Error handler. Loosely based on WP_Error (takes similar args), but you can override this if you want to do
	 * something different with error messages.
	 *
	 * @param string|int $code
	 * @param string $message
	 * @param string|array $data
	 * @param mixed $return Set a return value
	 *
	 * @return mixed
	 */
	protected function handle_error( $code = '', $message = '', $data = '', $return = false ) {
		$code = $code ? "[Code:$code] " : '';
		$data = ! is_array( $data ) ? $data : json_encode( $data );
		$data = $data ? " | $data" : '';
		trigger_error( $code . $message . $data );

		return $return;
	}


}