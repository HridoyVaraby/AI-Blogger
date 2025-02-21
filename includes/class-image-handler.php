<?php
namespace AI_Blogger;

defined('ABSPATH') || exit;

class Image_Handler {
    const UNSPLASH_API_ENDPOINT = 'https://api.unsplash.com/';
    
    private $api_key;
    
    public function __construct() {
        $this->api_key = get_option('ai_blogger_unsplash_api_key');
    }
    
    public function get_featured_image($title, $content) {
        if (empty($this->api_key)) {
            return new \WP_Error('missing_api_key', __('Unsplash API key not configured', 'ai-blogger'));
        }
        
        // Extract keywords from title and content
        $keywords = $this->extract_keywords($title, $content);
        
        // Search for relevant image
        $image_url = $this->search_image($keywords);
        if (is_wp_error($image_url)) {
            return $image_url;
        }
        
        // Download and attach image to media library
        return $this->attach_image($image_url, $title);
    }
    
    private function extract_keywords($title, $content) {
        // Remove HTML tags and extract text
        $text = strip_tags($title . ' ' . $content);
        
        // Remove common words and get most relevant terms
        $stop_words = array('the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at');
        $words = str_word_count(strtolower($text), 1);
        $words = array_diff($words, $stop_words);
        $word_count = array_count_values($words);
        arsort($word_count);
        
        // Get top 3 keywords
        return implode(' ', array_slice(array_keys($word_count), 0, 3));
    }
    
    private function search_image($query) {
        $response = wp_remote_get(self::UNSPLASH_API_ENDPOINT . 'search/photos', array(
            'headers' => array(
                'Authorization' => 'Client-ID ' . $this->api_key
            ),
            'body' => array(
                'query' => $query,
                'orientation' => 'landscape',
                'per_page' => 1
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['results'])) {
            return new \WP_Error('no_image', __('No suitable image found', 'ai-blogger'));
        }
        
        return $body['results'][0]['urls']['regular'];
    }
    
    private function attach_image($image_url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Get file info
        $file_array = array();
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        // Get file extension from URL
        $url_path = parse_url($image_url, PHP_URL_PATH);
        $extension = pathinfo($url_path, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'jpg';
        }
        
        // Set file name and temp name
        $file_array['name'] = sanitize_title($title) . '.' . $extension;
        $file_array['tmp_name'] = $tmp;
        $file_array['type'] = 'image/' . $extension;
        
        // Insert attachment
        $attachment_id = media_handle_sideload($file_array, 0);
        
        // Clean up
        @unlink($tmp);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        return $attachment_id;
    }
}