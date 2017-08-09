<?php


class Test_Model extends \Mishterk\WP\Tools\DB\Model {


	public $drop_on_deactivation = true;


	function table_name() {
		return 'model_table';
	}


	function schema() {
		return "CREATE TABLE {$this->full_table_name()} (
			  user_id BIGINT UNSIGNED NOT NULL,
			  post_id BIGINT UNSIGNED NOT NULL,
			  type_id INT UNSIGNED    NOT NULL,
			  {$this->timestamp_field('created_at')},
			  {$this->timestamp_field('updated_at', true)},
			  PRIMARY KEY  (user_id)
			) {$this->db->get_charset_collate()};";
	}


	function column_defaults() {
		return [
			'type_id' => 1,
		];
	}


	function columns() {
		return [
			'user_id'    => '%d',
			'post_id'    => '%d',
			'type_id'    => '%d',
			'created_at' => '%s',
			'updated_at' => '%s',
		];
	}


	function primary_key() {
		return [ 'user_id' ];
	}
}