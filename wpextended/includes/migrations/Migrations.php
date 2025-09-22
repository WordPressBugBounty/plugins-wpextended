<?php

namespace Wpextended\Includes\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

class Migrations
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'maybeMigrate']);
    }

    /**
     * Determine whether to run migrations.
     *
     * If the plugin version option is missing, run global and module migrations.
     */
    public function maybeMigrate(): void
    {
        // Only in admin context
        if (!is_admin()) {
            return;
        }

        // Check if version has been recorded
        $has_version = get_option('wpextended_version', false);

        if ($has_version) {
            return;
        }

        // Global migration (constructor performs mapping)
        $adminClass = '\\Wpextended\\Includes\\Migrations\\Admin';
        if (class_exists($adminClass)) {
            new $adminClass();
        }

        // Module migrations executed in order (single pass)
        foreach ($this->getModuleMigrationClasses() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $instance = new $class();

            if (method_exists($instance, 'run')) {
                $instance->run();
                continue;
            }
        }

        // Record current plugin version to avoid re-running migrations on every admin load
        if (defined('WP_EXTENDED_VERSION')) {
            update_option('wpextended_version', WP_EXTENDED_VERSION, false);
        }
    }

    /**
     * List of fully-qualified module migration classes to run.
     *
     * @return array<string>
     */
    private function getModuleMigrationClasses(): array
    {
        // Module list to migrate (sorted alphabetically)
        $modules = array(
            'BlockUsernames',
            'CleanDashboard',
            'CleanProfiles',
            'ClassicEditor',
            'CustomLoginUrl',
            'ExternalPermalinks',
            'HideAdminBar',
            'HideAdminNotices',
            'LimitLoginAttempts',
            'LinkManager',
            'MaintenanceMode',
            'PixelTagManager',
            'PostTypeOrder',
            'QuickImage',
            'SmtpEmail',
        );

        $dir = WP_EXTENDED_PATH . 'includes/migrations/modules/';
        $classes = array();

        if (!is_dir($dir)) {
            return $classes;
        }

        $files = glob($dir . '*.php');
        if (!is_array($files)) {
            return $classes;
        }

        foreach ($files as $file) {
            $base = basename($file, '.php');
            // Only allow valid PHP class names
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $base)) {
                continue;
            }

            $class = sprintf('\\Wpextended\\Includes\\Migrations\\Modules\\%s', $base);

            if (!class_exists($class)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
