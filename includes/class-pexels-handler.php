<?php
namespace AI_Blogger;

defined('ABSPATH') || exit;

class Pexels_Handler {
    /**
     * Debug logging function that only logs in development environments
     * 
     * @param string $message The message to log
     * @return void
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log($message);
        }
    }
    private $api_key;
    private $base_url = 'https://api.pexels.com/v1/';

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Search for relevant images on Pexels
     * 
     * @param string|array $query Search query or array of keywords
     * @param int $per_page Number of results per page
     * @return array|WP_Error Array of image data or WP_Error on failure
     */
    public function search_images($query, $per_page = 5) {
        if (empty($this->api_key)) {
            $this->log_debug('Pexels API key is empty or not configured');
            return new \WP_Error('missing_api_key', __('Pexels API key is not configured', 'ai-blogger'));
        }
        
        // Handle array of keywords
        if (is_array($query)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            $this->log_debug('Received array of keywords for image search: ' . print_r($query, true));
            
            // Try the first keyword (most relevant)
            if (!empty($query[0])) {
                $result = $this->perform_search($query[0], $per_page);
                
                // If we got results, return them
                if (!is_wp_error($result) && !empty($result)) {
                    return $result;
                }
                
                // If no results with first keyword, try combining top keywords
                if (count($query) >= 2) {
                    $combined_query = $query[0] . ' ' . $query[1];
                    $this->log_debug('First keyword returned no results, trying combined keywords: ' . $combined_query);
                    
                    $result = $this->perform_search($combined_query, $per_page);
                    if (!is_wp_error($result) && !empty($result)) {
                        return $result;
                    }
                }
                
                // Try other keywords if still no results
                for ($i = 1; $i < min(count($query), 3); $i++) {
                    $this->log_debug('Trying alternative keyword: ' . $query[$i]);
                    $result = $this->perform_search($query[$i], $per_page);
                    
                    if (!is_wp_error($result) && !empty($result)) {
                        return $result;
                    }
                }
            }
            
            // If we've tried keywords and got nothing, use the original query as a string
            $query = implode(' ', array_slice($query, 0, 3));
            $this->log_debug('No results with individual keywords, using combined query: ' . $query);
        }
        
        return $this->perform_search($query, $per_page);
    }
    
    /**
     * Perform the actual search request to Pexels API
     * 
     * @param string $query Search query
     * @param int $per_page Number of results per page
     * @return array|WP_Error Array of image data or WP_Error on failure
     */
    private function perform_search($query, $per_page = 5) {
        $url = $this->base_url . 'search?query=' . urlencode($query) . '&per_page=' . $per_page;
        $this->log_debug('Pexels API request URL: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => $this->api_key
            ),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        $this->log_debug('Sending request to Pexels API with query: ' . $query);

        if (is_wp_error($response)) {
            $this->log_debug('Pexels API request failed: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $this->log_debug('Pexels API response status code: ' . $status_code);
        
        if ($status_code !== 200) {
            $this->log_debug('Pexels API returned non-200 status code: ' . $status_code);
            $this->log_debug('Response body: ' . wp_remote_retrieve_body($response));
            // translators: %d is the HTTP status code returned by the Pexels API
            return new \WP_Error('api_error', sprintf(__('Pexels API error: HTTP %d', 'ai-blogger'), $status_code));
        }

        $body = wp_remote_retrieve_body($response);
        $this->log_debug('Pexels API raw response: ' . substr($body, 0, 500) . '...');
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('Pexels API returned invalid JSON: ' . json_last_error_msg());
            return new \WP_Error('invalid_json', __('Invalid response from Pexels API', 'ai-blogger'));
        }

        // Debug the full response structure
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        $this->log_debug('Pexels API response structure: ' . print_r($data, true));

        if (empty($data['photos'])) {
            $this->log_debug('Pexels API returned no photos for query: ' . $query);
            return new \WP_Error('no_images', __('No images found for this query', 'ai-blogger'));
        }

        $this->log_debug('Pexels API returned ' . count($data['photos']) . ' photos');
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
        $this->log_debug('Attempting to attach image from URL: ' . $image_url);
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Ensure the URL has a valid extension
        $url_parts = wp_parse_url($image_url);
        $path_parts = pathinfo($url_parts['path']);
        
        // If no extension or invalid extension, add .jpg
        if (empty($path_parts['extension']) || !in_array(strtolower($path_parts['extension']), ['jpg', 'jpeg', 'png', 'gif'])) {
            $image_url .= (strpos($image_url, '?') === false ? '?' : '&') . 'ext=.jpg';
            $this->log_debug('Added extension to URL: ' . $image_url);
        }

        // Download the image
        $this->log_debug('Downloading image from Pexels');
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            $this->log_debug('Failed to download image: ' . $tmp->get_error_message());
            return $tmp;
        }
        
        $this->log_debug('Image downloaded successfully to temporary file: ' . $tmp);

        // Generate a proper filename with extension
        $filename = sanitize_file_name($title . '-' . time() . '.jpg');
        
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp
        );
        
        $this->log_debug('Attaching image to media library with filename: ' . $file_array['name']);

        // Attach the image to the media library
        $attachment_id = media_handle_sideload($file_array, 0, $title);
        
