<?php
/**
 * Protects critical system plugins from manager access
 */
class MAC_Plugin_Protection {
    // Always hidden plugins
    const SYSTEM_PROTECTED = [
        'advanced-access-manager/aam.php',
        'greenshift-animation-and-page-builder-blocks/plugin.php',
        'greenshiftquery/greenshiftquery.php',
        'manager-access-control/manager-access-control.php'
    ];

    /**
     * Initialize plugin protection
     */
    public static function init($settings) {
        if ($settings->get_setting('enable_restrictions')) {
            add_action('admin_init', [__CLASS__, 'restrict_plugin_access']);
            add_filter('all_plugins', [__CLASS__, 'filter_plugin_list']);
        }
    }

    /**
     * Block access to plugins page
     */
    public static function restrict_plugin_access() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'plugins') {
            $user = wp_get_current_user();
            $settings = MAC_Settings::get_instance();
            $target_roles = $settings->get_setting('target_roles', []);
            
            if (array_intersect($user->roles, $target_roles)) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
        }
    }

    /**
     * Filter plugin list to hide system plugins
     */
    public static function filter_plugin_list($plugins) {
        $settings = MAC_Settings::get_instance();
        $user = wp_get_current_user();
        $target_roles = $settings->get_setting('target_roles', []);
        
        if (!array_intersect($user->roles, $target_roles)) {
            return $plugins;
        }
        
        // Remove system protected plugins
        foreach (self::SYSTEM_PROTECTED as $plugin_path) {
            unset($plugins[$plugin_path]);
        }
        
        // Remove settings-based protected plugins
        $protected = $settings->get_setting('protected_plugins', []);
        foreach ($protected as $plugin_path) {
            unset($plugins[$plugin_path]);
        }
        
        // Remove pattern-based protected plugins
        $patterns = $settings->get_setting('protected_patterns', []);
        foreach ($plugins as $path => $data) {
            foreach ($patterns as $pattern) {
                if (stripos($path, $pattern) !== false) {
                    unset($plugins[$path]);
                }
            }
        }
        
        return $plugins;
    }
}