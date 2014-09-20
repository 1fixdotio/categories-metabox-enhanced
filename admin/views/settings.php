<?php

/**
 * Provide a dashboard view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       http://1fix.io
 * @since      0.4.0
 *
 * @package    Category_Metabox_Enhanced
 * @subpackage Category_Metabox_Enhanced/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">

	<div id="icon-themes" class="icon32"></div>
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<?php // settings_errors(); ?>

	<form method="post" action="options.php">
		<?php
			// $plugin = Completely_Delete::get_instance();
                        //
			// settings_fields( $plugin->get_plugin_slug() );
			// do_settings_sections( $plugin->get_plugin_slug() );
                        //
			// submit_button();

		?>
	</form>

</div><!-- /.wrap -->
