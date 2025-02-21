<?php
namespace AI_Blogger;

defined('ABSPATH') || exit;

class API_Handler {
    const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    
    public function generate_from_topics($topics, $model, $api_key) {
        if (empty($api_key) || empty($model)) {
            return new \WP_Error('missing_credentials', __('API credentials not configured', 'ai-blogger'));
        }

        // First, generate a SEO-friendly title based on the topics
        $title_response = wp_remote_post(self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are an SEO expert. Generate a compelling, SEO-optimized blog post title based on the given topics or niche. The title should be engaging, include relevant keywords, and be optimized for search engines while maintaining readability.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "Generate a SEO-friendly blog post title for topics: $topics"
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 100
            )),
            'timeout' => 30
        ));

        if (is_wp_error($title_response)) {
            return $title_response;
        }

        $title_status = wp_remote_retrieve_response_code($title_response);
        $title_body = json_decode(wp_remote_retrieve_body($title_response), true);

        if ($title_status !== 200) {
            return new \WP_Error('api_error', __('API Error: ', 'ai-blogger') . ($title_body['error']['message'] ?? __('Unknown error', 'ai-blogger')));
        }

        $generated_title = trim($title_body['choices'][0]['message']['content'] ?? '');

        // Now generate the content using the generated title and original topics
        $content_response = wp_remote_post(self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are an expert blog writer. Generate a comprehensive, SEO-optimized blog post in HTML format using proper heading tags (h2-h4), paragraphs, and semantic markup. Include relevant keywords naturally within the content. Focus on the provided topics and ensure the content aligns with the title.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "Write a blog post with title: $generated_title\nTopics to focus on: $topics\n\nInclude an introduction, 3-5 main sections with subheadings, and a conclusion. Ensure proper keyword usage and SEO optimization."
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 3000
            )),
            'timeout' => 30
        ));

        if (is_wp_error($content_response)) {
            return $content_response;
        }

        $content_status = wp_remote_retrieve_response_code($content_response);
        $content_body = json_decode(wp_remote_retrieve_body($content_response), true);

        if ($content_status !== 200) {
            return new \WP_Error('api_error', __('API Error: ', 'ai-blogger') . ($content_body['error']['message'] ?? __('Unknown error', 'ai-blogger')));
        }

        return array(
            'title' => $generated_title,
            'content' => $this->sanitize_content($content_body['choices'][0]['message']['content'] ?? '')
        );
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
            return new \WP_Error('api_error', __('API Error: ', 'ai-blogger') . ($body['error']['message'] ?? __('Unknown error', 'ai-blogger')));
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
