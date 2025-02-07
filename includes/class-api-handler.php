<?php
namespace GroqBlogger;

defined('ABSPATH') || exit;

class API_Handler {
    const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    
    public function generate_content($title, $model, $api_key) {
        if (empty($api_key) || empty($model)) {
            return new \WP_Error('missing_credentials', __('API credentials not configured', 'groq-blogger'));
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
                        'content' => 'You are an expert blog writer. Generate a comprehensive, SEO-optimized blog post in HTML format using proper heading tags (h2-h4), paragraphs, and semantic markup. Include relevant keywords naturally within the content.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "Write a blog post about: $title. Include an introduction, 3-5 main sections with subheadings, and a conclusion."
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
            return new \WP_Error('api_error', __('API Error: ', 'groq-blogger') . ($body['error']['message'] ?? __('Unknown error', 'groq-blogger')));
        }

        return $this->sanitize_content($body['choices'][0]['message']['content'] ?? '');
    }

    private function sanitize_content($content) {
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['h2'] = $allowed_html['h3'] = $allowed_html['h4'] = array();
        $allowed_html['ul'] = $allowed_html['ol'] = $allowed_html['li'] = array();
        
        return wp_kses($content, $allowed_html);
    }
}
