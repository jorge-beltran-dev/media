<?php
class AllValidationGroupTest extends TestSuite {
	var $label = 'All validation related test cases';

	function AllValidationGroupTest() {
		TestManager::addTestFile(
			$this,
			dirname(dirname(__FILE__)) . DS . 'cases' . DS . 'vendors' . DS . 'media_validation'
		);
		TestManager::addTestFile(
			$this,
			dirname(dirname(__FILE__)) . DS . 'cases' . DS . 'vendors' . DS . 'transfer_validation'
		);
	}
}
?>