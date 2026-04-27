<?php
/**
 * Plugin Name: Categories Metabox Enhanced — Test Seed
 * Description: Mounted by wp-env to pre-populate categories and plugin settings on first request. Idempotent.
 *
 * Lives in mu-plugins inside the wp-env environment. Do NOT include this in the
 * shipped plugin — it is dev-only.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	// Skip during WP test-suite install/runs — the seed assumes the front-end
	// schema, which the test bootstrap creates with a different table prefix.
	if ( wp_installing() || defined( 'WP_TESTS_DOMAIN' ) ) {
		return;
	}

	if ( get_option( 'cme_test_seeded' ) ) {
		return;
	}

	$tree = array(
		array( 'name' => 'News',    'slug' => 'news',    'parent' => 0 ),
		array( 'name' => 'Reviews', 'slug' => 'reviews', 'parent' => 0 ),
		array( 'name' => 'Tech',    'slug' => 'tech',    'parent' => 'news' ),
		array( 'name' => 'Sports',  'slug' => 'sports',  'parent' => 'news' ),
		array( 'name' => 'Books',   'slug' => 'books',   'parent' => 'reviews' ),
		array( 'name' => 'Movies',  'slug' => 'movies',  'parent' => 'reviews' ),
	);

	$ids = array();
	foreach ( $tree as $term ) {
		$existing = get_term_by( 'slug', $term['slug'], 'category' );
		if ( $existing ) {
			$ids[ $term['slug'] ] = $existing->term_id;
			continue;
		}
		$parent_id = is_string( $term['parent'] )
			? ( $ids[ $term['parent'] ] ?? 0 )
			: $term['parent'];
		$result = wp_insert_term( $term['name'], 'category', array(
			'slug'   => $term['slug'],
			'parent' => $parent_id,
		) );
		if ( ! is_wp_error( $result ) ) {
			$ids[ $term['slug'] ] = $result['term_id'];
		}
	}

	update_option( 'category-metabox-enhanced_category', array(
		'type'            => 'radio',
		'context'         => 'side',
		'priority'        => 'default',
		'metabox_title'   => '',
		'indented'        => 1,
		'allow_new_terms' => 1,
	) );

	// Default to Block Editor but let the user switch per-post so we can
	// exercise both editor paths in a single environment.
	update_option( 'classic-editor-replace', 'block' );
	update_option( 'classic-editor-allow-users', 'allow' );

	update_option( 'cme_test_seeded', '1' );
}, 1 );
