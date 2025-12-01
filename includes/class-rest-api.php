<?php
/**
 * REST API Endpoints for Block Copy functionality
 */

class ACF_Block_Copy_REST_API {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register custom REST API endpoints
	 */
	public function register_endpoints() {
		// Endpoint to resolve attachment IDs to URLs
		register_rest_route(
			'acf-block-copy/v1',
			'/resolve-attachments',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'resolve_attachments' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'attachment_ids' => array(
						'type'              => 'array',
						'items'             => array( 'type' => 'integer' ),
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_ids' ),
					),
				),
			)
		);
	}

	/**
	 * Permission check for REST endpoints
	 * Allow for block editor users
	 */
	public function check_permissions() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize attachment IDs
	 */
	public function sanitize_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_map( 'intval', $ids );
	}

	/**
	 * Resolve attachment IDs to URLs
	 *
	 * @param WP_REST_Request $request The REST request
	 * @return WP_REST_Response
	 */
	public function resolve_attachments( WP_REST_Request $request ) {
		$attachment_ids = $request->get_param( 'attachment_ids' );

		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No attachment IDs provided',
					'urls'    => array(),
				),
				400
			);
		}

		// Get URLs for all provided attachment IDs
		$urls = ACF_Block_Copy_Manager::get_attachment_urls( $attachment_ids );

		return new WP_REST_Response(
			array(
				'success' => true,
				'urls'    => $urls,
			),
			200
		);
	}
}
