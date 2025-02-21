<?php
namespace AI_Blogger;

defined('ABSPATH') || exit;

class API_Handler {
    const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    
    public function generate_from_topics($topics, $model, $api_key) {
        if (empty($api_key) || empty($model)) {
            return new \WP_Error('missing_credentials', __('API credentials not configured', 'ai-blogger'));
        }

        // Generate a single SEO-friendly title and content based on the topics
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
                        'content' => 'You are an expert blog writer and SEO specialist. Generate a single, compelling SEO-optimized blog post title and comprehensive content based on the given topics. The content should be in HTML format using proper heading tags (h2-h4), paragraphs, and semantic markup.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "Generate a single SEO-friendly blog post title and content for topics: $topics\n\nProvide the output in the following format:\nTITLE: [Your generated title]\n\nCONTENT:\n[Your generated content with proper HTML formatting, including introduction, 3-5 main sections with subheadings, and a conclusion]"
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

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            return new \WP_Error('api_error', __('API Error: ', 'ai-blogger') . ($body['error']['message'] ?? __('Unknown error', 'ai-blogger')));
        }

        $generated_content = $body['choices'][0]['message']['content'] ?? '';
        
        // Extract title and content from the response
        preg_match('/TITLE:\s*(.+?)\s*CONTENT:/s', $generated_content, $title_matches);
        preg_match('/CONTENT:\s*(.+)$/s', $generated_content, $content_matches);

        $title = trim($title_matches[1] ?? '');
        $content = trim($content_matches[1] ?? '');

        return array(
            'title' => $title,
            'content' => $this->sanitize_content($content)
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
                        'content' => "Write a blog post with title: $title\n\nInclude an introduction, 3-5 main sections with subheadings, and a conclusion. Ensure proper keyword usage and SEO optimization."
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

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            return new \WP_Error('api_error', __('API Error: ', 'ai-blogger') . ($body['error']['message'] ?? __('Unknown error', 'ai-blogger')));
        }

        return $this->sanitize_content($body['choices'][0]['message']['content'] ?? '');
    }

    private function sanitize_content($content) {
        return wp_kses_post($content);
    }
}
