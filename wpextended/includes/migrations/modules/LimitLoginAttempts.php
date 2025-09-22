<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Limit Login Attempts settings.
 */
class LimitLoginAttempts
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
        $existing = Utils::getSettings('limit-login-attempts');
        if (!empty($existing)) {
            return;
        }

        // Legacy group config and standalone options
        $legacy_group = get_option('wpext-user-login-attempt-config', []);
        if (!is_array($legacy_group)) {
            $legacy_group = [];
        }

        $legacy_attempts = get_option('login_attempts', 3);
        $legacy_lockout  = get_option('lockout_time', 30);

        // Determine max attempts
        $maxAttempts = 0;
        if (isset($legacy_group['login_attempts']) && is_numeric($legacy_group['login_attempts'])) {
            $maxAttempts = (int) $legacy_group['login_attempts'];
        } elseif (is_numeric($legacy_attempts)) {
            $maxAttempts = (int) $legacy_attempts;
        }

        // Determine lockout time (minutes)
        $lockoutTime = 0;
        if (isset($legacy_group['lockout_time']) && is_numeric($legacy_group['lockout_time'])) {
            $lockoutTime = (int) $legacy_group['lockout_time'];
        } elseif (is_numeric($legacy_lockout)) {
            $lockoutTime = (int) $legacy_lockout;
        }

        // Booleans with safe defaults
        $notifyAdmin = false;
        foreach (['notify_admin', 'email_admin', 'notify', 'email_on_block'] as $key) {
            if (array_key_exists($key, $legacy_group)) {
                $notifyAdmin = Utils::isTruthy($legacy_group[$key]);
                break;
            }
        }

        $blockByUsername = false;
        foreach (['block_username_attempts', 'block_username', 'block_by_username'] as $key) {
            if (array_key_exists($key, $legacy_group)) {
                $blockByUsername = Utils::isTruthy($legacy_group[$key]);
                break;
            }
        }

        // Build new settings
        $settings = [
            'max_attempts'             => ($maxAttempts > 0 ? $maxAttempts : 3),
            'lockout_time'             => ($lockoutTime > 0 ? $lockoutTime : 30),
            'notify_admin'             => $notifyAdmin,
            'block_username_attempts'  => $blockByUsername,
        ];

        Utils::updateSettings('limit-login-attempts', $settings);
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-user-login-attempt-config');
        delete_option('login_attempts');
        delete_option('lockout_time');
    }
}
