<?php
/**
 * Block Copy Manager - Core plugin functionality
 */

class ACF_Block_Copy_Manager {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Plugin hooks will be added here
	}

	/**
	 * Extract attachment IDs from block content
	 *
	 * @param string $block_content The block HTML content
	 * @return array Array of attachment IDs found in the block
	 */
	public static function extract_attachment_ids( $block_content ) {
		// Parse the block JSON to find all numeric IDs that could be attachments
		$attachment_ids = array();

		// Match JSON blocks with data attributes
		if ( preg_match_all( '/"data"\s*:\s*({.*?})\s*,\s*"mode"/', $block_content, $matches ) ) {
			foreach ( $matches[1] as $json_str ) {
				// Decode JSON
				$data = json_decode( '{' . $json_str . '}', true );
				if ( is_array( $data ) ) {
					self::recursively_find_attachment_ids( $data, $attachment_ids );
				}
			}
		}

		// Return unique IDs
		return array_unique( array_filter( $attachment_ids ) );
	}

	/**
	 * Recursively search array for potential attachment IDs
	 *
	 * @param array $array The array to search
	 * @param array $attachment_ids Reference to accumulate IDs
	 */
	private static function recursively_find_attachment_ids( $array, &$attachment_ids ) {
		if ( ! is_array( $array ) ) {
			return;
		}

		foreach ( $array as $key => $value ) {
			// Skip field mapping keys (starting with _)
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			// Check if value is a numeric ID (attachment ID)
			if ( is_numeric( $value ) && $value > 0 && (int) $value === $value ) {
				$attachment_ids[] = (int) $value;
			}

			// If value is array, recurse
			if ( is_array( $value ) ) {
				self::recursively_find_attachment_ids( $value, $attachment_ids );
			}
		}
	}

	/**
	 * Get attachment URL by ID
	 *
	 * @param int $attachment_id The attachment post ID
	 * @return string|false The attachment URL or false if not found
	 */
	public static function get_attachment_url( $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		return $url ? $url : false;
	}

	/**
	 * Get multiple attachment URLs
	 *
	 * @param array $attachment_ids Array of attachment IDs
	 * @return array Associative array of ID => URL
	 */
	public static function get_attachment_urls( $attachment_ids ) {
		$urls = array();
		
		foreach ( $attachment_ids as $id ) {
			$url = self::get_attachment_url( $id );
			if ( $url ) {
				$urls[ $id ] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Replace attachment IDs with URLs in block content
	 *
	 * @param string $block_content The block HTML content
	 * @param array $id_to_url Mapping of ID => URL
	 * @return string Modified block content with URLs instead of IDs
	 */
	public static function replace_ids_with_urls( $block_content, $id_to_url ) {
		// Parse block to find and replace IDs
		foreach ( $id_to_url as $id => $url ) {
			// Match numeric IDs in JSON, handling various contexts
			// This pattern matches IDs that are either:
			// 1. Values in JSON (preceded by colon or comma, followed by comma or closing bracket)
			// 2. Array elements (preceded by comma or bracket, followed by comma or bracket)
			$pattern = '/"(' . preg_quote( $id ) . '"|\b' . preg_quote( $id ) . '\b)(?=\s*[,\]\}])/';

			// Replace with quoted URL string in JSON context
			$block_content = preg_replace_callback(
				$pattern,
				function( $matches ) use ( $url ) {
					return '"' . esc_url( $url ) . '"';
				},
				$block_content
			);
		}

		return $block_content;
	}
}
