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
        if (empty($this->api_key)) {
            error_log('Pexels API key is empty or not configured');
            return new \WP_Error('missing_api_key', __('Pexels API key is not configured', 'ai-blogger'));
        }

        $url = $this->base_url . 'search?query=' . urlencode($query) . '&per_page=' . $per_page;
        error_log('Pexels API request URL: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => $this->api_key
            ),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        error_log('Sending request to Pexels API with Authorization header: ' . $this->api_key);

        if (is_wp_error($response)) {
            error_log('Pexels API request failed: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        error_log('Pexels API response status code: ' . $status_code);
        
        if ($status_code !== 200) {
            error_log('Pexels API returned non-200 status code: ' . $status_code);
            error_log('Response body: ' . wp_remote_retrieve_body($response));
            return new \WP_Error('api_error', __('Pexels API error: HTTP ' . $status_code, 'ai-blogger'));
        }

        $body = wp_remote_retrieve_body($response);
        error_log('Pexels API raw response: ' . substr($body, 0, 500) . '...');
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Pexels API returned invalid JSON: ' . json_last_error_msg());
            return new \WP_Error('invalid_json', __('Invalid response from Pexels API', 'ai-blogger'));
        }

        // Debug the full response structure
        error_log('Pexels API response structure: ' . print_r($data, true));

        if (empty($data['photos'])) {
            error_log('Pexels API returned no photos for query: ' . $query);
            return new \WP_Error('no_images', __('No images found for this query', 'ai-blogger'));
        }

        error_log('Pexels API returned ' . count($data['photos']) . ' photos');
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
        error_log('Attempting to attach image from URL: ' . $image_url);
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Ensure the URL has a valid extension
        $url_parts = parse_url($image_url);
        $path_parts = pathinfo($url_parts['path']);
        
        // If no extension or invalid extension, add .jpg
        if (empty($path_parts['extension']) || !in_array(strtolower($path_parts['extension']), ['jpg', 'jpeg', 'png', 'gif'])) {
            $image_url .= (strpos($image_url, '?') === false ? '?' : '&') . 'ext=.jpg';
            error_log('Added extension to URL: ' . $image_url);
        }

        // Download the image
        error_log('Downloading image from Pexels');
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            error_log('Failed to download image: ' . $tmp->get_error_message());
            return $tmp;
        }
        
        error_log('Image downloaded successfully to temporary file: ' . $tmp);

        // Generate a proper filename with extension
        $filename = sanitize_file_name($title . '-' . time() . '.jpg');
        
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp
        );
        
        error_log('Attaching image to media library with filename: ' . $file_array['name']);

        // Attach the image to the media library
        $attachment_id = media_handle_sideload($file_array, 0, $title);
        
        if (is_wp_error($attachment_id)) {
            error_log('Failed to attach image: ' . $attachment_id->get_error_message());
            @unlink($file_array['tmp_name']);
            return $attachment_id;
        }

        error_log('Image successfully attached to media library with ID: ' . $attachment_id);
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
        $selected_image = $images[0];
        
        // Debug the image structure
        error_log('Selected image structure: ' . print_r($selected_image, true));
        
        // Ensure the image has the expected structure
        if (!isset($selected_image['src'])) {
            // If 'src' doesn't exist, check if the structure is different
            if (isset($selected_image['photos'][0]['src'])) {
                return $selected_image['photos'][0];
            }
            
            // Create a compatible structure if needed
            if (isset($selected_image['url'])) {
                $selected_image['src'] = array(
                    'original' => $selected_image['url'],
                    'large' => $selected_image['url'],
                    'medium' => $selected_image['url'],
                    'small' => $selected_image['url']
                );
            }
        }
        
        return $selected_image;
    }
}