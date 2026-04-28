<?php
/**
 * Plugin Name: Categories Metabox Enhanced — E2E Helper
 * Description: Mounted by wp-env. Registers two custom hierarchical
 * taxonomies and pins plugin settings so Playwright specs run against a
 * known configuration. Dev-only; never ship this file.
 *
 * Two taxonomies are needed to disentangle our enforcement from WP core's
 * built-in default_term auto-assignment:
 *   - cme_e2e            registered with default_term, exercises the
 *                        "select-mode + radio-mode + Block Editor" specs.
 *   - cme_e2e_no_default registered without default_term, exercises the
 *                        REST sentinel-substitution and slug-preservation
 *                        regression specs (where WP core would otherwise
 *                        mask our filter's behavior).
 *
 * Plugin options are seeded via a sentinel so the Settings-page spec can
 * mutate force_selection and observe persistence across a reload. Tests
 * that depend on a baseline call back into wp-cli to reset.
 */

defined( 'ABSPATH' ) || exit;

const CME_E2E_TAX            = 'cme_e2e';
const CME_E2E_TAX_NO_DEFAULT = 'cme_e2e_no_default';

add_action( 'init', static function () {
	register_taxonomy(
		CME_E2E_TAX,
		array( 'post' ),
		array(
			'label'             => 'E2E Taxonomy',
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'rest_base'         => 'cme_e2e_terms',
			'show_admin_column' => true,
			'default_term'      => array(
				'name' => 'General',
				'slug' => 'general',
			),
		)
	);

	register_taxonomy(
		CME_E2E_TAX_NO_DEFAULT,
		array( 'post' ),
		array(
			'label'        => 'E2E No Default',
			'hierarchical' => true,
			'show_ui'      => true,
			'show_in_rest' => true,
			'rest_base'    => 'cme_e2e_no_default_terms',
		)
	);

	if ( ! get_option( 'cme_e2e_seeded' ) ) {
		update_option( 'category-metabox-enhanced_' . CME_E2E_TAX, array(
			'type'            => 'radio',
			'context'         => 'side',
			'priority'        => 'default',
			'metabox_title'   => 'E2E Taxonomy',
			'indented'        => 1,
			'allow_new_terms' => 1,
			'force_selection' => 1,
		) );

		update_option( 'category-metabox-enhanced_' . CME_E2E_TAX_NO_DEFAULT, array(
			'type'            => 'radio',
			'context'         => 'side',
			'priority'        => 'default',
			'metabox_title'   => 'E2E No Default',
			'indented'        => 1,
			'allow_new_terms' => 1,
			'force_selection' => 1,
		) );

		update_option( 'cme_e2e_seeded', '1' );
	}

	// Idempotent term seeding — safe to run on every init since each branch
	// no-ops once the term exists.
	foreach ( array(
		array( 'tax' => CME_E2E_TAX,            'name' => 'News',    'slug' => 'news' ),
		array( 'tax' => CME_E2E_TAX,            'name' => 'Reviews', 'slug' => 'reviews' ),
		array( 'tax' => CME_E2E_TAX_NO_DEFAULT, 'name' => 'Alpha',   'slug' => 'alpha' ),
		array( 'tax' => CME_E2E_TAX_NO_DEFAULT, 'name' => 'Beta',    'slug' => 'beta' ),
	) as $term ) {
		if ( ! get_term_by( 'slug', $term['slug'], $term['tax'] ) ) {
			wp_insert_term( $term['name'], $term['tax'], array( 'slug' => $term['slug'] ) );
		}
	}
}, 1 );

/**
 * Allow application passwords on http://localhost. WordPress gates them on
 * is_ssl() || wp_is_local_environment(); wp-env doesn't always mark itself
 * local, so force-allow here for the e2e environment only.
 */
add_filter( 'wp_is_application_passwords_available', '__return_true' );
