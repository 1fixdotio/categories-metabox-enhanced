<?php
/**
 * Integration coverage for of_cme_enforce_single_term().
 *
 * The function is hooked on `set_object_terms` (post-write action), not
 * tested in isolation, because the regression in 0.9.0 was a hook-name typo
 * that function-level tests can't catch. Each test calls wp_set_object_terms
 * — the integration path real callers take — and asserts the resulting term
 * assignment.
 */

class Test_Enforce_Single_Term extends WP_UnitTestCase {

	const TAX        = 'cme_test_enforce';
	const OPTION_KEY = 'category-metabox-enhanced_cme_test_enforce';

	private $term_ids = array();

	public function set_up() {
		parent::set_up();
		register_taxonomy( self::TAX, 'post', array( 'hierarchical' => true ) );
		update_option( self::OPTION_KEY, array(
			'type'            => 'radio',
			'force_selection' => 1,
		) );

		// Predictable term set for substitution + multi-term tests.
		$this->term_ids = array(
			'alpha' => self::factory()->term->create( array( 'taxonomy' => self::TAX, 'name' => 'Alpha', 'slug' => 'alpha' ) ),
			'beta'  => self::factory()->term->create( array( 'taxonomy' => self::TAX, 'name' => 'Beta',  'slug' => 'beta'  ) ),
			'gamma' => self::factory()->term->create( array( 'taxonomy' => self::TAX, 'name' => 'Gamma', 'slug' => 'gamma' ) ),
		);
	}

	public function tear_down() {
		delete_option( self::OPTION_KEY );
		delete_option( 'default_' . self::TAX );
		delete_option( 'default_term_' . self::TAX );
		unregister_taxonomy( self::TAX );
		parent::tear_down();
	}

	private function create_post() {
		return self::factory()->post->create( array( 'post_status' => 'publish' ) );
	}

	private function get_post_term_ids( $post_id ) {
		return wp_get_object_terms( $post_id, self::TAX, array( 'fields' => 'ids', 'orderby' => 'term_id' ) );
	}

	/**
	 * Meta-test — proves the action is wired to `set_object_terms` so a
	 * future hook-name typo (the 0.9.0 regression) breaks the suite.
	 */
	public function test_action_is_registered_on_set_object_terms() {
		$this->assertNotFalse(
			has_action( 'set_object_terms', 'of_cme_enforce_single_term' ),
			'of_cme_enforce_single_term must be hooked on set_object_terms; that hook is the only post-write integration point WP exposes for term assignments.'
		);
	}

	public function test_single_term_passes_through() {
		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array( $this->term_ids['beta'] ), self::TAX );

		$this->assertSame( array( $this->term_ids['beta'] ), $this->get_post_term_ids( $post_id ) );
	}

	public function test_multiple_terms_coerced_to_last() {
		$post_id = $this->create_post();
		wp_set_object_terms(
			$post_id,
			array( $this->term_ids['alpha'], $this->term_ids['beta'], $this->term_ids['gamma'] ),
			self::TAX
		);

		$this->assertSame( array( $this->term_ids['gamma'] ), $this->get_post_term_ids( $post_id ) );
	}

	public function test_empty_with_force_selection_substitutes_default() {
		// Alpha is alphabetically first — falls through to the resolver's
		// first-by-name path when no default option is set.
		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array(), self::TAX );

		$this->assertSame( array( $this->term_ids['alpha'] ), $this->get_post_term_ids( $post_id ) );
	}

	public function test_zero_sentinel_with_force_selection_substitutes_default() {
		// [0] reaches wp_set_object_terms which drops it during term resolution
		// (term_exists(0) returns null, is_int(0) skip branch). Net committed
		// state is empty, so our action substitutes the default.
		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array( 0 ), self::TAX );

		$this->assertSame( array( $this->term_ids['alpha'] ), $this->get_post_term_ids( $post_id ) );
	}

	public function test_empty_without_force_selection_passes_through() {
		update_option( self::OPTION_KEY, array(
			'type'            => 'radio',
			'force_selection' => 0,
		) );

		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array(), self::TAX );

		$this->assertSame( array(), $this->get_post_term_ids( $post_id ) );
	}

	public function test_pinned_default_term_wins_over_first_by_name() {
		update_option( 'default_' . self::TAX, $this->term_ids['gamma'] );

		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array(), self::TAX );

		$this->assertSame( array( $this->term_ids['gamma'] ), $this->get_post_term_ids( $post_id ) );
	}

	public function test_checkbox_type_is_not_coerced() {
		update_option( self::OPTION_KEY, array( 'type' => 'checkbox' ) );

		$post_id = $this->create_post();
		wp_set_object_terms(
			$post_id,
			array( $this->term_ids['alpha'], $this->term_ids['beta'] ),
			self::TAX
		);

		$this->assertEqualsCanonicalizing(
			array( $this->term_ids['alpha'], $this->term_ids['beta'] ),
			$this->get_post_term_ids( $post_id )
		);
	}

	public function test_non_hierarchical_taxonomy_is_not_coerced() {
		// post_tag ships non-hierarchical, so we can verify the early return
		// without registering yet another taxonomy.
		$post_id = $this->create_post();
		$tag1    = self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'one' ) );
		$tag2    = self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'two' ) );

		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );

		$this->assertEqualsCanonicalizing(
			array( $tag1, $tag2 ),
			wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) )
		);
	}

	public function test_append_mode_is_not_coerced() {
		// Append mode is the one path that intentionally adds to the existing
		// set; coercing would surprise callers like wp_add_object_terms.
		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array( $this->term_ids['alpha'] ), self::TAX );
		wp_set_object_terms( $post_id, array( $this->term_ids['beta'] ), self::TAX, true );

		$this->assertEqualsCanonicalizing(
			array( $this->term_ids['alpha'], $this->term_ids['beta'] ),
			$this->get_post_term_ids( $post_id )
		);
	}
}
