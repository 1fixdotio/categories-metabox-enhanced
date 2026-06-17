<?php
/**
 * Security regression coverage for Taxonomy_Single_Term::ajax_add_term().
 *
 * The classic metabox uses a custom authenticated Ajax endpoint rather than
 * the Block Editor's REST controller. These tests pin the server-side
 * authorization boundary so term creation cannot bypass taxonomy caps.
 */

class Test_Classic_Ajax_Add_Term extends WP_UnitTestCase {

	const SOURCE_TAX = 'cme_ajax_source';
	const TARGET_TAX = 'cme_ajax_target';

	/**
	 * Captured JSON response from wp_send_json_*.
	 *
	 * @var string
	 */
	private $last_response = '';

	/**
	 * Track metabox instances so their hooks can be removed in tear_down().
	 *
	 * @var Taxonomy_Single_Term[]
	 */
	private $instances = array();

	public function set_up() {
		parent::set_up();

		register_taxonomy(
			self::SOURCE_TAX,
			'post',
			array(
				'hierarchical' => true,
				'labels'       => array(
					'singular_name' => 'Source Term',
				),
			)
		);

		register_taxonomy(
			self::TARGET_TAX,
			'post',
			array(
				'hierarchical' => true,
				'labels'       => array(
					'singular_name' => 'Target Term',
				),
			)
		);

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ), 1, 1 );
		set_current_screen( 'ajax' );
	}

	public function tear_down() {
		foreach ( $this->instances as $instance ) {
			remove_action( 'add_meta_boxes', array( $instance, 'add_input_element' ) );
			remove_action( 'admin_footer', array( $instance, 'js_checkbox_transform' ) );
			remove_action( 'wp_ajax_taxonomy_single_term_add', array( $instance, 'ajax_add_term' ) );
			remove_action( 'set_object_terms', array( $instance, 'maybe_resave_terms' ), 10 );
		}

		$this->instances     = array();
		$this->last_response = '';

		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ), 1 );
		wp_set_current_user( 0 );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		set_current_screen( 'front' );

		unregister_taxonomy( self::SOURCE_TAX );
		unregister_taxonomy( self::TARGET_TAX );

		parent::tear_down();
	}

	public function get_die_handler() {
		return array( $this, 'die_handler' );
	}

	public function die_handler( $message ) {
		$this->last_response .= ob_get_clean();

		if ( '' === $this->last_response ) {
			throw new WPAjaxDieStopException( is_scalar( $message ) ? (string) $message : '0' );
		}

		throw new WPAjaxDieContinueException( is_scalar( $message ) ? (string) $message : '0' );
	}

	private function new_metabox( $taxonomy, $allow_new_terms = true ) {
		$instance = new Taxonomy_Single_Term( $taxonomy, array(), 'radio' );
		$instance->set( 'allow_new_terms', $allow_new_terms );
		$this->instances[] = $instance;

		return $instance;
	}

	private function invoke_ajax_add_term( $instance, array $post ) {
		$this->last_response = '';
		$_POST               = $post;
		$_GET                = array();
		$_REQUEST            = $post;
		$buffer_level        = ob_get_level();

		ini_set( 'implicit_flush', false );
		ob_start();

		try {
			$instance->ajax_add_term();
			$this->fail( 'ajax_add_term() should terminate via wp_send_json().' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected for wp_send_json_* responses.
		} catch ( WPAjaxDieStopException $e ) {
			// Error path with no output — leave $last_response empty for asserts.
		}

		if ( ob_get_level() > $buffer_level ) {
			$buffer = ob_get_clean();
			if ( '' !== $buffer ) {
				$this->last_response .= $buffer;
			}
		}

		return json_decode( $this->last_response, true );
	}

	public function test_author_cannot_create_terms_via_classic_ajax_handler() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );

		$instance = $this->new_metabox( self::SOURCE_TAX, true );

		$this->assertFalse(
			current_user_can( get_taxonomy( self::SOURCE_TAX )->cap->edit_terms ),
			'Author fixtures must lack the taxonomy term-management capability for this regression to be meaningful.'
		);

		$response = $this->invoke_ajax_add_term(
			$instance,
			array(
				'nonce'     => wp_create_nonce( 'taxonomy_' . self::SOURCE_TAX ),
				'term_name' => 'Unauthorized Term',
				'taxonomy'  => self::SOURCE_TAX,
			)
		);

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'], 'Low-privilege users must not be able to create terms through the classic metabox Ajax endpoint.' );
		$this->assertNull( term_exists( 'Unauthorized Term', self::SOURCE_TAX ) );
	}

	public function test_ajax_handler_rejects_requests_for_a_different_taxonomy_instance() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$instance = $this->new_metabox( self::SOURCE_TAX, true );

		$response = $this->invoke_ajax_add_term(
			$instance,
			array(
				'nonce'     => wp_create_nonce( 'taxonomy_' . self::TARGET_TAX ),
				'term_name' => 'Wrong Taxonomy Term',
				'taxonomy'  => self::TARGET_TAX,
			)
		);

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'], 'Each metabox instance must only service add-term Ajax requests for its own taxonomy.' );
		$this->assertNull( term_exists( 'Wrong Taxonomy Term', self::TARGET_TAX ) );
	}
}
