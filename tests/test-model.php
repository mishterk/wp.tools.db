<?php
/**
 * Class SampleTest
 *
 * @package Pdk_Wp_Db_Kit
 */

/**
 * Sample test case.
 */
class ModelTest extends WP_UnitTestCase {


	public $table_name;
	public $prefix;


	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		$model = ModelFactory::getTestModel();
		$model->create_table();
		$modelC = ModelFactory::getTestModelCompositeKey();
		$modelC->create_table();
	}

	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
		$model = ModelFactory::getTestModel();
		$model->drop_table();
		$modelC = ModelFactory::getTestModelCompositeKey();
		$modelC->drop_table();
	}

	public function setUp() {
		parent::setUp();
		$model            = ModelFactory::getTestModel();
		$this->prefix     = $model->db->prefix;
		$this->table_name = 'model_table';
	}

	function test_table_names() {
		$model = ModelFactory::getTestModel();
		$this->assertSame( $this->table_name, $model->table_name() );
		$this->assertSame( "{$this->prefix}{$this->table_name}", $model->full_table_name() );
	}

	function test_table_exists() {
		$model      = ModelFactory::getTestModel();
		$tableQuery = $model->db->get_var( "SHOW TABLES LIKE '{$this->prefix}{$this->table_name}';" );
		$this->assertSame( $tableQuery, $this->prefix . $this->table_name );
		$this->assertTrue( $model->table_exists() );
	}

	function test_timestamp_field() {
		$model = ModelFactory::getTestModel();
		$this->assertInternalType( 'string', $model->timestamp_field() );
		$this->assertSame( "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $model->timestamp_field() );
		$this->assertSame( "`custom_column_name` TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $model->timestamp_field( 'custom_column_name' ) );
		$this->assertSame( "`t` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", $model->timestamp_field( 't', true ) );
	}

	function test_insert() {
		$model  = ModelFactory::getTestModel();
		$data   = [
			'post_id' => 1,
			'user_id' => 2,
			'type_id' => 3,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result );
	}

	function test_normalise_row() {
		$model         = ModelFactory::getTestModel();
		$normalisedRow = [ 'user_id' => 1, 'post_id' => 1, 'type_id' => 1 ];
		$this->assertEqualSets( $model->normalise_row( $normalisedRow ), $normalisedRow );
		$abnormalRow = [ 'post_id' => 1, 'user_id' => 1 ];
		$this->assertEqualSets( $model->normalise_row( $abnormalRow ), $normalisedRow );
	}

	function test_normalise_many_rows() {
		$model      = ModelFactory::getTestModel();
		$normalised = [
			[ 'user_id' => 1, 'post_id' => 1, 'type_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2, 'type_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 3, 'type_id' => 1 ],
		];
		$this->assertEqualSets( $model->normalise_rows( $normalised ), $normalised );
		$abnormal = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'post_id' => 2, 'user_id' => 1, 'type_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 3, 'type_id' => 1 ],
		];
		$this->assertEqualSets( $model->normalise_rows( $abnormal ), $normalised );
	}

	function test_validate_rows() {
		$model     = ModelFactory::getTestModel();
		$modelC    = ModelFactory::getTestModelCompositeKey();
		$validSet  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
		];
		$validSetC = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
		];
		$this->assertTrue( $model->validate_rows( $validSet ) );
		$this->assertTrue( $modelC->validate_rows( $validSetC ) );

		$invalidSetByCount  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 2, 'type_id' => 1 ],
		];
		$invalidSetByCountC = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2, 'type_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
		];
		$this->assertFalse( $model->validate_rows( $invalidSetByCount ) );
		$this->assertFalse( $modelC->validate_rows( $invalidSetByCountC ) );

		$invalidSetByOrder  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'post_id' => 1, 'user_id' => 2 ],
		];
		$invalidSetByOrderC = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'post_id' => 2, 'user_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
		];
		$this->assertFalse( $model->validate_rows( $invalidSetByOrder ) );
		$this->assertFalse( $modelC->validate_rows( $invalidSetByOrderC ) );

		$invalidSetByMissingKeys  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'type_id' => 9, 'post_id' => 1 ],
		];
		$invalidSetByMissingKeysC = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2 ],
			[ 'type_id' => 2, 'post_id' => 1 ],
		];
		$this->assertFalse( $model->validate_rows( $invalidSetByMissingKeys ) );
		$this->assertFalse( $modelC->validate_rows( $invalidSetByMissingKeysC ) );

		$invalidSetByDuplicatedKeys  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2 ],
		];
		$invalidSetByDuplicatedKeysC = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2 ],
		];
		$this->assertFalse( $model->validate_rows( $invalidSetByDuplicatedKeys ) );
		$this->assertFalse( $modelC->validate_rows( $invalidSetByDuplicatedKeysC ) );
	}


	function test_prepare_fields_string() {
		$model    = ModelFactory::getTestModel();
		$fields   = [ 'one', 'two', 'three' ];
		$expected = '`one`,`two`,`three`';
		$this->assertSame( $expected, $model->prepare_fields_string( $fields ) );
	}


	function test_insert_rows() {
		$model = ModelFactory::getTestModel();
		$rows  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
		];
		$this->assertTrue( $model->insert_rows( $rows ) );
		$this->assertSame( 2, $model->count() );

		$modelC = ModelFactory::getTestModelCompositeKey();
		$rows   = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 1, 'post_id' => 2 ],
			[ 'user_id' => 1, 'post_id' => 3 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 2 ],
			[ 'user_id' => 2, 'post_id' => 3 ],
		];
		$this->assertTrue( $modelC->insert_rows( $rows ) );
		$this->assertSame( 6, $modelC->count() );


		$outOfOrder = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'post_id' => 2, 'user_id' => 1 ],
		];

		$this->assertFalse( $model->insert_rows( $outOfOrder ) );

		$missingKey = [
			[ 'post_id' => 1 ],
			[ 'post_id' => 2, 'user_id' => 1 ],
		];
		$this->assertFalse( $model->insert_rows( $missingKey ) );
	}

	function test_set_missing_defaults() {
		$model     = ModelFactory::getTestModel();
		$inputData = [
			'post_id' => 1,
			'user_id' => 2,
		];
		$data      = $model->set_missing_defaults( $inputData );
		$this->assertTrue( isset( $data['type_id'] ) );
	}

	function test_remove_extraneous_fields() {
		$model     = ModelFactory::getTestModel();
		$inputData = [
			'post_id'               => 1,
			'user_id'               => 2,
			'some_extraneous_field' => 'value'
		];
		$data      = $model->remove_extraneous_fields( $inputData );
		$this->assertCount( 2, $data );
		$this->assertFalse( isset( $data['some_extraneous_field'] ) );
	}

	function test_insertion_of_duplicate_data_fails() {
		$model  = ModelFactory::getTestModel();
		$data   = [
			'post_id' => 1,
			'user_id' => 2,
			'type_id' => 3,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result );
		$model->db->suppress_errors( true ); // error output gets dumped into test console
		$result = $model->insert( $data );
		$this->assertFalse( $result );
		$model->db->suppress_errors( false );
	}

	function test_update() {
		$model  = ModelFactory::getTestModel();
		$data   = [
			'post_id' => 2,
			'user_id' => 3,
			'type_id' => 4,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );

		$updatedData = [ 'type_id' => 9 ];
		$where       = [ 'post_id' => 2, 'user_id' => 3 ];
		$this->assertTrue( $model->update( $updatedData, $where ) );
	}

	function test_update_returns_false_when_no_row_exists() {
		$model       = ModelFactory::getTestModel();
		$updatedData = [ 'type_id' => 9 ];
		$where       = [ 'post_id' => 2, 'user_id' => 3 ];
		$this->assertFalse( $model->update( $updatedData, $where ) );
	}

	function test_count() {
		$model = ModelFactory::getTestModel();
		$data  = [
			'post_id' => 2,
			'user_id' => 3,
			'type_id' => 4,
		];
		$this->assertSame( 0, $model->count() );
		$model->insert( $data );
		$this->assertSame( 1, $model->count() );
		$data['user_id'] = 4;
		$model->insert( $data );
		$this->assertSame( 2, $model->count() );
	}

	function test_insert_or_update() {
		$model  = ModelFactory::getTestModel();
		$data   = [
			'post_id' => 3,
			'user_id' => 4,
			'type_id' => 5,
		];
		$result = $model->insert_or_update( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );

		$data['type_id'] = 6;
		$result          = $model->insert_or_update( $data );
		$this->assertTrue( $result, 'Failed to update data' );

		$this->assertSame( 1, $model->count() );
	}

	/** @group complete */
	function test_insert_or_update_rows_method_overwrites_existing_records() {
		$model = ModelFactory::getTestModel();
		$rows  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
		];
		$this->assertTrue( $model->insert_rows( $rows ) );
		$this->assertSame( 2, $model->count() );

		// insert new and changed existing rows
		$rows = [
			[ 'user_id' => 2, 'post_id' => 2 ], // changed
			[ 'user_id' => 3, 'post_id' => 1 ], // new
		];
		$this->assertTrue( $model->insert_or_update_rows( $rows ) );
		$this->assertSame( 3, $model->count() );
		$saved_row = $model->find( 2 );
		$this->assertSame( 2, intval( $saved_row['post_id'] ) );
	}

	function test_column_format() {
		$model = ModelFactory::getTestModel();
		$cols  = $model->columns();
		$key   = key( $cols );
		$val   = current( $cols );
		$this->assertSame( $val, $model->column_format( $key ) );
		$this->assertFalse( $model->column_format( 'not_a_model_column' ) );
	}

	function test_build_where_clause() {
		$model = ModelFactory::getTestModel();
		$args  = [
			'arg1' => 1,
		];
		$this->assertSame( 'WHERE `arg1` = 1', $model->build_where_clause( $args ) );

		$args     = [
			'arg1' => 1,
			'arg2' => 'string',
			'arg3' => 0.1234
		];
		$expFloat = $model->db->prepare( '%f', 0.1234 ); // making sure we expect the same precision returned by our db object
		$clause   = $model->build_where_clause( $args );
		$this->assertSame( "WHERE `arg1` = 1 AND `arg2` = 'string' AND `arg3` = $expFloat", $clause );
	}

	function test_guess_format() {
		$model = ModelFactory::getTestModel();
		$this->assertSame( '%s', $model->guess_format( 'string' ) );
		$this->assertSame( '%f', $model->guess_format( 1.2334234 ) );
		$this->assertSame( '%f', $model->guess_format( '1.2334234' ) );
		$this->assertSame( '%d', $model->guess_format( 3 ) );
		$this->assertSame( '%d', $model->guess_format( '3' ) );
	}

	function test_validate_primary_key_or_array() {
		$model = ModelFactory::getTestModel();
		$this->assertTrue( $model->validate_inbound_primary_key( 9 ) );
		$this->assertTrue( $model->validate_inbound_primary_key( 'string' ) );

		$model = ModelFactory::getTestModelCompositeKey();
		$this->assertTrue( $model->validate_inbound_primary_key( [ 1, 2 ] ) );
		$this->assertTrue( $model->validate_inbound_primary_key( [ 'user_id' => 1, 'post_id' => 2 ] ) );
		$this->assertFalse( $model->validate_inbound_primary_key( 1 ) );
		$this->assertFalse( $model->validate_inbound_primary_key( [ 1 ] ) );
		$this->assertFalse( $model->validate_inbound_primary_key( [ 'wrong_key' => 1, 'post_id' => 2 ] ) );
	}

	function test_find_works_with_single_key() {
		$model  = ModelFactory::getTestModel();
		$data   = [
			'user_id' => 4,
			'post_id' => 3,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$this->assertTrue( is_array( $model->find( 4 ) ) );
		$this->assertFalse( $model->find( 5 ) );
	}

	function test_find_works_with_composite_key() {
		$model  = ModelFactory::getTestModelCompositeKey();
		$data   = [
			'user_id' => 4,
			'post_id' => 3,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$this->assertFalse( $model->find( 4 ) );
		$this->assertTrue( is_array( $model->find( [ 4, 3 ] ) ) );
		$this->assertTrue( is_array( $model->find( [ 'user_id' => 4, 'post_id' => 3 ] ) ) );
		$this->assertTrue( is_array( $model->find( [ 'post_id' => 3, 'user_id' => 4 ] ) ) );
	}


	function test_find_where() {
		$model  = ModelFactory::getTestModelCompositeKey();
		$data   = [
			'user_id' => 4,
			'post_id' => 3,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$data   = [
			'user_id' => 4,
			'post_id' => 4,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$data   = [
			'user_id' => 5,
			'post_id' => 4,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$this->assertCount( 2, $model->find_where( [ 'user_id' => 4 ] ) );
		$this->assertCount( 2, $model->find_where( [ 'post_id' => 4 ] ) );
		$this->assertCount( 1, $model->find_where( [ 'post_id' => 4 ], 1 ) );
		$this->assertCount( 0, $model->find_where( [ 'post_id' => 40 ] ) );
	}


	function test_find_where_limit_and_offset_are_working() {
		$model = ModelFactory::getTestModel();
		$rows  = [
			[ 'user_id' => 1, 'post_id' => 1 ],
			[ 'user_id' => 2, 'post_id' => 1 ],
			[ 'user_id' => 3, 'post_id' => 1 ],
			[ 'user_id' => 4, 'post_id' => 1 ],
			[ 'user_id' => 5, 'post_id' => 1 ],
			[ 'user_id' => 6, 'post_id' => 1 ],
			[ 'user_id' => 7, 'post_id' => 1 ],
			[ 'user_id' => 8, 'post_id' => 1 ],
			[ 'user_id' => 9, 'post_id' => 1 ],
			[ 'user_id' => 10, 'post_id' => 1 ],
		];
		$this->assertTrue( $model->insert_rows( $rows ) );
		$this->assertSame( 10, $model->count() );
		$this->assertCount( 5, $model->find_where( [ 'post_id' => 1 ], $limit = 5 ) );

		$query = $model->find_where( [ 'post_id' => 1 ], $limit = 5, $offset = 5 );
		$this->assertCount( 5, $query );
		$this->assertSame( 6, intval( $query[0]['user_id'] ) );
	}


	function test_delete_where() {
		$model  = ModelFactory::getTestModelCompositeKey();
		$data   = [
			'user_id' => 4,
			'post_id' => 3,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$data   = [
			'user_id' => 4,
			'post_id' => 4,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$this->assertFalse( $model->delete_where( [ 'user_id' => 100 ] ) );
		$this->assertTrue( $model->delete_where( [ 'user_id' => 4 ] ) );
		$this->assertSame( 0, $model->count() );
	}


}
