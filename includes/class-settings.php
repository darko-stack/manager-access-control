<?php
/**
 * Manages all plugin settings and admin interface
 * 
 * Handles settings registration, sanitization, and admin page rendering
 * Contains all UI components for the settings page
 */
class MAC_Settings {
    private static $instance;          // Singleton instance
    private $settings;                // Current settings array
    private $settings_key = 'doodle_mac_settings';  // Database option key

    /**
     * Get singleton instance
     * @return MAC_Settings Singleton instance
     */
    public static function get_instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private for singleton pattern
     * Loads settings and sets up admin interface
     */
    private function __construct() {
        $this->settings = get_option($this->settings_key, []);
        $this->setup_admin();
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
    }

    /**
     * Set up admin menu and settings registration
     */
    private function setup_admin() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
    }

    /**
     * Add admin menu item
     * Only visible to WordPress Administrators
     */
    public function add_admin_menu() {
        if (!mac_is_administrator()) return;
        
        $hook = add_menu_page(
            'Manager Access Control',
            'Manager Access',
            'manage_options',
            'manager-access-control',
            [$this, 'render_settings_page'],
            'dashicons-shield-alt',
            80
        );
        
        add_action("load-$hook", [$this, 'check_admin_access']);
    }

    /**
     * Verify admin privileges before allowing access
     */
    public function check_admin_access() {
        if (!mac_is_administrator()) {
            wp_die(__('Access denied: Requires Administrator privileges.'));
        }
    }

    /**
     * Set default settings on plugin activation
     */
    public static function set_defaults() {
        update_option('doodle_mac_settings', self::get_default_settings());
    }

    /**
     * Get default settings array
     * @return array Default settings configuration
     */
    public static function get_default_settings() {
        return [
            'enable_restrictions' => true,
            'target_roles' => ['admin_manager'],
            'protected_plugins' => [],
            'protected_patterns' => [],
            'editor_restrictions' => [
                'hide_reusable_blocks' => true,
                'hide_block_settings' => true,
                'hide_greenshift_buttons' => true,
                'hide_reusable_popover' => true,
                'disable_list_view' => false,
                'show_drag_handles' => true
            ],
            'custom_css' => ''
        ];
    }

    /**
     * Get merged settings (defaults + saved values)
     * @return array Current settings
     */
    public function get_settings() {
        return array_merge(self::get_default_settings(), $this->settings);
    }

    /**
     * Get specific setting value
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return $settings[$key] ?? $default;
    }

    /**
     * Register all settings with WordPress
     * Creates settings sections and fields
     */
    public function register_settings() {
        register_setting(
            'doodle_mac_settings_group',
            $this->settings_key,
            [$this, 'sanitize_settings']
        );

        /* GENERAL SETTINGS SECTION */
        add_settings_section(
            'doodle_mac_general_section',
            'General Settings',
            [$this, 'render_general_section'],
            'manager-access-control'
        );

        add_settings_field(
            'enable_restrictions',
            'Enable Restrictions',
            [$this, 'render_enable_restrictions_field'],
            'manager-access-control',
            'doodle_mac_general_section'
        );

        add_settings_field(
            'target_roles',
            'Target Roles',
            [$this, 'render_target_roles_field'],
            'manager-access-control',
            'doodle_mac_general_section'
        );

        /* PLUGIN PROTECTION SECTION */
        add_settings_section(
            'doodle_mac_plugin_section',
            'Plugin Protection',
            [$this, 'render_plugin_section'],
            'manager-access-control'
        );

        add_settings_field(
            'protected_plugins',
            'Restricted Plugins',
            [$this, 'render_protected_plugins_field'],
            'manager-access-control',
            'doodle_mac_plugin_section'
        );

        add_settings_field(
            'protected_patterns',
            'Other Protected Plugins',
            [$this, 'render_protected_patterns_field'],
            'manager-access-control',
            'doodle_mac_plugin_section'
        );

        /* BLOCK EDITOR RESTRICTIONS SECTION */
        add_settings_section(
            'doodle_mac_editor_section',
            'Block Editor Restrictions',
            [$this, 'render_editor_section'],
            'manager-access-control'
        );

        add_settings_field(
            'editor_restrictions',
            'Editor Interface Options',
            [$this, 'render_editor_restrictions_field'],
            'manager-access-control',
            'doodle_mac_editor_section'
        );

        add_settings_field(
            'custom_css',
            'Custom CSS',
            [$this, 'render_custom_css_field'],
            'manager-access-control',
            'doodle_mac_editor_section'
        );
    }

    /**
     * Sanitize and validate settings before saving
     * @param array $input Unsanitized settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        $defaults = self::get_default_settings();

        // Enable restrictions (checkbox)
        $sanitized['enable_restrictions'] = isset($input['enable_restrictions']);

        // Target roles (array)
        $sanitized['target_roles'] = [];
        $available_roles = array_keys(get_editable_roles());
        if (isset($input['target_roles']) && is_array($input['target_roles'])) {
            foreach ($input['target_roles'] as $role) {
                if (in_array($role, $available_roles)) {
                    $sanitized['target_roles'][] = sanitize_text_field($role);
                }
            }
        }

        // Protected plugins (array)
        $sanitized['protected_plugins'] = [];
        if (isset($input['protected_plugins']) && is_array($input['protected_plugins'])) {
            foreach ($input['protected_plugins'] as $plugin) {
                $sanitized['protected_plugins'][] = sanitize_text_field($plugin);
            }
        }

        // Protected patterns (array)
        $sanitized['protected_patterns'] = [];
        if (isset($input['protected_patterns']) && is_array($input['protected_patterns'])) {
            foreach ($input['protected_patterns'] as $pattern) {
                $sanitized['protected_patterns'][] = sanitize_text_field($pattern);
            }
        }

        // Editor restrictions (checkboxes)
        $sanitized['editor_restrictions'] = [];
        $editor_options = array_keys($defaults['editor_restrictions']);
        foreach ($editor_options as $option) {
            $sanitized['editor_restrictions'][$option] = isset($input['editor_restrictions'][$option]);
        }

        // Custom CSS (textarea)
        $sanitized['custom_css'] = isset($input['custom_css']) ? sanitize_textarea_field($input['custom_css']) : '';

        return $sanitized;
    }

    /**
     * Render the main settings page
     */
    public function render_settings_page() {
        $this->check_admin_access();
        
        wp_enqueue_style(
            'doodle-mac-admin-css',
            MAC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            MAC_VERSION
        );
        
        include MAC_PLUGIN_PATH . 'admin/partials/settings-page.php';
    }

    /* SECTION DESCRIPTION RENDERERS */

    public function render_general_section() {
        echo '<p>Configure general restrictions settings</p>';
    }

    public function render_plugin_section() {
        echo '<p>Configure which plugins to hide and protect from manager access</p>';
    }

    public function render_editor_section() {
        echo '<p>Configure restrictions for the Block Editor interface</p>';
    }

    /* SETTINGS FIELD RENDERERS */

    public function render_enable_restrictions_field() {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="doodle_mac_settings[enable_restrictions]" value="1" <?php checked($settings['enable_restrictions'], true); ?>>
            Enable all restrictions
        </label>
        <p class="description">Uncheck to temporarily disable all restrictions</p>
        <?php
    }

    public function render_target_roles_field() {
        $settings = $this->get_settings();
        $roles = get_editable_roles();
        ?>
        <div class="mac-checkbox-group">
            <?php foreach ($roles as $slug => $role) : 
                if ($slug === 'administrator') continue;
                $checked = in_array($slug, $settings['target_roles']) ? 'checked' : '';
            ?>
                <div class="mac-checkbox-item">
                    <input type="checkbox" name="doodle_mac_settings[target_roles][]" 
                           id="role_<?php echo esc_attr($slug); ?>" 
                           value="<?php echo esc_attr($slug); ?>" <?php echo $checked; ?>>
                    <label for="role_<?php echo esc_attr($slug); ?>">
                        <?php echo esc_html($role['name']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="description">Select which roles these restrictions should apply to</p>
        <?php
    }

    public function render_protected_plugins_field() {
        $settings = $this->get_settings();
        $all_plugins = get_plugins();
        $system_plugins = MAC_Plugin_Protection::SYSTEM_PROTECTED;
        ?>
        <div class="mac-plugin-list">
            <p class="description">Select plugins to hide from non-administrator roles:</p>
            <?php foreach ($all_plugins as $path => $plugin) : 
                $is_system = in_array($path, $system_plugins);
            ?>
                <div class="mac-checkbox-item <?php if($is_system) echo 'mac-default-hidden'; ?>">
                    <input type="checkbox" 
                        name="doodle_mac_settings[protected_plugins][]" 
                        id="plugin_<?php echo sanitize_title($path); ?>" 
                        value="<?php echo esc_attr($path); ?>"
                        <?php if($is_system) echo 'checked disabled'; else checked(in_array($path, $settings['protected_plugins'])); ?>>
                    <label for="plugin_<?php echo sanitize_title($path); ?>">
                        <?php echo esc_html($plugin['Name']); ?>
                        <code><?php echo esc_html($path); ?></code>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function render_protected_patterns_field() {
        $settings = $this->get_settings();
        $patterns = $settings['protected_patterns'];
        $patterns_str = implode(', ', $patterns);
        ?>
        <input type="text" 
            name="doodle_mac_settings[protected_patterns]" 
            value="<?php echo esc_attr($patterns_str); ?>" 
            class="regular-text">
        <p class="description">
            Enter <strong>relative plugin paths</strong> (comma-separated) that should be hidden.<br>
            Example: <code>my-plugin/my-plugin.php, another-plugin/main-file.php</code>
        </p>
        <p class="description">
            These will be hidden in addition to system plugins and your selected restricted plugins.
        </p>
        <?php
    }

    public function render_editor_restrictions_field() {
        $settings = $this->get_settings();
        $restrictions = $settings['editor_restrictions'];
        $options = [
            'hide_reusable_blocks' => 'Hide reusable blocks library & conversion options',
            'hide_block_settings' => 'Hide block settings panel (three-dot menu)',
            'hide_greenshift_buttons' => 'Hide GreenShift controls (editor toolbar & inspector)',
            'hide_reusable_popover' => 'Hide "Add to Reusable blocks" option',
            'disable_list_view' => 'Disable document outline / list view',
            'show_drag_handles' => 'Enable block drag handles <span class="mac-beta-badge">Experimental</span>'
        ];
        ?>
        <div class="mac-checkbox-group">
            <?php foreach ($options as $key => $label) : ?>
                <div class="mac-checkbox-item">
                    <?php if ($key === 'show_drag_handles') : ?>
                        <input type="hidden" name="doodle_mac_settings[editor_restrictions][show_drag_handles]" value="1">
                        <input type="checkbox" id="editor_show_drag_handles" checked disabled>
                        <label for="editor_show_drag_handles">
                            <?php echo $label; ?>
                        </label>
                        <p class="description">Always enabled</p>
                    <?php else : ?>
                        <input type="checkbox" 
                            name="doodle_mac_settings[editor_restrictions][<?php echo $key; ?>]" 
                            id="editor_<?php echo $key; ?>" 
                            value="1" <?php checked($restrictions[$key], true); ?>>
                        <label for="editor_<?php echo $key; ?>"><?php echo $label; ?></label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function render_custom_css_field() {
        $settings = $this->get_settings();
        ?>
        <textarea name="doodle_mac_settings[custom_css]" class="large-text code" rows="10"><?php 
            echo esc_textarea($settings['custom_css']); 
        ?></textarea>
        <p class="description">Add custom CSS for additional editor restrictions</p>
        <?php
    }

    /**
     * Add settings link to plugin list
     * @param array $links Existing plugin action links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        if (!mac_is_administrator()) return $links;
        
        $settings_link = '<a href="' . admin_url('admin.php?page=manager-access-control') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Clean up on plugin uninstallation
     * Removes all plugin settings from database
     */
    public static function uninstall() {
        if (!mac_is_administrator()) {
            wp_die(__('Uninstallation requires Administrator privileges.'));
        }
        delete_option('doodle_mac_settings');
    }
}