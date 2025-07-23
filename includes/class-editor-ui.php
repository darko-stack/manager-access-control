<?php
/**
 * Handles dynamic editor UI restrictions with precise CSS targeting
 * Generates and injects CSS based on plugin settings
 */
class MAC_Editor_UI {

    /**
     * Initialize editor restrictions
     * @param object $settings Plugin settings instance
     */
    public static function init($settings) {
        if ($settings->get_setting('enable_restrictions')) {
            add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_editor_assets']);
            add_action('admin_head', [__CLASS__, 'enforce_editor_restrictions']);
        }
    }

    /**
     * Generate dynamic CSS with precise selectors
     * @param object $settings Plugin settings instance
     * @return string Generated CSS rules
     */
    private static function generate_dynamic_css($settings) {
        $restrictions = $settings->get_setting('editor_restrictions', []);
        $css = '';
        
        // 1. REUSABLE BLOCKS - Hide interface elements
        if ($restrictions['hide_reusable_blocks'] ?? false) {
            $css .= '
                /* Hide reusable blocks interface */
                .edit-post-sidebar__panel-tab[aria-label="Reusable blocks"],
                .components-menu-item__button[aria-label="Add to Reusable blocks"],
                a[href*="edit.php?post_type=wp_block"] { 
                    display: none !important; 
                }';
        }

        // 2. BLOCK SETTINGS - Hide toolbar
        if ($restrictions['hide_block_settings'] ?? false) {
            $css .= '
                /* Hide block settings toolbar */
                .block-editor-block-toolbar.is-synced { 
                    display: none !important; 
                }';
        }

        // 3. GREENSHIFT ELEMENTS - Hide controls
        if ($restrictions['hide_greenshift_buttons'] ?? false) {
            $css .= '
                /* Hide GreenShift UI elements */
                button[aria-label="GreenShift settings"],
                button[aria-label="GreenShift Helpers"],
                button[aria-controls^="greenshift-"] { 
                    display: none !important; 
                }';
        }

        // 4. DRAG HANDLES - Always hidden
        $css .= '
            /* Always hide block drag handles */
            .block-editor-block-mover__drag-handle { 
                display: none !important; 
            }';

        // 5. LIST VIEW - Hide toggle when disabled
        if ($restrictions['disable_list_view'] ?? true) {
            $css .= '
                /* Hide list view toggle */
                .components-toolbar-button.editor-document-tools__document-overview-toggle { 
                    display: none !important; 
                }';
        }

        // 6. ADD CUSTOM CSS
        $css .= $settings->get_setting('custom_css', '');

        return $css;
    }

    /**
     * Inject CSS through standard enqueue system
     */
    public static function enqueue_editor_assets() {
        $settings = MAC_Settings::get_instance();
        $user = wp_get_current_user();
        $target_roles = $settings->get_setting('target_roles', []);
        
        // Only apply to target roles
        if (!array_intersect($user->roles, $target_roles)) return;
        
        // Only in block editor context
        $screen = get_current_screen();
        if (!$screen || !$screen->is_block_editor()) return;
        
        // Inject dynamic CSS
        $dynamic_css = self::generate_dynamic_css($settings);
        wp_add_inline_style('wp-edit-post', $dynamic_css);
    }

    /**
     * Fallback CSS injection for maximum compatibility
     */
    public static function enforce_editor_restrictions() {
        $settings = MAC_Settings::get_instance();
        $user = wp_get_current_user();
        $target_roles = $settings->get_setting('target_roles', []);
        
        // Only apply to target roles
        if (!array_intersect($user->roles, $target_roles)) return;
        
        // Only in block editor context
        $screen = get_current_screen();
        if (!$screen || !$screen->is_block_editor()) return;
        
        // Output CSS directly as fallback
        $css = self::generate_dynamic_css($settings);
        echo '<style id="mac-dynamic-css">' . $css . '</style>';
    }
}