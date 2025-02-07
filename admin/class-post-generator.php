<?php
namespace GroqBlogger\Admin;

defined('ABSPATH') || exit;

class Post_Generator {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_generate_page'));
        add_action('admin_post_groq_generate_post', array($this, 'handle_generation'));
    }

    public function add_generate_page() {
        add_posts_page(
            __('Generate Blog Post', 'groq-blogger'),
            __('Generate with Groq', 'groq-blogger'),
            'edit_posts',
            'groq-blogger-generate',
            array($this, 'render_generate_page')
        );
    }

    public function render_generate_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Generate Blog Post', 'groq-blogger'); ?></h1>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="groq_generate_post">
                <?php wp_nonce_field('groq_generate_post'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="post_title"><?php esc_html_e('Post Title', 'groq-blogger'); ?></label></th>
                        <td>
                            <input type="text" name="post_title" id="post_title" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Enter the title for the blog post you want to generate', 'groq-blogger'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Generate Post', 'groq-blogger')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_generation() {
        if (!current_user_can('edit_posts') || !wp_verify_nonce($_POST['_wpnonce'], 'groq_generate_post')) {
            wp_die(__('Authorization failed', 'groq-blogger'));
        }

        $title = sanitize_text_field($_POST['post_title']);
        $api_key = get_option('groq_blogger_api_key');
        $model = get_option('groq_blogger_model');

        if (empty($api_key) || empty($model)) {
            $this->add_notice('error', __('API credentials not configured', 'groq-blogger'));
            wp_redirect(admin_url('edit.php?page=groq-blogger-generate'));
            exit;
        }

        $api_handler = new \GroqBlogger\API_Handler();
        $content = $api_handler->generate_content($title, $model, $api_key);

        if (is_wp_error($content)) {
            $this->add_notice('error', $content->get_error_message());
            wp_redirect(admin_url('edit.php?page=groq-blogger-generate'));
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
            $this->add_notice('success', __('Post generated and saved as draft', 'groq-blogger'));
            wp_redirect(admin_url('post.php?post='.$post_id.'&action=edit'));
            exit;
        }

        $this->add_notice('error', __('Failed to save post', 'groq-blogger'));
        wp_redirect(admin_url('edit.php?page=groq-blogger-generate'));
        exit;
    }

    private function add_notice($type, $message) {
        add_settings_error(
            'groq_blogger_notices',
            'groq_notice',
            $message,
            $type
        );
    }
}
