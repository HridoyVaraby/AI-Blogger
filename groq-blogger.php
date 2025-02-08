<?php
/**
 * Plugin Name: Groq Blogger
 * Description: Generate SEO-friendly blog posts using Groq API
 * Version: 1.0.2
 * Author: <a href="https://github.com/HridoyVaraby">Hridoy Varaby</a> | <a href="https://varabit.com">Varabit</a> | <a href="https://github.com/HridoyVaraby/Groq-Blogger">View Details</a>
 * License: GPL-2.0+
 * Text Domain: groq-blogger
 */

// Security check
defined('ABSPATH') || exit;

// Define plugin constants
define('GROQ_BLOGGER_VERSION', '1.0.2');
define('GROQ_BLOGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GROQ_BLOGGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Register activation hook
register_activation_hook(__FILE__, 'groq_blogger_activate');

function groq_blogger_activate() {
    if (!current_user_can('activate_plugins')) return;
    
    // Initial plugin setup (if needed)
    update_option('groq_blogger_version', GROQ_BLOGGER_VERSION);
}

// Initialize plugin components
add_action('plugins_loaded', 'groq_blogger_init');

function groq_blogger_init() {
    // Load admin components
    if (is_admin()) {
        require_once GROQ_BLOGGER_PLUGIN_DIR . 'admin/class-settings.php';
        require_once GROQ_BLOGGER_PLUGIN_DIR . 'admin/class-post-generator.php';
        
        // Initialize admin classes
        new GroqBlogger\Admin\Settings();
        new GroqBlogger\Admin\Post_Generator();
    }

    // Load API handler
    require_once GROQ_BLOGGER_PLUGIN_DIR . 'includes/class-api-handler.php';
    new GroqBlogger\API_Handler();
}

// Enqueue admin styles
add_action('admin_enqueue_scripts', 'groq_blogger_admin_styles');

function groq_blogger_admin_styles($hook) {
    if (strpos($hook, 'groq-blogger') === false) return;
    
    wp_enqueue_style(
        'groq-blogger-admin',
        GROQ_BLOGGER_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        GROQ_BLOGGER_VERSION
    );
}
