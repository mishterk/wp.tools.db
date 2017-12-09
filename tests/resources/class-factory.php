<?php


class ModelFactory {


	public static function getTestModel() {
		$model = new Test_Model();

		return $model;
	}


	public static function getTestModelWithBackticks() {
		$model = new Test_Model_With_Backticks();

		return $model;
	}

	public static function getTestModelCompositeKey() {
		$model = new Test_Model_Composite_key();

		return $model;
	}


}