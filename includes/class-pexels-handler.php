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
     * @param string|array $query Search query or array of keywords
     * @param int $per_page Number of results per page
     * @return array|WP_Error Array of image data or WP_Error on failure
     */
    public function search_images($query, $per_page = 5) {
        if (empty($this->api_key)) {
            error_log('Pexels API key is empty or not configured');
            return new \WP_Error('missing_api_key', __('Pexels API key is not configured', 'ai-blogger'));
        }
        
        // Handle array of keywords
        if (is_array($query)) {
            error_log('Received array of keywords for image search: ' . print_r($query, true));
            
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
                    error_log('First keyword returned no results, trying combined keywords: ' . $combined_query);
                    
                    $result = $this->perform_search($combined_query, $per_page);
                    if (!is_wp_error($result) && !empty($result)) {
                        return $result;
                    }
                }
                
                // Try other keywords if still no results
                for ($i = 1; $i < min(count($query), 3); $i++) {
                    error_log('Trying alternative keyword: ' . $query[$i]);
                    $result = $this->perform_search($query[$i], $per_page);
                    
                    if (!is_wp_error($result) && !empty($result)) {
                        return $result;
                    }
                }
            }
            
            // If we've tried keywords and got nothing, use the original query as a string
            $query = implode(' ', array_slice($query, 0, 3));
            error_log('No results with individual keywords, using combined query: ' . $query);
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
        error_log('Pexels API request URL: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => $this->api_key
            ),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        error_log('Sending request to Pexels API with query: ' . $query);

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
     * Get the most relevant image from search results
     * 
     * @param array $images Array of image data from Pexels
     * @param string|array $query Original search query or keywords
     * @return array Selected image data
     */
    public function select_most_relevant_image($images, $query) {
        // If no images, return empty
        if (empty($images)) {
            error_log('No images to select from');
            return [];
        }
        
        // Convert query to keywords array if it's a string
        $keywords = is_array($query) ? $query : [$query];
        error_log('Selecting most relevant image using keywords: ' . print_r($keywords, true));
        
        // If we only have one image, return it
        if (count($images) === 1) {
            error_log('Only one image available, selecting it');
            $selected_image = $images[0];
        } else {
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
            error_log('Image scores: ' . print_r($scored_images, true));
            
            // Get highest scored image
            $best_index = key($scored_images);
            $selected_image = $images[$best_index];
            error_log('Selected best image with index ' . $best_index . ' and score ' . $scored_images[$best_index]);
        }
        
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