<?php
/**
 * Admin Assets - Enqueue scripts and styles
 */

class ACF_Block_Copy_Admin_Assets {
    public static function enqueue_assets() {
        wp_enqueue_script(
            'acf-block-copy-editor',
            ACF_BLOCK_COPY_URL . 'assets/js/block-copy-editor.js',
            ['wp-blocks', 'wp-dom', 'wp-data', 'wp-element', 'wp-components'],
            ACF_BLOCK_COPY_VERSION,
            true,
        );

        wp_localize_script('acf-block-copy-editor', 'acfBlockCopyData', [
            'restApiUrl' => rest_url('acf-block-copy/v1/resolve-attachments'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style('acf-block-copy-editor-style', ACF_BLOCK_COPY_URL . 'assets/css/block-copy-editor.css', [], ACF_BLOCK_COPY_VERSION);
    }
}
