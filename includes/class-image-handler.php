<?php
/**
 * Image Download and Upload Handler
 * Handles downloading images from URLs and uploading them to the destination environment
 */

class ACF_Block_Copy_Image_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('save_post', [$this, 'process_block_images_on_save'], 20, 1);
        add_action('rest_insert_post', [$this, 'process_block_images_on_rest_save'], 20, 3);
    }

    /**
     * Hook into post save event to process images
     */
    public function process_block_images_on_save($post_id) {
        // Skip if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Get the post
        $post = get_post($post_id);
        if (!$post || !isset($post->post_content)) {
            return;
        }

        // Process the post content
        $this->process_post_content($post_id, $post->post_content);
    }

    /**
     * Hook into REST API save events
     */
    public function process_block_images_on_rest_save($post, $post_obj, $request) {
        // Get content from the request
        $params = $request->get_json_params();
        if (isset($params['content'])) {
            $this->process_post_content($post->ID, $params['content']);
        }
    }

    /**
     * Main processing function for post content
     */
    private function process_post_content($post_id, $post_content) {
        // Extract all image URLs from blocks
        $image_urls = $this->extract_image_urls_from_content($post_content);
        if (empty($image_urls)) {
            return;
        }

        // Download and upload images, get mapping of URL to new attachment ID
        $url_to_id_map = $this->download_and_upload_images($image_urls);

        if (empty($url_to_id_map)) {
            return;
        }
        // Replace URLs with attachment IDs in post content
        $updated_content = $this->replace_urls_with_ids($post_content, $url_to_id_map);

        // Save the updated content
        if ($updated_content !== $post_content) {
            // Temporarily remove the hook to avoid infinite recursion
            remove_action('save_post', [$this, 'process_block_images_on_save'], 20);

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $updated_content,
            ]);

            // Re-add the hook
            add_action('save_post', [$this, 'process_block_images_on_save'], 20, 1);

            // Log the update
            error_log('[ACF Block Copy] Updated post ' . $post_id . ' with ' . count($url_to_id_map) . ' new attachments');
        }
    }

    /**
     * Extract all image URLs from block content
     * Looks for URLs that appear in block JSON data
     */
    private function extract_image_urls_from_content($content) {
        $urls = [];

        // Match all strings between your custom tags
        if (preg_match_all('/_image-url-start_(.*?)_image-url-end_/', $content, $matches)) {
            foreach ($matches[1] as $url) {
				if (!empty($url) && $this->is_valid_image_url($url)) {
					$urls[] = $url;
                }
            }
        }
		
        return array_unique($urls);
    }

    /**
     * Recursively search for image URLs in data structure
     */
    private function recursively_find_image_urls($data, &$urls, $visited = []) {
        if (!is_array($data)) {
            return;
        }

        // Prevent infinite recursion
        $data_hash = spl_object_hash((object) $data);
        if (in_array($data_hash, $visited, true)) {
            return;
        }
        $visited[] = $data_hash;

        foreach ($data as $key => $value) {
            // Skip field mapping keys
            if (strpos($key, '_') === 0) {
                continue;
            }

            // Check if value is a URL (http/https)
            if (is_string($value) && $this->is_valid_image_url($value)) {
                $urls[] = $value;
            }

            // Recurse into nested arrays/objects
            if (is_array($value)) {
                $this->recursively_find_image_urls($value, $urls, $visited);
            }
        }
    }

    /**
     * Validate if string is a valid image URL
     */
    private function is_valid_image_url($url) {
        // Check if it's a valid URL
        if (!wp_http_validate_url($url)) {
            return false;
        }

        // Check if it's an HTTP/HTTPS URL
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        // Check if it has a common image extension
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'];
        $url_path = wp_parse_url($url, PHP_URL_PATH);

        if (!$url_path) {
            return false;
        }

        $extension = strtolower(pathinfo($url_path, PATHINFO_EXTENSION));

        return in_array($extension, $image_extensions, true);
    }

    /**
     * Download images from URLs and upload them to media library
     */
    private function download_and_upload_images($image_urls) {
        $url_to_id_map = [];

        // Require WordPress media functions
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        foreach ($image_urls as $url) {
            // Check if this URL was already processed in this session
            $cached_id = $this->get_cached_attachment_id($url);
            if ($cached_id) {
                $url_to_id_map[$url] = $cached_id;
                continue;
            }

            // Download the image
            $temp_file = download_url($url, 300);

            if (is_wp_error($temp_file)) {
                error_log('[ACF Block Copy] Failed to download image from ' . $url . ': ' . $temp_file->get_error_message());
                continue;
            }

            // Prepare file array for media upload
            $file_array = [
                'name' => basename($url),
                'tmp_name' => $temp_file,
            ];

            // Upload the file
            $attachment_id = media_handle_sideload($file_array, 0, null, ['test_form' => false]);

            if (is_wp_error($attachment_id)) {
                error_log('[ACF Block Copy] Failed to upload image: ' . $attachment_id->get_error_message());
                @unlink($temp_file);
                continue;
            }

            // Cache the mapping
            $this->cache_attachment_id($url, $attachment_id);

            $url_to_id_map[$url] = $attachment_id;

            error_log('[ACF Block Copy] Successfully uploaded image from ' . $url . ' as attachment ID ' . $attachment_id);
        }

        return $url_to_id_map;
    }

    /**
     * Replace URLs with attachment IDs in block content
     */
    private function replace_urls_with_ids($content, $url_to_id_map) {
        $updated_content = $content;

        foreach ($url_to_id_map as $url => $attachment_id) {
            // Replace quoted URL with quoted ID
            // This handles JSON strings wrapped in quotes
            $updated_content = str_replace('"_image-url-start_' . $url . '_image-url-end_"', (string) $attachment_id, $updated_content);
        }

        return $updated_content;
    }

    /**
     * Cache attachment ID for URL to avoid duplicate uploads in same session
     */
    private function cache_attachment_id($url, $attachment_id) {
        $cache_key = 'acf_block_copy_url_to_id_' . md5($url);
        wp_cache_set($cache_key, $attachment_id, 'acf_block_copy', 3600);
    }

    /**
     * Get cached attachment ID for URL
     */
    private function get_cached_attachment_id($url) {
        $cache_key = 'acf_block_copy_url_to_id_' . md5($url);
        return wp_cache_get($cache_key, 'acf_block_copy');
    }

    /**
     * Check if URL is from same site (avoid re-downloading own uploads)
     */
    private function is_external_url($url) {
        $home_url = home_url();
        return strpos($url, $home_url) !== 0;
    }
}
