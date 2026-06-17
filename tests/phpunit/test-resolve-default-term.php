<?php
/**
 * Coverage for of_cme_resolve_default_term().
 *
 * Verifies the four-step resolution chain:
 *   1. default_<tax>      (legacy option)
 *   2. default_term_<tax> (modern option, WP 5.5+)
 *   3. WP_Taxonomy::default_term (register_taxonomy default_term arg)
 *   4. first term by name asc
 * plus the of_cme_force_selection_default_term filter override.
 */

class Test_Resolve_Default_Term extends WP_UnitTestCase {

	const TAX = 'cme_test_resolve';

	/**
	 * Slug of any extra taxonomy a single test registered. Tracked so
	 * tear_down can clean up even when an assertion fails before the
	 * test reaches its own teardown.
	 *
	 * @var string
	 */
	private $extra_tax = '';

	public function set_up() {
		parent::set_up();
		register_taxonomy( self::TAX, 'post', array( 'hierarchical' => true ) );
	}

	public function tear_down() {
		delete_option( 'default_' . self::TAX );
		delete_option( 'default_term_' . self::TAX );
		unregister_taxonomy( self::TAX );
		if ( '' !== $this->extra_tax ) {
			delete_option( 'default_' . $this->extra_tax );
			delete_option( 'default_term_' . $this->extra_tax );
			unregister_taxonomy( $this->extra_tax );
			$this->extra_tax = '';
		}
		remove_all_filters( 'of_cme_force_selection_default_term' );
		parent::tear_down();
	}

	private function create_term( $name, $slug = '' ) {
		return self::factory()->term->create(
			array(
				'taxonomy' => self::TAX,
				'name'     => $name,
				'slug'     => $slug ? $slug : sanitize_title( $name ),
			)
		);
	}

	public function test_legacy_option_resolves_term() {
		$term_id = $this->create_term( 'Legacy' );
		update_option( 'default_' . self::TAX, $term_id );

		$this->assertSame( $term_id, of_cme_resolve_default_term( self::TAX ) );
	}

	public function test_modern_option_resolves_term() {
		$term_id = $this->create_term( 'Modern' );
		update_option( 'default_term_' . self::TAX, $term_id );

		$this->assertSame( $term_id, of_cme_resolve_default_term( self::TAX ) );
	}

	public function test_legacy_wins_when_both_set() {
		$legacy = $this->create_term( 'Legacy' );
		$modern = $this->create_term( 'Modern' );
		update_option( 'default_' . self::TAX, $legacy );
		update_option( 'default_term_' . self::TAX, $modern );

		$this->assertSame( $legacy, of_cme_resolve_default_term( self::TAX ) );
	}

	public function test_falls_through_when_option_term_deleted() {
		// Stale option references a term that no longer exists; resolver
		// must skip it and fall through to the next path. With no default_term
		// registered, the alphabetic fallback kicks in — 'Aaa' wins over 'Zzz'.
		$ghost     = $this->create_term( 'Ghost' );
		$surviving = $this->create_term( 'Aaa' );
		$this->create_term( 'Zzz' );
		update_option( 'default_' . self::TAX, $ghost );
		wp_delete_term( $ghost, self::TAX );

		$this->assertSame( $surviving, of_cme_resolve_default_term( self::TAX ) );
	}

	public function test_register_taxonomy_default_term_with_slug() {
		// Exercise the third resolution step: WP_Taxonomy::default_term arg
		// resolved via get_term_by('slug', ...). register_taxonomy with the
		// default_term arg auto-creates the term *and* the modern option, so
		// we drop the option to isolate the slug-lookup branch. tear_down
		// owns the unregister so a failing assertion can't leak this
		// taxonomy into the rest of the suite.
		$this->extra_tax = 'cme_test_with_default';
		register_taxonomy( $this->extra_tax, 'post', array(
			'hierarchical' => true,
			'default_term' => array(
				'name' => 'Default Test',
				'slug' => 'default-test',
			),
		) );
		delete_option( 'default_term_' . $this->extra_tax );

		$term = get_term_by( 'slug', 'default-test', $this->extra_tax );
		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( (int) $term->term_id, of_cme_resolve_default_term( $this->extra_tax ) );
	}

	public function test_falls_through_to_first_by_name() {
		$first = $this->create_term( 'Alpha' );
		$this->create_term( 'Beta' );

		$this->assertSame( $first, of_cme_resolve_default_term( self::TAX ) );
	}

	public function test_returns_zero_when_taxonomy_has_no_terms() {
		$this->assertSame( 0, of_cme_resolve_default_term( self::TAX ) );
	}

	public function test_filter_overrides_resolution() {
		$natural  = $this->create_term( 'Natural' );
		$override = $this->create_term( 'Override' );

		add_filter(
			'of_cme_force_selection_default_term',
			static function ( $default, $taxonomy ) use ( $override ) {
				return $override;
			},
			10,
			2
		);

		$this->assertSame( $override, of_cme_resolve_default_term( self::TAX ) );
		$this->assertNotSame( $natural, of_cme_resolve_default_term( self::TAX ) );
	}
}
