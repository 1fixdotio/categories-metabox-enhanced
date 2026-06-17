<?php
/**
 * Plugin Name: Categories Metabox Enhanced — Playground Demo Notice
 * Description: Adds an admin notice on post-edit and posts-list screens explaining the Playground demo. Loaded only inside the Playground demo environment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return;
	}

	$relevant = in_array( $screen->id, array( 'post', 'edit-post', 'settings_page_category-metabox-enhanced' ), true );
	if ( ! $relevant ) {
		return;
	}

	$settings_url = admin_url( 'options-general.php?page=category-metabox-enhanced' );
	$list_url     = admin_url( 'edit.php' );
	?>
	<div class="notice notice-info is-dismissible" style="border-left-color:#2271b1;">
		<h2 style="margin:0.5em 0 0.25em;">Categories Metabox Enhanced — try it out</h2>
		<?php if ( 'post' === $screen->id ) : ?>
			<p style="margin:0.25em 0;">
				The Category taxonomy is configured as <strong>radio buttons</strong>.
				In the Block Editor sidebar, expand the <strong>Categories</strong> panel on the right to see the radio tree.
				Pick one — you can't pick two.
			</p>
			<p style="margin:0.25em 0;">
				<a href="<?php echo esc_url( $settings_url ); ?>">Switch the option type</a>
				to <code>select</code> for the drop-down variant, or to <code>checkbox</code> to disable the plugin's UI.
			</p>
		<?php elseif ( 'edit-post' === $screen->id ) : ?>
			<p style="margin:0.25em 0;">
				Hover any row to reveal <strong>Quick Edit</strong>, then tick a second category and save —
				the plugin's server-side enforcement coerces the post back to a single term.
				Same applies to <strong>Bulk Edit</strong>: select two posts, tick a category in the bulk form, and only that term survives on each.
			</p>
			<p style="margin:0.25em 0;">
				<a href="<?php echo esc_url( $settings_url ); ?>">Plugin settings</a>
				let you change the option type and toggle <em>Force selection</em>, which substitutes a default term server-side when the form submits empty.
			</p>
		<?php else : ?>
			<p style="margin:0.25em 0;">
				This is the plugin's settings page. Each hierarchical taxonomy can be configured independently.
				Try switching <strong>Category</strong> between <code>radio</code>, <code>select</code>, and <code>checkbox</code>,
				then <a href="<?php echo esc_url( $list_url ); ?>">go back to Posts</a> to see the change.
			</p>
		<?php endif; ?>
	</div>
	<?php
} );
