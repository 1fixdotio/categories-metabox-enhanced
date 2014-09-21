<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       http://1fix.io
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
	 * @var      string    $name    The ID of this plugin.
	 */
	private $name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @var      string    $name       The name of this plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct( $name, $version ) {

		$this->name = $name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Category_Metabox_Enhanced_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Category_Metabox_Enhanced_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->name, plugin_dir_url( __FILE__ ) . 'css/category-metabox-enhanced-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the dashboard.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Category_Metabox_Enhanced_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Category_Metabox_Enhanced_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->name, plugin_dir_url( __FILE__ ) . 'js/category-metabox-enhanced-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Display admin notice after plugin activated
	 *
	 * @since 0.2.0
	 */
	public function admin_notice_activation() {

		$screen = get_current_screen();

		if ( true == get_option( 'cme-display-activation-message' ) && 'plugins' == $screen->id ) {
			$html  = '<div class="updated">';
			$html .= '<p>';
				$html .= sprintf( __( 'The plugin <strong>Categories Metabox Enhanced is activated.</strong> Check out all the options for this plugin in the <strong><a href="%s">Settings</a></strong> page.', $this->name ), admin_url( 'options-general.php?page=' . $this->name ) );
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
		 *        Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
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
	 * @since    0.4.0
	 *
	 * @param array<string> $links Action links
	 * @return  array<string> Action links
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
	 * Build completely delete action url.
	 *
	 * @since 0.4.0
	 *
	 * @param  int    $post_id Post ID
	 * @return string    Action url
	 */
	public function get_action_url( $post_id ) {

		return wp_nonce_url( add_query_arg( array( 'action' => 'completely_delete', 'post' => $post_id ), admin_url( 'admin.php' ) ) );
	}

	/**
	 * Customize taxonomy metaboxes
	 *
	 * @since 0.3.0
	 */
	public function customize_taxonomy_metaboxes() {

		$taxes = of_cme_supported_taxonomies();

		foreach ( $taxes as $tax ) {
			$options = get_option( $this->name . '_' . $tax );
			$type = $options['type'];

			if ( $type != 'checkbox' ) {
				${$tax . "_metabox"} = new Taxonomy_Single_Term( $tax, array(), $type );
				${$tax . "_metabox"}->set( 'priority', 'default' );
			}
		}
	}

}
