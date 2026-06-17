<?php
/**
 * Coverage for Category_Metabox_Enhanced_Settings_Settings::validate_inputs.
 *
 * The validator runs as register_setting's `sanitize_callback` on every
 * settings save. A regression here silently corrupts every saved option
 * for every taxonomy, so even a few cheap PHPUnit assertions are
 * disproportionately load-bearing.
 */

class Test_Validate_Inputs extends WP_UnitTestCase {

	/**
	 * @var Category_Metabox_Enhanced_Settings_Settings
	 */
	private $settings;

	public function set_up() {
		parent::set_up();
		$this->settings = new Category_Metabox_Enhanced_Settings_Settings( 'category-metabox-enhanced' );
	}

	public function tear_down() {
		remove_all_filters( 'cme_validate_inputs' );
		parent::tear_down();
	}

	public function test_full_input_preserves_all_default_keys() {
		$inputs = array(
			'type'            => 'radio',
			'context'         => 'side',
			'priority'        => 'default',
			'metabox_title'   => 'My Title',
			'indented'        => 1,
			'allow_new_terms' => 1,
			'force_selection' => 1,
		);

		$output = $this->settings->validate_inputs( $inputs );

		$this->assertEqualsCanonicalizing( array_keys( of_cme_get_defaults() ), array_keys( $output ) );
		$this->assertSame( 'radio', $output['type'] );
		$this->assertSame( 'My Title', $output['metabox_title'] );
		$this->assertSame( '1', $output['force_selection'] );
	}

	public function test_missing_checkbox_field_defaults_to_zero() {
		// Unchecked checkboxes don't appear in $_POST. The validator must
		// substitute 0 so the saved option doesn't keep the previous value.
		// This is the regression class that breaks "uncheck Force selection,
		// save, reload" — i.e. the e2e settings spec, but at the unit layer.
		$inputs = array(
			'type'            => 'radio',
			'context'         => 'side',
			'priority'        => 'default',
			'metabox_title'   => '',
			'indented'        => 1,
			'allow_new_terms' => 1,
			// force_selection intentionally omitted.
		);

		$output = $this->settings->validate_inputs( $inputs );

		$this->assertSame( 0, $output['force_selection'] );
	}

	public function test_empty_input_zero_fills_every_default_key() {
		$output = $this->settings->validate_inputs( array() );

		foreach ( array_keys( of_cme_get_defaults() ) as $key ) {
			$this->assertSame( 0, $output[ $key ], "Key {$key} should default to 0 when absent from input." );
		}
	}

	public function test_unknown_keys_are_dropped() {
		// The validator iterates over of_cme_get_defaults(), so anything not
		// in that list is silently discarded — important if a malicious or
		// stale form submission carries extra fields.
		$inputs = array(
			'type'        => 'radio',
			'evil_field'  => 'should_not_persist',
			'admin_email' => 'attacker@example.com',
		);

		$output = $this->settings->validate_inputs( $inputs );

		$this->assertArrayNotHasKey( 'evil_field', $output );
		$this->assertArrayNotHasKey( 'admin_email', $output );
	}

	public function test_html_and_script_tags_are_stripped() {
		// validate_inputs runs each value through sanitize_text_field, which
		// strips tags and normalizes whitespace. Asserts the wiring is in
		// place; sanitize_text_field's own behavior is WP's responsibility.
		$inputs = array(
			'metabox_title' => '<script>alert(1)</script>News',
		);

		$output = $this->settings->validate_inputs( $inputs );

		$this->assertSame( 'News', $output['metabox_title'] );
	}

	public function test_cme_validate_inputs_filter_can_override_output() {
		// Third-party extension point. The filter receives ($outputs, $inputs)
		// — both useful for consumers that need to react to raw form data
		// before it lands in the option.
		add_filter(
			'cme_validate_inputs',
			static function ( $outputs, $inputs ) {
				$outputs['type'] = 'checkbox';
				return $outputs;
			},
			10,
			2
		);

		$output = $this->settings->validate_inputs( array( 'type' => 'radio' ) );

		$this->assertSame( 'checkbox', $output['type'] );
	}
}