        if (is_wp_error($attachment_id)) {
            $this->log_debug('Failed to attach image: ' . $attachment_id->get_error_message());
            if (file_exists($file_array['tmp_name'])) {
                wp_delete_file($file_array['tmp_name']);
            }
            return $attachment_id;
        }

        $this->log_debug('Image successfully attached to media library with ID: ' . $attachment_id);
        return $attachment_id;
    }

    /**
     * Extract keywords from post content
     * 
     * @param string $content Post content to analyze
     * @return array Array of relevant keywords
     */
    private function extract_keywords($content) {
        // Remove HTML tags
        $text = wp_strip_all_tags($content);
        
        // Remove common stop words
        $stop_words = array('the', 'and', 'a', 'to', 'of', 'in', 'is', 'it', 'that', 'for', 'on', 'with', 'as', 'at', 'by', 'from');
        
        // Extract words and count frequency
        $words = preg_split('/\s+/', strtolower($text));
        $word_counts = array_count_values($words);
        
        // Filter out stop words and short words
        $keywords = array();
        foreach ($word_counts as $word => $count) {
            if (!in_array($word, $stop_words) && strlen($word) > 3 && $count > 1) {
                $keywords[$word] = $count;
            }
        }
        
        // Sort by frequency
        arsort($keywords);
        
        return array_keys($keywords);
    }
    
    /**
     * Get multiple relevant images from search results
     * 
     * @param array $images Array of image data from Pexels
     * @param string|array $query Original search query or keywords
     * @param int $count Number of images to return (default: 3)
     * @return array Array of selected image data
     */
    public function select_relevant_images($images, $query, $count = 3) {
        // If no images, return empty array
        if (empty($images)) {
            $this->log_debug('No images to select from');
            return [];
        }
        
        // Convert query to keywords array if it's a string
        $keywords = is_array($query) ? $query : [$query];
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        $this->log_debug('Selecting ' . $count . ' relevant images using keywords: ' . print_r($keywords, true));
        
        // If we have fewer images than requested, return all of them
        if (count($images) <= $count) {
            $this->log_debug('Only ' . count($images) . ' images available, returning all');
            return $this->normalize_image_structures($images);
        }
        
        // Score each image based on alt text and photographer name relevance to keywords
        $scored_images = [];
        foreach ($images as $index => $image) {
            $score = 0;
            
            // Get text to match against (alt text, photographer name, etc.)
            $alt_text = isset($image['alt']) ? strtolower($image['alt']) : '';
            $photographer = isset($image['photographer']) ? strtolower($image['photographer']) : '';
            $combined_text = $alt_text . ' ' . $photographer;
            
            // Score based on keyword matches
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));
                if (!empty($keyword) && strpos($combined_text, $keyword) !== false) {
                    $score += 2; // Direct match
                }
                
                // Check for partial matches
                $keyword_parts = explode(' ', $keyword);
                foreach ($keyword_parts as $part) {
                    if (strlen($part) > 3 && strpos($combined_text, $part) !== false) {
                        $score += 1; // Partial match
                    }
                }
            }
            
            // Prefer landscape images for blog posts
            if (isset($image['width']) && isset($image['height']) && $image['width'] > $image['height']) {
                $score += 1;
            }
            
            // Store score
            $scored_images[$index] = $score;
        }
        
        // Sort by score (descending)
        arsort($scored_images);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        $this->log_debug('Image scores: ' . print_r($scored_images, true));
        
        // Get top N images
        $selected_images = [];
        $i = 0;
        foreach ($scored_images as $index => $score) {
            if ($i >= $count) break;
            $selected_images[] = $images[$index];
            $this->log_debug('Selected image with index ' . $index . ' and score ' . $score);
            $i++;
        }
        
        // Normalize image structures
        return $this->normalize_image_structures($selected_images);
    }
    
    /**
     * Normalize image structures to ensure consistent format
     * 
     * @param array $images Array of image data
     * @return array Normalized image data
     */
    private function normalize_image_structures($images) {
        $normalized = [];
        
        foreach ($images as $image) {
            // Debug the image structure
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            $this->log_debug('Normalizing image structure: ' . print_r($image, true));
            
            // Ensure the image has the expected structure
            if (!isset($image['src'])) {
                // If 'src' doesn't exist, check if the structure is different
                if (isset($image['photos'][0]['src'])) {
                    $normalized[] = $image['photos'][0];
                    continue;
                }
                
                // Create a compatible structure if needed
                if (isset($image['url'])) {
                    $image['src'] = array(
                        'original' => $image['url'],
                        'large' => $image['url'],
                        'medium' => $image['url'],
                        'small' => $image['url']
                    );
                }
            }
            
            $normalized[] = $image;
        }
        
        return $normalized;
    }
    
    /**
     * Get the most relevant image from search results (legacy support)
     * 
     * @param array $images Array of image data from Pexels
     * @param string|array $query Original search query or keywords
     * @return array Selected image data
     */
    public function select_most_relevant_image($images, $query) {
        $selected_images = $this->select_relevant_images($images, $query, 1);
        return !empty($selected_images) ? $selected_images[0] : [];
    }
}