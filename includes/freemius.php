<?php
if ( ! function_exists( 'of_cme_fs' ) ) {
	// Create a helper function for easy SDK access.
	function of_cme_fs() {
		global $of_cme_fs;

		if ( ! isset( $of_cme_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			$of_cme_fs = fs_dynamic_init( array(
				'id'             => '7799',
				'slug'           => 'categories-metabox-enhanced',
				'type'           => 'plugin',
				'public_key'     => 'pk_358975b931ebdd8eb7c33cecdf858',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => false,
				'menu'           => array(
					'slug'       => 'category-metabox-enhanced',
					'first-path' => 'options-general.php?page=category-metabox-enhanced',
					'account'    => true,
					'parent'     => array(
						'slug' => 'options-general.php',
					),
				),
			) );
		}

		return $of_cme_fs;
	}

	// Init Freemius.
	of_cme_fs();
	// Signal that SDK was initiated.
	do_action( 'of_cme_fs_loaded' );
}

of_cme_fs()->add_action( 'after_uninstall', 'of_cme_fs_uninstall_cleanup' );
/**
 * The uninstall function required by Freemius.
 *
 * @since 0.8.0
 */
function of_cme_fs_uninstall_cleanup() {

	// If uninstall not called from WordPress, then exit.
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		exit;
	}

	$taxes = get_taxonomies();
	foreach ( $taxes as $tax ) {
		if ( is_taxonomy_hierarchical( $tax ) ) {
			delete_option( 'category-metabox-enhanced_' . $tax );
		}
	}

	delete_option( 'cme-display-activation-message' );
	/**
	 * @todo Delete options in whole network
	 */

}
