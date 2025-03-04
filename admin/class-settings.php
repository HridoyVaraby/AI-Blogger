<?php
namespace AI_Blogger\Admin;

defined('ABSPATH') || exit;

class Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function sanitize_api_key($input) {
        return sanitize_text_field(trim($input));
    }

    public function sanitize_options($options) {
        if (!isset($options['ai_blogger_api_key']) || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ai_blogger_options-options')) {
            return $options;
        }
        
        $clean_options = array();
        $clean_options['ai_blogger_api_key'] = $this->sanitize_api_key($options['ai_blogger_api_key']);
        $clean_options['ai_blogger_model'] = $this->sanitize_model($options['ai_blogger_model']);
        
        return $clean_options;
    }

    public function sanitize_model($input) {
        $valid_models = [
            'llama-3.3-70b-versatile',
            'mixtral-8x7b-32768',
            'deepseek-r1-distill-llama-70b',
            'gemma2-9b-it'
        ];
        return in_array($input, $valid_models) ? $input : 'llama-3.3-70b-versatile';
    }

    public function add_settings_page() {
        add_options_page(
            __('AI Blogger Settings', 'ai-blogger'),
            __('AI Blogger', 'ai-blogger'),
            'manage_options',
            'ai-blogger-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'ai_blogger_options',
            'ai_blogger_api_key',
            array(
                'sanitize_callback' => array($this, 'sanitize_api_key')
            )
        );

        register_setting(
            'ai_blogger_options', 
            'ai_blogger_model',
            array(
                'sanitize_callback' => array($this, 'sanitize_model')
            )
        );

        add_settings_section(
            'ai_blogger_main',
            __('API Configuration', 'ai-blogger'),
            null,
            'ai-blogger-settings'
        );

        add_settings_field(
            'ai_api_key',
            __('Groq API Key', 'ai-blogger'),
            array($this, 'render_api_key_field'),
            'ai-blogger-settings',
            'ai_blogger_main'
        );

        add_settings_field(
            'ai_model',
            __('AI Model', 'ai-blogger'),
            array($this, 'render_model_field'),
            'ai-blogger-settings',
            'ai_blogger_main'
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Blogger Settings', 'ai-blogger'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('ai_blogger_options');
                do_settings_sections('ai-blogger-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_api_key_field() {
        $api_key = get_option('ai_blogger_api_key');
        echo '<input type="password" name="ai_blogger_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Your Groq API key can be found in your ', 'ai-blogger') 
            . ' <a href="https://console.groq.com/keys" target="_blank" rel="noopener noreferrer">'
            . esc_html__('Groq Cloud Console', 'ai-blogger') 
            . '</a></p>';
    }

    public function render_model_field() {
        $selected_model = get_option('ai_blogger_model', 'llama-3.3-70b-versatile');
        $models = array(
            'llama-3.3-70b-versatile' => 'Llama-3.3-70b-Versatile',
            'mixtral-8x7b-32768' => 'Mixtral-8x7b-32768',
            'deepseek-r1-distill-llama-70b' => 'Deepseek-R1-Distill-Llama-70b (Experimental)', 
            'gemma2-9b-it' => 'Gemma2-9b-IT',
        );
        
        echo '<select name="ai_blogger_model" class="regular-text">';
        foreach ($models as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($selected_model, $value, false) . '>' 
                . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}
