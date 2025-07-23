<?php
/**
 * Handles reusable blocks restrictions
 */
class MAC_Reusable_Blocks {
    /**
     * Initialize reusable blocks restrictions
     */
    public static function init($settings) {
        if ($settings->get_setting('enable_restrictions')) {
            add_action('admin_init', [__CLASS__, 'block_reusable_blocks_access']);
            add_filter('register_post_type_args', [__CLASS__, 'restrict_reusable_blocks'], 10, 2);
        }
    }

    /**
     * Block direct access to reusable blocks interface
     */
    public static function block_reusable_blocks_access() {
        global $pagenow;
        
        $settings = MAC_Settings::get_instance();
        $user = wp_get_current_user();
        $target_roles = $settings->get_setting('target_roles', []);
        
        if (!array_intersect($user->roles, $target_roles)) return;
        
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'wp_block') {
            wp_die(__('Access to reusable blocks is restricted.'));
        }
    }

    /**
     * Remove reusable blocks from admin menu
     */
    public static function restrict_reusable_blocks($args, $post_type) {
        if ($post_type === 'wp_block') {
            $settings = MAC_Settings::get_instance();
            $user = wp_get_current_user();
            $target_roles = $settings->get_setting('target_roles', []);
            
            if (array_intersect($user->roles, $target_roles)) {
                $args['show_in_menu'] = false;
            }
        }
        return $args;
    }
}