<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy SMTP Email settings.
 */
class SmtpEmail
{
    /**
     * Run the module migration and clean up legacy options.
     */
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    /**
     * Perform migration from legacy options to new module settings.
     */
    public function migrate(): void
    {
        // If already migrated, skip
        $existing = Utils::getSettings('smtp-email');
        if (!empty($existing)) {
            return;
        }

        // Fetch legacy options
        $legacy_from_name   = get_option('wpext_smtp_from_name', '');
        $legacy_from_email  = get_option('wpext_smtp_from_email', '');
        $legacy_host        = get_option('wpext_smtp_host', '');
        $legacy_port_value  = get_option('wpext_smtp_port', ''); // may be string or int
        $legacy_username    = get_option('wpext_smtp_username', '');
        $legacy_password    = get_option('wpext_smtp_password', '');
        $legacy_log_toggle  = get_option('wpext_disable_email_Log', ''); // "on" to enable logging view
        $legacy_port_choice = get_option('smtp_post_number', ''); // "25" | "465" | "587"

        // Normalize and infer values
        $port = 0;
        if (is_numeric($legacy_port_value)) {
            $port = (int) $legacy_port_value;
        } elseif (is_numeric($legacy_port_choice)) {
            $port = (int) $legacy_port_choice;
        }

        // Infer encryption from common SMTP ports if not explicitly defined in legacy
        $encryption = 'none';
        if ($port === 465) {
            $encryption = 'ssl';
        } elseif ($port === 587) {
            $encryption = 'tls';
        } else {
            $encryption = 'none';
        }

        $enable_email_logs = Utils::isTruthy($legacy_log_toggle);

        // Build new settings array, only including meaningful values
        $settings = [];

        if (is_string($legacy_from_name) && $legacy_from_name !== '') {
            $settings['from_name'] = sanitize_text_field($legacy_from_name);
        }

        if (is_string($legacy_from_email) && $legacy_from_email !== '') {
            $settings['from_email'] = sanitize_email($legacy_from_email);
        }

        if (is_string($legacy_host) && $legacy_host !== '') {
            $settings['host'] = sanitize_text_field($legacy_host);
        }

        if ($port > 0) {
            $settings['port'] = $port;
        }

        if (is_string($legacy_username) && $legacy_username !== '') {
            $settings['username'] = sanitize_text_field($legacy_username);
        }

        if (is_string($legacy_password) && $legacy_password !== '') {
            // Do not aggressively sanitize passwords to avoid destructive changes
            $settings['password'] = $legacy_password;
        }

        // Always set these booleans to sensible defaults aligned with new schema
        $settings['force_from_email'] = false;
        $settings['force_from_name']  = false;
        $settings['auto_tls']         = true;

        if (!isset($settings['encryption'])) {
            $settings['encryption'] = $encryption;
        }

        $settings['enable_email_logs'] = $enable_email_logs;
        $settings['log_retention']     = 'forever';

        // If nothing meaningful to migrate, skip
        $has_meaningful = false;
        foreach (['from_name','from_email','host','port','username','password'] as $key) {
            if (isset($settings[$key]) && $settings[$key] !== '' && $settings[$key] !== 0) {
                $has_meaningful = true;
                break;
            }
        }
        if (!$has_meaningful && $enable_email_logs === false) {
            return;
        }

        Utils::updateSettings('smtp-email', $settings);
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext_smtp_from_name');
        delete_option('wpext_smtp_from_email');
        delete_option('wpext_smtp_host');
        delete_option('wpext_smtp_port');
        delete_option('wpext_smtp_username');
        delete_option('wpext_smtp_password');
        delete_option('wpext_disable_email_Log');
        delete_option('smtp_post_number');
    }
}
