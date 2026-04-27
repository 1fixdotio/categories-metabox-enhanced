<?php
/**
 * Coverage for of_cme_enforce_single_term().
 *
 * Each test corresponds to a regression class identified in the PR #5 review:
 * sentinel filtering, type coercion of slug strings, force_selection
 * substitution, and multi-term coercion.
 */

class Test_Enforce_Single_Term extends WP_UnitTestCase {

	const TAX        = 'cme_test_enforce';
	const OPTION_KEY = 'category-metabox-enhanced_cme_test_enforce';

	public function set_up() {
		parent::set_up();
		register_taxonomy( self::TAX, 'post', array( 'hierarchical' => true ) );
		update_option( self::OPTION_KEY, array(
			'type'            => 'radio',
			'force_selection' => 1,
		) );
	}

	public function tear_down() {
		delete_option( self::OPTION_KEY );
		delete_option( 'default_' . self::TAX );
		delete_option( 'default_term_' . self::TAX );
		unregister_taxonomy( self::TAX );
		parent::tear_down();
	}

	/**
	 * Helper: create a term in the test taxonomy and pin it as the
	 * legacy default so force_selection resolves deterministically.
	 */
	private function pin_default_term() {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => self::TAX,
				'name'     => 'Pinned',
				'slug'     => 'pinned',
			)
		);
		update_option( 'default_' . self::TAX, $term_id );
		return $term_id;
	}

	public function test_non_array_input_passes_through() {
		$this->assertSame( 'foo', of_cme_enforce_single_term( 'foo', 0, self::TAX ) );
		$this->assertSame( 42, of_cme_enforce_single_term( 42, 0, self::TAX ) );
		$this->assertNull( of_cme_enforce_single_term( null, 0, self::TAX ) );
	}

	public function test_non_hierarchical_taxonomy_passes_through() {
		// post_tag is non-hierarchical and ships with WordPress, so we can
		// assert the early return without registering anything custom.
		$input = array( 10, 20, 30 );
		$this->assertSame( $input, of_cme_enforce_single_term( $input, 0, 'post_tag' ) );
	}

	public function test_checkbox_type_passes_through() {
		update_option( self::OPTION_KEY, array( 'type' => 'checkbox' ) );

		$input = array( 10, 20, 30 );
		$this->assertSame( $input, of_cme_enforce_single_term( $input, 0, self::TAX ) );
	}

	public function test_single_valid_id_passes_through() {
		$this->assertSame( array( 42 ), of_cme_enforce_single_term( array( 42 ), 0, self::TAX ) );
	}

	public function test_multiple_ids_coerced_to_last() {
		$this->assertSame( array( 30 ), of_cme_enforce_single_term( array( 10, 20, 30 ), 0, self::TAX ) );
	}

	public function test_zero_int_filtered_triggers_force_selection() {
		$default = $this->pin_default_term();

		$this->assertSame( array( $default ), of_cme_enforce_single_term( array( 0 ), 0, self::TAX ) );
	}

	public function test_zero_string_filtered_triggers_force_selection() {
		$default = $this->pin_default_term();

		$this->assertSame( array( $default ), of_cme_enforce_single_term( array( '0' ), 0, self::TAX ) );
	}

	public function test_slug_string_preserved() {
		// Regression for the `(int) 'slug' === 0` bug: pre_set_object_terms
		// fires before WP resolves slugs to IDs, so a slug like 'my-category'
		// must survive the sentinel filter.
		$this->assertSame(
			array( 'my-category' ),
			of_cme_enforce_single_term( array( 'my-category' ), 0, self::TAX )
		);
	}

	public function test_mixed_id_and_zero_filters_zero() {
		// [5, 0] should drop the sentinel and pass the surviving single ID
		// through without triggering substitution.
		$this->assertSame( array( 5 ), of_cme_enforce_single_term( array( 5, 0 ), 0, self::TAX ) );
	}

	public function test_empty_with_force_selection_substitutes_default() {
		$default = $this->pin_default_term();

		$this->assertSame( array( $default ), of_cme_enforce_single_term( array(), 0, self::TAX ) );
	}

	public function test_empty_without_force_selection_passes_through() {
		update_option( self::OPTION_KEY, array(
			'type'            => 'radio',
			'force_selection' => 0,
		) );

		$this->assertSame( array(), of_cme_enforce_single_term( array(), 0, self::TAX ) );
	}
}
