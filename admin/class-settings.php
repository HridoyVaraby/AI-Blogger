<?php
namespace GroqBlogger\Admin;

defined('ABSPATH') || exit;

class Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_options_page(
            __('Groq Blogger Settings', 'groq-blogger'),
            __('Groq Blogger', 'groq-blogger'),
            'manage_options',
            'groq-blogger-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('groq_blogger_options', 'groq_blogger_api_key');
        register_setting('groq_blogger_options', 'groq_blogger_model');

        add_settings_section(
            'groq_blogger_main',
            __('API Configuration', 'groq-blogger'),
            null,
            'groq-blogger-settings'
        );

        add_settings_field(
            'groq_api_key',
            __('Groq API Key', 'groq-blogger'),
            array($this, 'render_api_key_field'),
            'groq-blogger-settings',
            'groq_blogger_main'
        );

        add_settings_field(
            'groq_model',
            __('LLM Model', 'groq-blogger'),
            array($this, 'render_model_field'),
            'groq-blogger-settings',
            'groq_blogger_main'
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Groq Blogger Settings', 'groq-blogger'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('groq_blogger_options');
                do_settings_sections('groq-blogger-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_api_key_field() {
        $api_key = get_option('groq_blogger_api_key');
        echo '<input type="password" name="groq_blogger_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Groq API key can be found in the ', 'groq-blogger') 
            . '<a href="https://console.groq.com/keys" target="_blank" rel="noopener noreferrer">'
            . __('Groq Cloud Console', 'groq-blogger') 
            . '</a></p>';
    }

    public function render_model_field() {
        $selected_model = get_option('groq_blogger_model', 'llama-3.3-70b-versatile');
        $models = array(
            'llama-3.3-70b-versatile' => 'Llama-3.3-70b-Versatile',
            'mixtral-8x7b-32768' => 'Mixtral-8x7b-32768',
            'llama2-70b-4096' => 'Llama2-70b-4096',
            'deepseek-r1-distill-llama-70b' => 'Deepseek-R1-Distill-Llama-70b (Experimental)', 
            'gemma2-9b-it' => 'Gemma2-9b-IT',
        );
        
        echo '<select name="groq_blogger_model" class="regular-text">';
        foreach ($models as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($selected_model, $value, false) . '>' 
                . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}
