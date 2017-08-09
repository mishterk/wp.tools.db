<?php


namespace Mishterk\WP\Tools\DB;


/**
 * Class Adaptor
 * @package Mishterk\WP\Tools\DB
 *
 * This adaptor serves as a replaceable object which we can test around. Any direct WP functions required for DB
 * operations should go in here.
 */
class Adaptor {


	/**
	 * Direct port of WP's dbDelta() fn.
	 *
	 * @param $queries
	 * @param bool $execute
	 *
	 * @return array
	 */
	function dbDelta( $queries, $execute = true ) {
		// todo - maybe try/catch this and return a TRUE or WP_Error if appropriate
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		return dbDelta( $queries, $execute );
	}
}