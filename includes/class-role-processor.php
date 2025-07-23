<?php
/**
 * Handles the default role configuration import
 */
class MAC_Role_Processor {
    /**
     * Get the unmodified AAM export for the Manager role
     * @return string|bool JSON content or false on failure
     */
    public static function get_default_config() {
        $file = MAC_PLUGIN_PATH . 'assets/aam-export-manager-role.json';
        return file_exists($file) ? file_get_contents($file) : false;
    }
}