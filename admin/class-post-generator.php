<?php
namespace AI_Blogger\Admin;

defined('ABSPATH') || exit;

class Post_Generator {
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
            error_log('API key or model not configured');
            $this->add_notice('error', __('API credentials not configured', 'ai-blogger'));
            wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
            exit;
        }

        $api_handler = new \AI_Blogger\API_Handler();
        $content = $api_handler->generate_content($title, $model, $api_key);

        if (is_wp_error($content)) {
            $this->add_notice('error', $content->get_error_message());
            wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
            exit;
        }

        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => wp_kses_post($content),
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_type' => 'post'
        ));

        if ($post_id) {
            // Add images from Pexels
            $pexels_key = get_option('ai_blogger_pexels_key');
            
            if (empty($pexels_key)) {
                error_log('Pexels API key is not configured');
                $this->add_notice('warning', __('Post created without images - Pexels API key not configured', 'ai-blogger'));
            } else {
                $pexels = new \AI_Blogger\Pexels_Handler($pexels_key);
                error_log('Attempting to search Pexels for images with title: ' . $title);
                $images = $pexels->search_images($title);
                
                if (is_wp_error($images)) {
                    error_log('Pexels API error: ' . $images->get_error_message());
                    $this->add_notice('warning', __('Post created without images - ' . $images->get_error_message(), 'ai-blogger'));
                } elseif (!empty($images)) {
                    error_log('Found ' . count($images) . ' images from Pexels');
                    $selected_image = $pexels->select_most_relevant_image($images, $title);
                    
                    // Verify the image URL structure
                    error_log('Checking image structure: ' . print_r($selected_image, true));
                    
                    // Handle different possible image URL structures
                    $image_url = null;
                    if (isset($selected_image['src']) && isset($selected_image['src']['large'])) {
                        $image_url = $selected_image['src']['large'];
                    } elseif (isset($selected_image['src']) && isset($selected_image['src']['original'])) {
                        $image_url = $selected_image['src']['original'];
                    } elseif (isset($selected_image['url'])) {
                        $image_url = $selected_image['url'];
                    }
                    
                    if ($image_url) {
                        error_log('Selected image URL: ' . $image_url);
                        
                        $attachment_id = $pexels->attach_image_to_post($image_url, $title);
                        
                        if (is_wp_error($attachment_id)) {
                            error_log('Image attachment error: ' . $attachment_id->get_error_message());
                            $this->add_notice('warning', __('Post created but image could not be attached', 'ai-blogger'));
                        } else {
                            // Set featured image
                            set_post_thumbnail($post_id, $attachment_id);
                            
                            // Add images to post content
                            $image_html = '<figure><img src="' . wp_get_attachment_url($attachment_id) . '" alt="' . esc_attr($title) . '"></figure>';
                            wp_update_post(array(
                                'ID' => $post_id,
                                'post_content' => $image_html . wp_kses_post($content)
                            ));
                            
                            error_log('Successfully added image to post content');
                        }
                    } else {
                        error_log('Invalid image structure returned from Pexels API');
                        error_log('Image data: ' . print_r($selected_image, true));
                        $this->add_notice('warning', __('Post created without images - Invalid image data', 'ai-blogger'));
                    }
                } elseif (empty($images)) {
                    error_log('No images found for query: ' . $title);
                    $this->add_notice('warning', __('Post created without images - No relevant images found', 'ai-blogger'));
                } else {
                    error_log('No valid image URL found in the Pexels API response');
                    $this->add_notice('warning', __('Post created without images - No valid image URL found', 'ai-blogger'));
                }
            }
        
            $this->add_notice('success', __('Post generated and saved as draft', 'ai-blogger'));
            wp_redirect(admin_url('post.php?post='.$post_id.'&action=edit'));
            exit;
        }

        $this->add_notice('error', __('Failed to save post', 'ai-blogger'));
        wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
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
}
