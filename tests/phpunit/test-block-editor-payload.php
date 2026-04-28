<?php
/**
 * Coverage for Category_Metabox_Enhanced_Admin::block_editor_taxonomies.
 *
 * The payload returned here is serialized as `window.ofCmeBlockEditor`
 * via wp_add_inline_script — it is the contract between PHP and the
 * Block Editor JS bundle. A regression that drops a key, flips a type,
 * or stops filtering taxonomies correctly will silently break every
 * sidebar panel without a visible PHP error, and the Build-freshness
 * CI check won't catch it (that only verifies built JS matches src).
 * PHPUnit asserts the contract directly so the panic surface stays small.
 */

class Test_Block_Editor_Payload extends WP_UnitTestCase {

	/**
	 * @var Category_Metabox_Enhanced_Admin
	 */
	private $admin;

	/**
	 * Slugs registered during a test. tear_down unregisters them so the
	 * global taxonomy registry doesn't leak across tests (WP rolls back the
	 * DB but not $GLOBALS['wp_taxonomies']).
	 *
	 * @var string[]
	 */
	private $registered_slugs = array();

	public function set_up() {
		parent::set_up();
		$this->admin = new Category_Metabox_Enhanced_Admin( 'category-metabox-enhanced', '0.9.1' );
	}

	public function tear_down() {
		foreach ( $this->registered_slugs as $slug ) {
			unregister_taxonomy( $slug );
			delete_option( 'category-metabox-enhanced_' . $slug );
		}
		$this->registered_slugs = array();
		parent::tear_down();
	}

	private function register_test_taxonomy( $slug, array $args = array(), array $option = array() ) {
		register_taxonomy( $slug, 'post', $args );
		$this->registered_slugs[] = $slug;
		if ( $option ) {
			update_option( 'category-metabox-enhanced_' . $slug, $option );
		}
	}

	private function invoke_payload() {
		$method = new ReflectionMethod( $this->admin, 'block_editor_taxonomies' );
		$method->setAccessible( true );
		return $method->invoke( $this->admin );
	}

	private function find_entry( array $payload, $slug ) {
		foreach ( $payload as $entry ) {
			if ( isset( $entry['slug'] ) && $entry['slug'] === $slug ) {
				return $entry;
			}
		}
		return null;
	}

	public function test_includes_hierarchical_rest_radio_taxonomy_with_full_payload() {
		$this->register_test_taxonomy(
			'cme_phpunit_full',
			array(
				'hierarchical' => true,
				'show_in_rest' => true,
				'rest_base'    => 'cme_phpunit_full_terms',
				'labels'       => array( 'name' => 'PHPUnit Full' ),
			),
			array(
				'type'            => 'radio',
				'metabox_title'   => 'Custom Title',
				'indented'        => 1,
				'allow_new_terms' => 1,
				'force_selection' => 1,
			)
		);

		$entry = $this->find_entry( $this->invoke_payload(), 'cme_phpunit_full' );

		$this->assertNotNull( $entry, 'Eligible tax must appear in payload' );
		$this->assertEqualsCanonicalizing(
			array( 'slug', 'rest_base', 'type', 'indented', 'allow_new_terms', 'force_selection', 'panel_title', 'post_types' ),
			array_keys( $entry ),
			'Payload shape is the JS bundle contract — adding/removing keys is breaking.'
		);
		$this->assertSame( 'cme_phpunit_full_terms', $entry['rest_base'] );
		$this->assertSame( 'radio', $entry['type'] );
		$this->assertSame( 'Custom Title', $entry['panel_title'] );
		$this->assertTrue( $entry['indented'] );
		$this->assertTrue( $entry['allow_new_terms'] );
		$this->assertTrue( $entry['force_selection'] );
		$this->assertContains( 'post', $entry['post_types'] );
	}

