<?php
namespace AI_Blogger;

defined('ABSPATH') || exit;

class API_Handler {
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
    const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    const MODELS_API_ENDPOINT = 'https://api.groq.com/openai/v1/models';

    public function get_models($api_key) {
        if (empty($api_key)) {
            return []; // Return empty array if no API key
        }

        $response = wp_remote_get(self::MODELS_API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return []; // Return empty array on error
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $models = [];

        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $model) {
                if (isset($model['id'])) {
                    $models[] = $model['id'];
                }
            }
        }
        sort($models);
        return $models;
    }
    
    public function generate_content($title, $model, $api_key) {
        if (empty($api_key) || empty($model)) {
            return new \WP_Error('missing_credentials', __('API credentials not configured', 'ai-blogger'));
        }

        $response = wp_remote_post(self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are an expert blog writer. Generate a comprehensive, SEO-optimized blog post in HTML format using proper heading tags (h2-h4), paragraphs, and semantic markup. Include relevant keywords naturally within the content. After the blog post, provide a JSON object with 5-10 relevant keywords for image search in the format: {"keywords": ["keyword1", "keyword2", ...]}'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "Write an 1000 words blog post about: $title. Include an introduction, 3-5 main sections with subheadings, and a conclusion. After the blog post, provide a JSON object with 5-10 relevant keywords for image search."
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 3000
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            return new \WP_Error('api_error', __('API Error: ', 'ai-blogger') . ($body['error']['message'] ?? __('Unknown error', 'ai-blogger')));
        }
        
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        // Extract keywords from the content
        $keywords = $this->extract_keywords($content);
        
        // Return both content and keywords
        return array(
            'content' => $this->sanitize_content($content),
            'keywords' => $keywords
        );
    }

    private function sanitize_content($content) {
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['h2'] = $allowed_html['h3'] = $allowed_html['h4'] = array();
        $allowed_html['ul'] = $allowed_html['ol'] = $allowed_html['li'] = array();
        
        return wp_kses($content, $allowed_html);
    }
    
    /**
     * Extract keywords from the AI-generated content
     * 
     * @param string $content The AI-generated content
     * @return array Array of keywords
     */
    private function extract_keywords($content) {
        // Default keywords if extraction fails
        $default_keywords = [];
        
        // Try to find JSON object with keywords at the end of the content
        if (preg_match('/\{\s*"keywords"\s*:\s*\[(.*?)\]\s*\}/s', $content, $matches)) {
            // Found JSON format
            $keywords_json = '{"keywords":[' . $matches[1] . ']}';
            $keywords_data = json_decode($keywords_json, true);
            
            if (json_last_error() === JSON_ERROR_NONE && !empty($keywords_data['keywords'])) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                $this->log_debug('Successfully extracted keywords: ' . print_r($keywords_data['keywords'], true));
                return $keywords_data['keywords'];
            }
        }
        
        // Alternative approach: look for keywords list in different format
        if (preg_match('/keywords\s*:\s*\[(.*?)\]/is', $content, $matches)) {
            $keywords_text = $matches[1];
            $keywords_array = array_map('trim', explode(',', str_replace('"', '', $keywords_text)));
            
            if (!empty($keywords_array)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                $this->log_debug('Extracted keywords using alternative method: ' . print_r($keywords_array, true));
                return $keywords_array;
            }
        }
        
        $this->log_debug('Failed to extract keywords from content');
        return $default_keywords;
    }
}
