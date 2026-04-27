<?php
/**
 * Smoke test — proves the test harness boots and the plugin loads under it.
 */

class Test_Plugin_Loaded extends WP_UnitTestCase {

	public function test_plugin_classes_are_loaded() {
		$this->assertTrue( class_exists( 'Category_Metabox_Enhanced' ) );
	}

	public function test_helper_functions_are_loaded() {
		$this->assertTrue( function_exists( 'of_cme_get_defaults' ) );
		$this->assertTrue( function_exists( 'of_cme_enforce_single_term' ) );
		$this->assertTrue( function_exists( 'of_cme_resolve_default_term' ) );
	}

	public function test_get_defaults_includes_force_selection() {
		$defaults = of_cme_get_defaults();

		$expected_keys = array(
			'type',
			'context',
			'priority',
			'metabox_title',
			'indented',
			'allow_new_terms',
			'force_selection',
		);

		$this->assertSame( $expected_keys, array_keys( $defaults ) );
		$this->assertSame( 1, $defaults['force_selection'] );
	}
}
