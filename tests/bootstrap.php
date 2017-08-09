<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Wp_Tools_Db
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

defined( 'PHPUNIT_RUNNING' ) or define( 'PHPUNIT_RUNNING', true );

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	//require dirname( dirname( __FILE__ ) ) . '/wp-tools-db.php';
	require dirname( dirname( __FILE__ ) ) . '/src/Adaptor.php';
	require dirname( dirname( __FILE__ ) ) . '/src/ModelBase.php';
	require 'resources/class-test-model.php';
	require 'resources/class-test-model-composite-key.php';
	require 'resources/class-factory.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
