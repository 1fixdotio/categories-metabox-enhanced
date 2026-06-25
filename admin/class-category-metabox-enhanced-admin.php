<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       https://1fix.io
 * @since      0.1.0
 *
 * @package    Category_Metabox_Enhanced
 * @subpackage Category_Metabox_Enhanced/includes
 */

/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    Category_Metabox_Enhanced
 * @subpackage Category_Metabox_Enhanced/admin
 * @author     1Fix.io <1fixdotio@gmail.com>
 */
class Category_Metabox_Enhanced_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string $name The ID of this plugin.
	 */
	private $name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * The hook suffix of the plugin's settings page.
	 *
	 * @since    0.4.0
	 * @access   private
	 * @var      string $plugin_screen_hook_suffix The settings page hook suffix.
	 */
	private $plugin_screen_hook_suffix;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @var      string $name The name of this plugin.
	 * @var      string $version The version of this plugin.
	 */
	public function __construct( $name, $version ) {

		$this->name    = $name;
		$this->version = $version;

	}

	/**
	 * Display admin notice after plugin activated
	 *
	 * @since 0.2.0
	 */
	public function admin_notice_activation() {

		$screen = get_current_screen();

		if ( true === (boolean) get_option( 'cme-display-activation-message' ) && 'plugins' === $screen->id ) {
			$html  = '<div class="updated">';
			$html .= '<p>';
			$html .= sprintf( __( 'Replace checkboxes in the Categories metabox with radio buttons or a select drop-down in the <strong><a href="%s">Settings</a></strong> page.', $this->name ), admin_url( 'options-general.php?page=' . $this->name ) );
			$html .= '</p>';
			$html .= '</div><!-- /.updated -->';

			echo $html;

			delete_option( 'cme-display-activation-message' );

		}
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    0.4.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		 *
		 * Administration Menus: http://codex.wordpress.org/Administration_Menus
		 */
		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'Categories Metabox Enhanced Settings', $this->name ),
			__( 'Categories Metabox Enhanced', $this->name ),
			'manage_options',
			$this->name,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    0.4.0
	 */
	public function display_plugin_admin_page() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/settings.php';
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @param array<string> $links Action links.
	 *
	 * @return  array<string> Action links
	 * @since    0.4.0
	 *
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->name ) . '">' . __( 'Settings' ) . '</a>',
			),
			$links
		);

	}

	/**
	 * Register the single-term metabox for every post type the taxonomy
	 * is associated with. Whether the metabox actually renders is decided
	 * per-post in suppress_classic_metabox_in_block_editor(), which runs
	 * later on add_meta_boxes.
	 *
	 * @since 0.3.0
	 */
	public function customize_taxonomy_metaboxes() {

		foreach ( of_cme_supported_taxonomies() as $tax ) {
			$options = of_cme_get_taxonomy_options( $tax );

			if ( ! of_cme_is_single_term_type( $options['type'] ) ) {
				continue;
			}

			$metabox = new Taxonomy_Single_Term( $tax, array(), $options['type'] );

			$schema = of_cme_get_defaults();
			unset( $schema['type'] );
			foreach ( array_keys( $schema ) as $key ) {
				$metabox->set( $key, $options[ $key ] );
			}
		}
	}

	/**
	 * Remove the classic metabox when the post will render in the Block Editor.
	 *
	 * Filtering at the post-type level (use_block_editor_for_post_type) misses
	 * the Classic Editor plugin's "allow users to switch" mode, where the
	 * post-type default is Block but individual posts may be opened in classic.
	 * use_block_editor_for_post() respects that per-post preference.
	 *
	 * @since 0.8.0
	 *
	 * @param string       $post_type Post type slug.
	 * @param WP_Post|null $post      Post being edited.
	 */
	public function suppress_classic_metabox_in_block_editor( $post_type, $post ) {

		if ( ! is_object( $post ) || ! use_block_editor_for_post( $post ) ) {
			return;
		}

		foreach ( of_cme_supported_taxonomies() as $tax ) {
			$options = of_cme_get_taxonomy_options( $tax );
			if ( ! of_cme_is_single_term_type( $options['type'] ) ) {
				continue;
			}
			// Only suppress the classic metabox if we actually ship a sidebar
			// replacement; non-REST taxonomies fall back to the legacy metabox.
			$tax_obj = get_taxonomy( $tax );
			if ( ! $tax_obj || empty( $tax_obj->show_in_rest ) ) {
				continue;
			}
			remove_meta_box( $tax . '_input_element', $post_type, $options['context'] );
		}
	}

	/**
	 * Build the JS payload describing every supported taxonomy whose UI
	 * should be replaced in the Block Editor sidebar.
	 *
	 * @since 0.8.0
	 * @return array<int, array<string, mixed>>
	 */
	private function block_editor_taxonomies() {
		$payload = array();

		foreach ( of_cme_supported_taxonomies() as $tax_slug ) {
			$options = of_cme_get_taxonomy_options( $tax_slug );

			if ( ! of_cme_is_single_term_type( $options['type'] ) ) {
				continue;
			}

			$tax = get_taxonomy( $tax_slug );
			if ( ! $tax || empty( $tax->show_in_rest ) ) {
				continue;
			}

			$payload[] = array(
				'slug'            => $tax_slug,
				'rest_base'       => $tax->rest_base ? $tax->rest_base : $tax_slug,
				'type'            => $options['type'],
				'indented'        => (bool) $options['indented'],
				'allow_new_terms' => (bool) $options['allow_new_terms'],
				'force_selection' => (bool) $options['force_selection'],
				'panel_title'     => $options['metabox_title'] ? $options['metabox_title'] : $tax->labels->name,
				'post_types'      => array_values( (array) $tax->object_type ),
			);
		}

		return $payload;
	}

	/**
	 * Enqueue the Block Editor sidebar panel.
	 *
	 * @since 0.8.0
	 */
	public function enqueue_block_editor_assets() {

		$taxonomies = $this->block_editor_taxonomies();
		if ( empty( $taxonomies ) ) {
			return;
		}

		$asset_file = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/block-editor/build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			$this->name . '-block-editor',
			plugin_dir_url( __FILE__ ) . 'js/block-editor/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Register translations so the panel's __() / sprintf() strings can
		// load from JSON language packs; the bundle declares wp-i18n as a dep.
		wp_set_script_translations( $this->name . '-block-editor', 'of-cme' );

		wp_add_inline_script(
			$this->name . '-block-editor',
			'window.ofCmeBlockEditor = ' . wp_json_encode( array( 'taxonomies' => $taxonomies ) ) . ';',
			'before'
		);
	}

}
