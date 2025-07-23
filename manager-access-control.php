<?php
/**
 * Plugin Name: Manager Access Control
 * Description: Control manager access with safe role import functionality
 * Version: 1.3
 * Author: Darko
 * Author URI: https://doodleapplications.com/
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('MAC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAC_VERSION', '1.3');

// Load required class files
require_once MAC_PLUGIN_PATH . 'includes/class-settings.php';
require_once MAC_PLUGIN_PATH . 'includes/class-capabilities.php';
require_once MAC_PLUGIN_PATH . 'includes/class-editor-ui.php';
require_once MAC_PLUGIN_PATH . 'includes/class-plugin-protection.php';
require_once MAC_PLUGIN_PATH . 'includes/class-reusable-blocks.php';
require_once MAC_PLUGIN_PATH . 'includes/class-role-processor.php';

/**
 * Check administrator status
 * @return bool True if user is administrator
 */
function mac_is_administrator() {
    $user = wp_get_current_user();
    return in_array('administrator', $user->roles);
}

/**
 * Check if user should have restrictions applied
 * @return bool True for restricted users
 */
function mac_is_restricted_user() {
    $user = wp_get_current_user();
    $settings = MAC_Settings::get_instance();
    $target_roles = $settings->get_setting('target_roles', []);
    return array_intersect($user->roles, $target_roles);
}

// Initialize plugin components
add_action('plugins_loaded', function() {
    $settings = MAC_Settings::get_instance();
    if (mac_is_restricted_user()) {
        MAC_Capabilities::init($settings);
        MAC_Editor_UI::init($settings);
        MAC_Plugin_Protection::init($settings);
        MAC_Reusable_Blocks::init($settings);
    }
});

// Plugin activation setup
register_activation_hook(__FILE__, function() {
    if (!mac_is_administrator()) {
        wp_die(__('You do not have permission to activate this plugin.'));
    }
    MAC_Settings::set_defaults();
    MAC_Capabilities::apply_defaults();
});

// Prevent non-admins from deactivating
add_filter('plugin_action_links', function($actions, $plugin_file) {
    if ($plugin_file === plugin_basename(__FILE__) && !mac_is_administrator()) {
        unset($actions['deactivate']);
    }
    return $actions;
}, 10, 2);

// Hide admin menu from non-admins
add_action('admin_menu', function() {
    if (!mac_is_administrator()) {
        remove_menu_page('manager-access-control');
    }
}, 999);

// Enqueue admin styles for settings page
add_action('admin_enqueue_scripts', function($hook) {
    // Load settings page CSS only on our settings page
    if ($hook === 'toplevel_page_manager-access-control') {
        wp_enqueue_style(
            'doodle-mac-admin-css',
            MAC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            MAC_VERSION
        );
    }
});

// Enqueue editor.css for managers throughout admin
add_action('admin_enqueue_scripts', function() {
    // Only enqueue for restricted users (managers)
    if (mac_is_restricted_user()) {
        wp_enqueue_style(
            'doodle-mac-editor-css',
            MAC_PLUGIN_URL . 'assets/editor.css',
            [],
            MAC_VERSION
        );
    }
});

/**
 * Check if manager role exists
 * @return bool True if any manager role exists
 */
function mac_manager_role_exists() {
    $all_roles = wp_roles()->roles;
    foreach ($all_roles as $role_slug => $role_data) {
        // Check for "manager" in role slug or name
        if (stripos($role_slug, 'manager') !== false || 
            stripos($role_data['name'], 'manager') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Handle role configuration downloads
 */
add_action('admin_post_mac_download_manager_role', function() {
    // Verify admin and nonce
    if (!mac_is_administrator() || !wp_verify_nonce($_GET['_wpnonce'], 'download_manager_role')) {
        wp_die('Access denied');
    }
    
    // Path to the static JSON file
    $file_path = MAC_PLUGIN_PATH . 'assets/aam-manager-role-export.json';
    
    // Verify file exists
    if (!file_exists($file_path)) {
        wp_die('Configuration file not found');
    }
    
    // Set download headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="aam-manager-role-export.json"');
    header('Content-Length: ' . filesize($file_path));
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    // Clear output buffer and send file
    ob_clean();
    flush();
    readfile($file_path);
    exit;
});

/**
 * Display setup instructions
 */
add_action('admin_notices', function() {
    // Only show to administrators
    if (!mac_is_administrator()) return;
    
    // Only show if manager role doesn't exist
    if (!mac_manager_role_exists()) : 
        // Use the download handler URL with nonce
        $download_url = wp_nonce_url(
            admin_url('admin-post.php?action=mac_download_manager_role'),
            'download_manager_role'
        );
        ?>
        <div class="notice notice-warning">
            <h3 style="margin: 1em 0;">Manager Role Setup</h3>
            <ol>
                <li>
                    First <a href="<?php echo admin_url('plugin-install.php?s=Advanced+Access+Manager&tab=search&type=term'); ?>">install and Activate AAM</a>
                </li>
                <li><a href="<?php echo esc_url($download_url); ?>">Download Pre-Configured Manager Role</a></li>
                <li>Next, Go to <a href="<?php echo esc_url(admin_url('admin.php?page=aam')); ?>">AAM Dashboard</a> → Settings → Export/Import AAM Settings</li>
                <li>And Import the downloaded file</li>
            </ol>
            <div style="margin-top:10px; padding:10px; background:#fff8e5; border-left:4px solid #ffb900;">
                <strong>Note:</strong> This will only add the Manager role without affecting existing roles.
            </div>
        </div>
    <?php endif;
});