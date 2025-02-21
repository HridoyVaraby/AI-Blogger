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
                        <th scope="row"><label for="generation_mode"><?php esc_html_e('Generation Mode', 'ai-blogger'); ?></label></th>
                        <td>
                            <select name="generation_mode" id="generation_mode" class="regular-text">
                                <option value="title"><?php esc_html_e('Generate from Title', 'ai-blogger'); ?></option>
                                <option value="auto"><?php esc_html_e('Auto Generate Post', 'ai-blogger'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="title-input">
                        <th scope="row"><label for="post_title"><?php esc_html_e('Post Title', 'ai-blogger'); ?></label></th>
                        <td>
                            <input type="text" name="post_title" id="post_title" class="regular-text">
                            <p class="description"><?php esc_html_e('Enter the title for the blog post you want to generate', 'ai-blogger'); ?></p>
                        </td>
                    </tr>
                    <tr class="auto-input" style="display: none;">
                        <th scope="row"><label for="post_topics"><?php esc_html_e('Topics/Tags/Categories', 'ai-blogger'); ?></label></th>
                        <td>
                            <textarea name="post_topics" id="post_topics" class="large-text" rows="3"></textarea>
                            <p class="description"><?php esc_html_e('Enter topics, tags, categories, or niche ideas separated by commas. AI will generate SEO-friendly title and content based on these.', 'ai-blogger'); ?></p>
                        </td>
                    </tr>
                </table>
                <script>
                jQuery(document).ready(function($) {
                    $('#generation_mode').on('change', function() {
                        if ($(this).val() === 'auto') {
                            $('.title-input').hide();
                            $('.auto-input').show();
                            $('#post_title').prop('required', false);
                            $('#post_topics').prop('required', true);
                        } else {
                            $('.title-input').show();
                            $('.auto-input').hide();
                            $('#post_title').prop('required', true);
                            $('#post_topics').prop('required', false);
                        }
                    });
                });
                </script>
                
                <?php submit_button(__('Generate Post', 'ai-blogger')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_generation() {
        // Verify nonce and permissions
        if (!current_user_can('edit_posts') 
            || !isset($_POST['_wpnonce']) 
            || !wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'ai_generate_post')) {
            wp_die(esc_html__('Security verification failed', 'ai-blogger'));
        }

        // Validate and sanitize input
        $generation_mode = isset($_POST['generation_mode']) 
            ? sanitize_text_field(wp_unslash($_POST['generation_mode'])) 
            : 'title';
        $title = '';
        $topics = '';

        if ($generation_mode === 'title') {
            $title = isset($_POST['post_title']) 
                ? sanitize_text_field(wp_unslash($_POST['post_title'])) 
                : '';
            if (empty($title)) {
                $this->add_notice('error', __('Post title is required', 'ai-blogger'));
                wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
                exit;
            }
        } else {
            $topics = isset($_POST['post_topics']) 
                ? sanitize_text_field(wp_unslash($_POST['post_topics'])) 
                : '';
            if (empty($topics)) {
                $this->add_notice('error', __('Topics/Tags/Categories are required', 'ai-blogger'));
                wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
                exit;
            }
        }

        $api_key = get_option('ai_blogger_api_key');
        $model = get_option('ai_blogger_model');

        if (empty($api_key) || empty($model)) {
            $this->add_notice('error', __('API credentials not configured', 'ai-blogger'));
            wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
            exit;
        }

        $api_handler = new \AI_Blogger\API_Handler();
        if ($generation_mode === 'auto') {
            $result = $api_handler->generate_from_topics($topics, $model, $api_key);
            if (is_wp_error($result)) {
                $this->add_notice('error', $result->get_error_message());
                wp_redirect(admin_url('edit.php?page=ai-blogger-generate'));
                exit;
            }
            $title = $result['title'];
            $content = $result['content'];
        } else {
            $content = $api_handler->generate_content($title, $model, $api_key);
        }

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
