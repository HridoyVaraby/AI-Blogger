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
            'content' => $this->sanitize_content($content, $model),
            'keywords' => $keywords
        );
    }

    private function sanitize_content($content, $model = '') {
        // Remove thinking part from deepseek r1 model output
        // The thinking part is typically a reflection followed by &nbsp; and then HTML content
        if (strpos($model, 'deepseek-r1') !== false) {
            $this->log_debug('Processing deepseek-r1 model output, original length: ' . strlen($content));
            
            // First approach: Try to find the first HTML tag and remove everything before it
            if (preg_match('/<(?:h[1-6]|p|div|section|article|ul|ol|li|blockquote|table)[^>]*>/is', $content, $html_matches, PREG_OFFSET_CAPTURE)) {
                $html_start_pos = $html_matches[0][1];
                if ($html_start_pos > 0) { // Only trim if there's content before the first HTML tag
                    $content = substr($content, $html_start_pos);
                    $this->log_debug('Removed thinking part by finding first semantic HTML tag');
                }
            }
            // If no semantic HTML tags found, try with any HTML tag
            elseif (preg_match('/<[a-z][^>]*>/is', $content, $html_matches, PREG_OFFSET_CAPTURE)) {
                $html_start_pos = $html_matches[0][1];
                if ($html_start_pos > 0) { // Only trim if there's content before the first HTML tag
                    $content = substr($content, $html_start_pos);
                    $this->log_debug('Removed thinking part by finding first HTML tag');
                }
            }
            // Pattern 0: Match the exact format from the example (Alright, so the user has asked me to write...)
            elseif (preg_match('/^\s*Alright,\s+so\s+the\s+user\s+has\s+asked\s+me\s+to\s+write.*?&nbsp;/is', $content)) {
                // Find the position of the first HTML tag after the thinking part
                if (preg_match('/&nbsp;\s*(\<[a-z][^>]*>)/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $html_start_pos = $matches[1][1];
                    if ($html_start_pos !== false) {
                        // Remove everything before the first HTML tag
                        $content = substr($content, $html_start_pos);
                        $this->log_debug('Removed thinking part using pattern 0 (exact format match)');
                    }
                } else {
                    // Fallback to removing everything before and including &nbsp;
                    $nbsp_pos = strpos($content, '&nbsp;');
                    if ($nbsp_pos !== false) {
                        $content = substr($content, $nbsp_pos + 6);
                        $this->log_debug('Removed thinking part using pattern 0 fallback');
                    }
                }
            }
            // Pattern 0.1: Direct match for the exact thinking part format in the example
            elseif (preg_match('/^\s*Alright, so the user has asked me to write a comprehensive, SEO-optimized blog post.*?Overall, the goal is to create a well-structured, SEO-friendly guide.*?&nbsp;/is', $content)) {
                $this->log_debug('Found exact thinking part format from example');
                // Try to find the HTML content after the thinking part
                if (preg_match('/<[a-z][^>]*>/is', $content, $html_matches, PREG_OFFSET_CAPTURE)) {
                    $html_start_pos = $html_matches[0][1];
                    if ($html_start_pos !== false) {
                        // Remove everything before the first HTML tag
                        $content = substr($content, $html_start_pos);
                        $this->log_debug('Removed exact thinking part format using HTML tag detection');
                    }
                } else {
                    // If no HTML tag found, remove everything before the first occurrence of a common HTML tag
                    foreach (array('<h1', '<h2', '<p', '<div', '<section', '<article') as $tag) {
                        $tag_pos = stripos($content, $tag);
                        if ($tag_pos !== false) {
                            $content = substr($content, $tag_pos);
                            $this->log_debug('Removed exact thinking part format using tag search: ' . $tag);
                            break;
                        }
                    }
                }
            }
            // Pattern 1: Check for thinking part that starts with { and ends with ----- followed by }
            elseif (preg_match('/^\s*\{\s*(?:Alright|the thinking part).*?-----\}\s*/is', $content, $matches)) {
                // Remove the matched thinking part
                $content = preg_replace('/^\s*\{\s*(?:Alright|the thinking part).*?-----\}\s*/is', '', $content, 1);
                $this->log_debug('Removed thinking part using pattern 1 (specific format with dashes)');
            }
            // Pattern 2: Check for "the thinking part ends here" marker
            elseif (preg_match('/.*?the thinking part ends here\.?\s*/is', $content, $matches)) {
                $content = preg_replace('/.*?the thinking part ends here\.?\s*/is', '', $content, 1);
                $this->log_debug('Removed thinking part using pattern 2 (explicit end marker)');
            }
            // Pattern 3: Look for &nbsp; followed by HTML tag
            elseif (preg_match('/\&nbsp;\s*(<[a-z][^>]*>)/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $html_start_pos = $matches[1][1];
                if ($html_start_pos !== false) {
                    // Remove everything before the first HTML tag after &nbsp;
                    $content = substr($content, $html_start_pos);
                    $this->log_debug('Removed thinking part using pattern 3 (HTML after &nbsp;)');
                }
            }
            // Pattern 4: Look for any thinking part enclosed in curly braces
            elseif (preg_match('/^\s*\{[^\}]+\}\s*/s', $content, $matches)) {
                $content = preg_replace('/^\s*\{[^\}]+\}\s*/s', '', $content, 1);
                $this->log_debug('Removed thinking part using pattern 4 (content in curly braces)');
            }
            // Pattern 5: Look for "Alright" followed by thinking content and &nbsp;
            elseif (preg_match('/^\s*Alright.*?&nbsp;/is', $content, $matches)) {
                $nbsp_pos = strpos($content, '&nbsp;');
                if ($nbsp_pos !== false) {
                    // Remove everything before and including the &nbsp; tag
                    $content = substr($content, $nbsp_pos + 6);
                    $this->log_debug('Removed thinking part using pattern 5 (Alright followed by &nbsp;)');
                }
            }
            // Pattern 6: Match thinking part followed by HTML code blocks
            elseif (preg_match('/^\s*Alright.*?```html\s*&nbsp;/is', $content, $matches)) {
                if (preg_match('/```html\s*&nbsp;\s*/is', $content, $code_matches, PREG_OFFSET_CAPTURE)) {
                    $html_start = $code_matches[0][1] + strlen($code_matches[0][0]);
                    $content = substr($content, $html_start);
                    $this->log_debug('Removed thinking part using pattern 6 (thinking followed by HTML code block)');
                }
            }
            // Pattern 7: Direct match for the exact thinking part in the example
            elseif (preg_match('/^\s*Alright, so the user has asked me to write a comprehensive, SEO-optimized blog post/is', $content)) {
                // Try to find the HTML content after the thinking part
                if (preg_match('/```html\s*\n\s*&nbsp;/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $html_start_pos = $matches[0][1] + strlen($matches[0][0]);
                    if ($html_start_pos !== false) {
                        // Remove everything before and including the HTML code block marker and &nbsp;
                        $content = substr($content, $html_start_pos);
                        $this->log_debug('Removed thinking part using pattern 7 (exact example format)');
                    }
                }
            }
            // Pattern 8: Look for any text that starts with "I'll" or "I will" followed by "write", "create", or "generate"
            elseif (preg_match('/^\s*(?:I\'ll|I will)\s+(?:write|create|generate).*?(?:<[a-z][^>]*>)/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
                // Find the position of the first HTML tag
                if (preg_match('/<[a-z][^>]*>/is', $content, $html_matches, PREG_OFFSET_CAPTURE)) {
                    $html_start_pos = $html_matches[0][1];
                    if ($html_start_pos !== false) {
                        // Remove everything before the first HTML tag
                        $content = substr($content, $html_start_pos);
                        $this->log_debug('Removed thinking part using pattern 8 (I\'ll/I will write/create/generate)');
                    }
                }
            }
            // Fallback: If &nbsp; exists, remove everything before it
            elseif (strpos($content, '&nbsp;') !== false) {
                $nbsp_pos = strpos($content, '&nbsp;');
                $content = substr($content, $nbsp_pos + 6);
                $this->log_debug('Removed thinking part using fallback method (simple &nbsp;)');
            }
            // Last resort: If content starts with plain text and not HTML, try to find the first HTML tag
            elseif (!preg_match('/^\s*<[a-z][^>]*>/is', $content) && preg_match('/<[a-z][^>]*>/is', $content, $html_matches, PREG_OFFSET_CAPTURE)) {
                $html_start_pos = $html_matches[0][1];
                if ($html_start_pos > 100) { // Only apply if there's substantial text before the first HTML tag
                    $content = substr($content, $html_start_pos);
                    $this->log_debug('Removed thinking part using last resort method (first HTML tag)');
                }
            }
            
            $this->log_debug('After processing, content length: ' . strlen($content));
        }
        
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
