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
		$this->assertSame( "created_at DATETIME DEFAULT CURRENT_TIMESTAMP", $model->timestamp_field() );
		$this->assertSame( "custom_column_name DATETIME DEFAULT CURRENT_TIMESTAMP", $model->timestamp_field( 'custom_column_name' ) );
		$this->assertSame( "t DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", $model->timestamp_field( 't', true ) );
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

	function test_normalise_data_sets_defaults_and_removes_extraneous_data() {
		$model     = ModelFactory::getTestModel();
		$inputData = [
			'post_id'    => 1,
			'user_id'    => 2,
			'extraneous' => 'data',
		];
		$data      = $model->normalize_data( $inputData );
		$this->assertFalse( isset( $data['extraneous'] ) );
		$this->assertTrue( isset( $data['user_id'] ) );
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
		$this->assertFalse( $model->validate_inbound_primary_key( 1 ) );
		$this->assertFalse( $model->validate_inbound_primary_key( [ 1 ] ) );
		$this->assertTrue( $model->validate_inbound_primary_key( [ 'user_id' => 1, 'post_id' => 2 ] ) );
		$this->assertFalse( $model->validate_inbound_primary_key( [ 'wrong_key' => 1, 'post_id' => 2 ] ) );
	}

	function test_get_works_with_single_key() {
		$model  = ModelFactory::getTestModel();
		$data   = [
			'user_id' => 4,
			'post_id' => 3,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$this->assertTrue( is_array( $model->get( 4 ) ) );
		$this->assertFalse( $model->get( 5 ) );
	}

	function test_get_works_with_composite_key() {
		$model  = ModelFactory::getTestModelCompositeKey();
		$data   = [
			'user_id' => 4,
			'post_id' => 3,
			'type_id' => 5,
		];
		$result = $model->insert( $data );
		$this->assertTrue( $result, 'Failed to insert initial data' );
		$this->assertFalse( @$model->get( 4 ) );
		$this->assertTrue( is_array( $model->get( [ 4, 3 ] ) ) );
		$this->assertTrue( is_array( $model->get( [ 'user_id' => 4, 'post_id' => 3 ] ) ) );
		$this->assertTrue( is_array( $model->get( [ 'post_id' => 3, 'user_id' => 4 ] ) ) );
	}


	function test_where() {
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
		$this->assertCount( 2, $model->where( [ 'user_id' => 4 ] ) );
		$this->assertCount( 2, $model->where( [ 'post_id' => 4 ] ) );
		$this->assertCount( 1, $model->where( [ 'post_id' => 4 ], 1 ) );
		$this->assertCount( 0, $model->where( [ 'post_id' => 40 ] ) );
	}

	function test_delete() {
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
		$this->assertFalse( $model->delete( [ 'user_id' => 100 ] ) );
		$this->assertTrue( $model->delete( [ 'user_id' => 4 ] ) );
		$this->assertSame( 0, $model->count() );
	}



	// todo - if the insert_many() method happens, this is our testing foundation
//	function test_insert_many() {
//		$model  = ModelFactory::getTestModel();
//		$data   = [
//			[ 'post_id' => 1, 'user_id' => 2, 'type_id' => 3 ],
//			[ 'post_id' => 2, 'user_id' => 3 ],
//			[ 'post_id' => 3, 'user_id' => 4, 'type_id' => 5, 'extraneous' => 'data' ],
//		];
//		$result = $model->insert_many( $data );
//		$this->assertTrue( $result );
//		$this->assertSame( 3, $model->count() );
//	}


}
