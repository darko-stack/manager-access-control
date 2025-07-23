<?php
/**
 * Manages role capabilities for restricted access
 */
class MAC_Capabilities {
	/**
	 * Initialize capability modifications
	 */
	public static function init($settings) {
		if ($settings->get_setting('enable_restrictions')) {
			add_action('init', [__CLASS__, 'modify_role_capabilities']);
		}
	}

	/**
	 * List of block editor capabilities to remove
	 */
	private static function get_restricted_caps() {
		return [
			'edit_blocks',
			'edit_others_blocks',
			'publish_blocks',
			'delete_blocks',
			'delete_others_blocks',
			'read_private_blocks',
			'edit_published_blocks',
			'delete_published_blocks',
			'edit_private_blocks',
			'delete_private_blocks'
		];
	}

	/**
	 * Modify capabilities for target roles
	 */
	public static function modify_role_capabilities() {
		$settings = MAC_Settings::get_instance();
		$target_roles = $settings->get_setting('target_roles', []);
		
		foreach ($target_roles as $role_slug) {
			$role = get_role($role_slug);
			if ($role) {
				foreach (self::get_restricted_caps() as $cap) {
					$role->remove_cap($cap);
				}
			}
		}
	}

	/**
	 * Apply default capability restrictions
	 */
	public static function apply_defaults() {
		$role = get_role('admin_manager');
		if ($role) {
			foreach (self::get_restricted_caps() as $cap) {
				$role->remove_cap($cap);
			}
		}
	}
}