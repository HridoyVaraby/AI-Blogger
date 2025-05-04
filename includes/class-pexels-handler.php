<?php
namespace AI_Blogger;

defined('ABSPATH') || exit;

class Pexels_Handler {
    private $api_key;
    private $base_url = 'https://api.pexels.com/v1/';

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Search for relevant images on Pexels
     * 
     * @param string $query Search query
     * @param int $per_page Number of results per page
     * @return array|WP_Error Array of image data or WP_Error on failure
     */
    public function search_images($query, $per_page = 5) {
        $url = $this->base_url . 'search?query=' . urlencode($query) . '&per_page=' . $per_page;
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => $this->api_key
            )
        ));

        if (is_wp_error($response)) {
            error_log('Pexels API request failed: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['photos'])) {
            error_log('Pexels API returned no photos for query: ' . $query);
            return new \WP_Error('no_images', __('No images found for this query', 'ai-blogger'));
        }

        return $data['photos'];
    }

    /**
     * Download and attach image to WordPress media library
     * 
     * @param string $image_url URL of image to download
     * @param string $title Title for the media attachment
     * @return int|WP_Error Attachment ID or WP_Error on failure
     */
    public function attach_image_to_post($image_url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file_array = array(
            'name' => sanitize_file_name(basename($image_url)),
            'tmp_name' => $tmp
        );

        $attachment_id = media_handle_sideload($file_array, 0, $title);
        
        if (is_wp_error($attachment_id)) {
            error_log('Failed to attach image: ' . $attachment_id->get_error_message());
            @unlink($file_array['tmp_name']);
            return $attachment_id;
        }

        return $attachment_id;
    }

    /**
     * Get the most relevant image from search results
     * 
     * @param array $images Array of image data from Pexels
     * @param string $query Original search query
     * @return array Selected image data
     */
    public function select_most_relevant_image($images, $query) {
        // Simple selection - just pick the first result
        // Can be enhanced with more sophisticated relevance algorithms
        return $images[0];
    }
}