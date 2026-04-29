<?php
/**
 * Seed script for the Playground demo.
 *
 * Configures the Category taxonomy to use the radio single-term UI, creates
 * a handful of categories so the radio panel has options, and seeds a couple
 * of posts so the edit list and Quick Edit / Bulk Edit flows are immediately
 * usable.
 *
 * Run from the Blueprint's runPHP step after wp-load.php is loaded.
 */

if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

// Idempotency guard: Playground may persist state (OPFS) and re-run the
// Blueprint, in which case we don't want duplicate categories or posts.
if ( get_option( 'cme_demo_seeded' ) ) {
	return;
}

// 1. Switch the built-in Category taxonomy to the radio single-term UI.
//    The plugin's Settings page writes this same option key. Pre-seeding
//    it means the demo lands on the radio UI on first load instead of the
//    default checkbox UI.
update_option(
	'category-metabox-enhanced_category',
	array(
		'type'            => 'radio',
		'context'         => 'side',
		'priority'        => 'default',
		'metabox_title'   => '',
		'indented'        => 1,
		'allow_new_terms' => 1,
		'force_selection' => 1,
	)
);

// 2. Seed categories. WordPress already creates "Uncategorized" on install;
//    we add four more so the radio list has something to demonstrate.
$category_slugs = array(
	'tutorials' => 'Tutorials',
	'news'      => 'News',
	'reviews'   => 'Reviews',
	'opinion'   => 'Opinion',
);

$category_ids = array();

foreach ( $category_slugs as $slug => $name ) {
	$existing = get_term_by( 'slug', $slug, 'category' );
	if ( $existing instanceof WP_Term ) {
		$category_ids[ $slug ] = (int) $existing->term_id;
		continue;
	}

	$result = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	if ( ! is_wp_error( $result ) ) {
		$category_ids[ $slug ] = (int) $result['term_id'];
	}
}

// 3. Seed a couple of posts assigned to different categories. Two posts is
//    enough for the user to see the metabox UI on edit, and for Bulk Edit
//    to have multiple rows to act on.
$posts = array(
	array(
		'title'    => 'Welcome to the Categories Metabox Enhanced demo',
		'content'  => "This post is here so you have something to open in the editor.\n\nOpen this post in the Block Editor and look at the right sidebar — the Categories panel renders as a radio tree. Try the same in the Classic Editor (you can switch via the Classic Editor plugin) and you'll see radio buttons instead of checkboxes.\n\nQuick Edit and Bulk Edit on the posts list still render WordPress's default checkbox UI, but server-side enforcement coerces multi-term submissions to a single term — try ticking two boxes in Quick Edit and saving.",
		'category' => 'tutorials',
	),
	array(
		'title'    => 'Second post — try Quick Edit on this one',
		'content'  => "Hover over a row on Posts → All Posts to reveal Quick Edit. Tick a second category and save — you'll only see one survive.",
		'category' => 'news',
	),
);

foreach ( $posts as $item ) {
	$category_id = isset( $category_ids[ $item['category'] ] ) ? $category_ids[ $item['category'] ] : 0;

	$post_id = wp_insert_post(
		array(
			'post_title'    => $item['title'],
			'post_content'  => $item['content'],
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'post_category' => $category_id ? array( $category_id ) : array(),
		)
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		error_log( 'CME demo seed: wp_insert_post failed for ' . $item['title'] );
	}
}

update_option( 'cme_demo_seeded', 1 );

error_log( 'CME demo seed: completed' );
