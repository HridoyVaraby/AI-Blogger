<?php
namespace AI_Blogger\Admin;

defined('ABSPATH') || exit;

/**
 * Post Generator class for AI Blogger plugin
 */

class Post_Generator {
    /**
     * Debug logging function that only logs in development environments
     * 
     * @param mixed $message The message or data to log
     * @param bool $is_array Whether the data is an array/object that needs to be converted
     * @return void
     */
    private function log_debug($message, $is_array = false) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            if ($is_array) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(print_r($message, true));
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log($message);
            }
        }
    }
    public function __construct() {
        add_action('admin_menu', array($this, 'add_generate_page'));
        add_action('admin_post_ai_generate_post', array($this, 'handle_generation'));
    }

    public function add_generate_page() {
        add_posts_page(
            __('Generate Blog Post', 'ai-blogger'),
            __('Generate with AI', 'ai-blogger'),
            'edit_posts',
            'ai-blogger-generate',
            array($this, 'render_generate_page')
        );
    }

    public function render_generate_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Generate Blog Post', 'ai-blogger'); ?></h1>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ai_generate_post">
                <?php wp_nonce_field('ai_generate_post'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="post_title"><?php esc_html_e('Post Title', 'ai-blogger'); ?></label></th>
                        <td>
                            <input type="text" name="post_title" id="post_title" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Enter the title for the blog post you want to generate', 'ai-blogger'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Generate Post', 'ai-blogger')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_generation() {
        // Verify nonce and permissions
        if (!current_user_can('edit_posts') 
            || !isset($_POST['_wpnonce']) 
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ai_generate_post')) {
            wp_die(esc_html__('Security verification failed', 'ai-blogger'));
        }

        // Validate and sanitize input
        $title = isset($_POST['post_title']) 
            ? sanitize_text_field(wp_unslash($_POST['post_title'])) 
            : '';
        $api_key = get_option('ai_blogger_api_key');
        $model = get_option('ai_blogger_model');

        if (empty($api_key) || empty($model)) {
            $this->log_debug('API key or model not configured');
            $this->add_notice('error', __('API credentials not configured', 'ai-blogger'));
            wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
            exit;
        }

        $api_handler = new \AI_Blogger\API_Handler();
        $result = $api_handler->generate_content($title, $model, $api_key);

        if (is_wp_error($result)) {
            $this->add_notice('error', $result->get_error_message());
            wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
            exit;
        }
        
        // Extract content and keywords from the result
        $content = $result['content'];
        $keywords = !empty($result['keywords']) ? $result['keywords'] : [$title];
        $this->log_debug('Extracted keywords for image search: ', false);
        $this->log_debug($keywords, true);

        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => wp_kses_post($content),
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_type' => 'post',
            'meta_input' => array(
                'ai_blogger_keywords' => $keywords
            )
        ));

        if ($post_id) {
            // Add keywords as WordPress tags
            if (!empty($keywords)) {
                $this->log_debug('Adding keywords as WordPress tags: ', false);
                $this->log_debug($keywords, true);
                wp_set_post_tags($post_id, $keywords, false);
            }
            
            // Add images from Pexels
            $pexels_key = get_option('ai_blogger_pexels_key');
            
            if (empty($pexels_key)) {
                $this->log_debug('Pexels API key is not configured');
                $this->add_notice('warning', __('Post created without images - Pexels API key not configured', 'ai-blogger'));
            } else {
                $pexels = new \AI_Blogger\Pexels_Handler($pexels_key);
                // Pass the entire keywords array to try multiple keywords if needed
                $this->log_debug('Attempting to search Pexels for images with keywords: ', false);
                $this->log_debug($keywords, true);
                $images = $pexels->search_images($keywords, 10); // Get more images to select from
                
                if (is_wp_error($images)) {
                    $this->log_debug('Pexels API error: ' . $images->get_error_message());
                    // translators: %s is the error message returned by the Pexels API
                    $this->add_notice('warning', sprintf(__('Post created without images - %s', 'ai-blogger'), $images->get_error_message()));
                } elseif (!empty($images)) {
                    $this->log_debug('Found ' . count($images) . ' images from Pexels');
                    $selected_images = $pexels->select_relevant_images($images, $keywords, 3); // Get 3 images
                    
                    if (empty($selected_images)) {
                        $this->log_debug('No valid images selected from Pexels API');
                        $this->add_notice('warning', __('Post created without images - No valid images selected', 'ai-blogger'));
                    } else {
                        $this->log_debug('Selected ' . count($selected_images) . ' images from Pexels');
                        
                        // Get the original content
                        $post_content = wp_kses_post($content);
                        $attachment_ids = [];
                        
                        // Process each image
                        foreach ($selected_images as $index => $image) {
                            // Handle different possible image URL structures
                            $image_url = null;
                            if (isset($image['src']) && isset($image['src']['large'])) {
                                $image_url = $image['src']['large'];
                            } elseif (isset($image['src']) && isset($image['src']['original'])) {
                                $image_url = $image['src']['original'];
                            } elseif (isset($image['url'])) {
                                $image_url = $image['url'];
                            }
                            
                            if ($image_url) {
                                $this->log_debug('Processing image ' . ($index + 1) . ' URL: ' . $image_url);
                                
                                // Create a unique title for each image
                                $image_title = $title . ' - Image ' . ($index + 1);
                                $attachment_id = $pexels->attach_image_to_post($image_url, $image_title);
                                
                                if (is_wp_error($attachment_id)) {
                                    $this->log_debug('Image attachment error: ' . $attachment_id->get_error_message());
                                } else {
                                    $attachment_ids[] = $attachment_id;
                                    $this->log_debug('Successfully attached image ' . ($index + 1) . ' with ID: ' . $attachment_id);
                                }
                            }
                        }
                        
                        if (empty($attachment_ids)) {
                            $this->log_debug('Failed to attach any images');
                            $this->add_notice('warning', __('Post created but images could not be attached', 'ai-blogger'));
                        } else {
                            // Set the first image as featured image
                            if (!empty($attachment_ids[0])) {
                                set_post_thumbnail($post_id, $attachment_ids[0]);
                                $this->log_debug('Set featured image with ID: ' . $attachment_ids[0]);
                            }
                            
                            // Insert images into post content at specific positions
                            if (count($attachment_ids) >= 3) {
                                // Find positions to insert images (before 2nd and 4th h2 tags)
                                $h2_positions = $this->find_h2_tag_positions($post_content);
                                
                                if (count($h2_positions) >= 4) {
                                    // Insert image before 4th h2 tag first (to maintain positions)
                                    $image_html = '<figure><img src="' . wp_get_attachment_url($attachment_ids[2]) . '" alt="' . esc_attr($title) . ' image 3"></figure>';
                                    $post_content = substr_replace($post_content, $image_html, $h2_positions[3], 0);
                                    
                                    // Insert image before 2nd h2 tag
                                    $image_html = '<figure><img src="' . wp_get_attachment_url($attachment_ids[1]) . '" alt="' . esc_attr($title) . ' image 2"></figure>';
                                    $post_content = substr_replace($post_content, $image_html, $h2_positions[1], 0);
                                    
                                    $this->log_debug('Successfully added images at specific positions in post content');
                                } else {
                                    // If we don't have enough h2 tags, add images at beginning and end
                                    $image1_html = '<figure><img src="' . wp_get_attachment_url($attachment_ids[1]) . '" alt="' . esc_attr($title) . ' image 2"></figure>';
                                    $image2_html = '<figure><img src="' . wp_get_attachment_url($attachment_ids[2]) . '" alt="' . esc_attr($title) . ' image 3"></figure>';
                                    
                                    // Add one image after intro paragraph and one before conclusion
                                    $paragraphs = explode('</p>', $post_content);
                                    if (count($paragraphs) >= 3) {
                                        $paragraphs[0] .= '</p>' . $image1_html;
                                        $paragraphs[count($paragraphs) - 2] .= '</p>' . $image2_html;
                                        $post_content = implode('</p>', $paragraphs);
                                    } else {
                                        // If not enough paragraphs, add at beginning and end
                                        $post_content = $image1_html . $post_content . $image2_html;
                                    }
                                    
                                    $this->log_debug('Added images at beginning and end of content due to insufficient h2 tags');
                                }
                            } elseif (count($attachment_ids) == 2) {
                                // If we only have 2 images, use one for featured and one in content
                                $image_html = '<figure><img src="' . wp_get_attachment_url($attachment_ids[1]) . '" alt="' . esc_attr($title) . ' image 2"></figure>';
                                
                                // Try to insert after first paragraph
                                $pos = strpos($post_content, '</p>');
                                if ($pos !== false) {
                                    $post_content = substr_replace($post_content, '</p>' . $image_html, $pos, 4);
                                } else {
                                    // If no paragraphs, add at beginning
                                    $post_content = $image_html . $post_content;
                                }
                                
                                $this->log_debug('Added one image to post content');
                            }
                            
                            // Update the post with the new content
                            wp_update_post(array(
                                'ID' => $post_id,
                                'post_content' => $post_content
                            ));
                            
                            // translators: %d is the number of images attached to the post
                            $this->add_notice('success', sprintf(__('Post created with %d images', 'ai-blogger'), count($attachment_ids)));
                        }
                    }
                } elseif (empty($images)) {
                    $this->log_debug('No images found for query: ' . $title);
                    $this->add_notice('warning', __('Post created without images - No relevant images found', 'ai-blogger'));
                } else {
                    $this->log_debug('No valid image URL found in the Pexels API response');
                    $this->add_notice('warning', __('Post created without images - No valid image URL found', 'ai-blogger'));
                }
            }
        }
        
        $this->add_notice('success', __('Post generated and saved as draft', 'ai-blogger'));
        wp_redirect(admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }

    private function add_notice($type, $message) {
        add_settings_error(
            'ai_blogger_notices',
            'ai_notice',
            $message,
            $type
        );
    }
    
    /**
     * Find positions of h2 tags in content
     * 
     * @param string $content The post content
     * @return array Array of positions where h2 tags start
     */
    private function find_h2_tag_positions($content) {
        $positions = array();
        $offset = 0;
        
        // Find all h2 opening tags
        while (($pos = stripos($content, '<h2', $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 3;
        }
        
        return $positions;
    }
}