	public function test_boolean_payload_fields_reflect_zero_option_values() {
		// Mirror of the full-payload test for the inverse: when indented,
		// allow_new_terms, and force_selection are all 0, the (bool) casts
		// in block_editor_taxonomies must produce literal false. A regression
		// that swapped (bool) for (int), or accidentally inverted a flag,
		// would silently flip every sidebar setting on save without an error.
		$this->register_test_taxonomy(
			'cme_phpunit_zeros',
			array(
				'hierarchical' => true,
				'show_in_rest' => true,
			),
			array(
				'type'            => 'radio',
				'indented'        => 0,
				'allow_new_terms' => 0,
				'force_selection' => 0,
			)
		);

		$entry = $this->find_entry( $this->invoke_payload(), 'cme_phpunit_zeros' );

		$this->assertNotNull( $entry );
		$this->assertFalse( $entry['indented'] );
		$this->assertFalse( $entry['allow_new_terms'] );
		$this->assertFalse( $entry['force_selection'] );
	}

	public function test_excludes_non_rest_hierarchical_taxonomy() {
		// Non-REST taxes have no Block Editor sidebar to render into; the
		// classic Taxonomy_Single_Term metabox is the only UI for them.
		$this->register_test_taxonomy(
			'cme_phpunit_no_rest',
			array(
				'hierarchical' => true,
				'show_in_rest' => false,
			),
			array( 'type' => 'radio' )
		);

		$this->assertNull( $this->find_entry( $this->invoke_payload(), 'cme_phpunit_no_rest' ) );
	}

	public function test_excludes_checkbox_type_taxonomy() {
		// of_cme_is_single_term_type returns false for 'checkbox' — the panel
		// only renders a single-term UI, so checkbox-typed taxes are the WP
		// default and intentionally untouched.
		$this->register_test_taxonomy(
			'cme_phpunit_checkbox',
			array(
				'hierarchical' => true,
				'show_in_rest' => true,
			),
			array( 'type' => 'checkbox' )
		);

		$this->assertNull( $this->find_entry( $this->invoke_payload(), 'cme_phpunit_checkbox' ) );
	}

	public function test_rest_base_falls_back_to_taxonomy_slug_when_unset() {
		// rest_base is a soft setting on register_taxonomy; without it WP
		// uses the slug as the REST endpoint. Mirror that here so the JS
		// bundle's REST URLs stay valid.
		$this->register_test_taxonomy(
			'cme_phpunit_no_base',
			array(
				'hierarchical' => true,
				'show_in_rest' => true,
			),
			array( 'type' => 'radio' )
		);

		$entry = $this->find_entry( $this->invoke_payload(), 'cme_phpunit_no_base' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'cme_phpunit_no_base', $entry['rest_base'] );
	}

	public function test_panel_title_falls_back_to_taxonomy_label_when_metabox_title_empty() {
		$this->register_test_taxonomy(
			'cme_phpunit_no_title',
			array(
				'hierarchical' => true,
				'show_in_rest' => true,
				'labels'       => array( 'name' => 'Fallback Label' ),
			),
			array(
				'type'          => 'radio',
				'metabox_title' => '',
			)
		);

		$entry = $this->find_entry( $this->invoke_payload(), 'cme_phpunit_no_title' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'Fallback Label', $entry['panel_title'] );
	}

	public function test_post_types_reflects_every_attached_post_type() {
		// Multi-post-type taxes are the case the Block Editor's TaxonomyPanel
		// guard (`taxonomy.post_types.includes(postType)`) actually exists for —
		// the panel must only render on screens for attached post types.
		register_post_type( 'cme_phpunit_cpt', array( 'public' => true, 'show_in_rest' => true ) );
		register_taxonomy(
			'cme_phpunit_multi_pt',
			array( 'post', 'cme_phpunit_cpt' ),
			array(
				'hierarchical' => true,
				'show_in_rest' => true,
			)
		);
		$this->registered_slugs[] = 'cme_phpunit_multi_pt';
		update_option( 'category-metabox-enhanced_cme_phpunit_multi_pt', array( 'type' => 'radio' ) );

		$entry = $this->find_entry( $this->invoke_payload(), 'cme_phpunit_multi_pt' );

		try {
			$this->assertNotNull( $entry );
			$this->assertEqualsCanonicalizing(
				array( 'post', 'cme_phpunit_cpt' ),
				$entry['post_types'],
				'post_types must mirror the taxonomy\'s registered object_type.'
			);
		} finally {
			unregister_post_type( 'cme_phpunit_cpt' );
		}
	}
}
