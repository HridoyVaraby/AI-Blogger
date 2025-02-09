<?php
/**
 * Plugin Name: AI Blogger
 * Description: Generate SEO-friendly blog posts using Groq API
 * Version: 1.0.3
 * Author: <a href="https://github.com/HridoyVaraby">Hridoy Varaby</a> | <a href="https://varabit.com">Varabit</a> | <a href="https://github.com/HridoyVaraby/AI-Blogger">View Details</a>
 * License: GPL-2.0+
 * Text Domain: ai-blogger
 */

// Security check
defined('ABSPATH') || exit;

// Define plugin constants
define('AI_BLOGGER_VERSION', '1.0.3');
define('AI_BLOGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_BLOGGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Register activation hook
register_activation_hook(__FILE__, 'ai_blogger_activate');

function ai_blogger_activate() {
    if (!current_user_can('activate_plugins')) return;
    
    // Initial plugin setup (if needed)
    update_option('ai_blogger_version', AI_BLOGGER_VERSION);
}

// Initialize plugin components
add_action('plugins_loaded', 'ai_blogger_init');

function ai_blogger_init() {
    // Load admin components
    if (is_admin()) {
        require_once AI_BLOGGER_PLUGIN_DIR . 'admin/class-settings.php';
        require_once AI_BLOGGER_PLUGIN_DIR . 'admin/class-post-generator.php';
        
        // Initialize admin classes
        new AI_Blogger\Admin\Settings();
        new AI_Blogger\Admin\Post_Generator();
    }

    // Load API handler
    require_once AI_BLOGGER_PLUGIN_DIR . 'includes/class-api-handler.php';
    new AI_Blogger\API_Handler();
}

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ai_blogger_add_settings_link');

function ai_blogger_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ai-blogger-settings') . '">' 
        . __('Settings', 'ai-blogger') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Enqueue admin styles
add_action('admin_enqueue_scripts', 'ai_blogger_admin_styles');

function ai_blogger_admin_styles($hook) {
    if (strpos($hook, 'ai-blogger') === false) return;
    
    wp_enqueue_style(
        'ai-blogger-admin',
        AI_BLOGGER_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        AI_BLOGGER_VERSION
    );
}
