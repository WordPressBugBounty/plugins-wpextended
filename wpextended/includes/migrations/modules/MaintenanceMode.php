<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}
/**
 * Migrate legacy Maintenance Mode settings.
 */
class MaintenanceMode
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
        $existing = Utils::getSettings('maintenance-mode');
        if (!empty($existing)) {
            return;
        }

        $legacy = get_option('wpext-maintanance_mode', array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $settings = array();

        // Mode mapping
        if (!empty($legacy['wpext_sitemode']) && is_string($legacy['wpext_sitemode'])) {
            $map = array(
                'wpext_disable'      => 'disabled',
                'wpext_coming_soon'  => 'coming_soon',
                'wpext_maintenance'  => 'maintenance',
            );
            $legacy_mode = strtolower(trim($legacy['wpext_sitemode']));
            if (isset($map[$legacy_mode])) {
                $settings['mode'] = $map[$legacy_mode];
            }
        }

        // Layout mapping
        if (!empty($legacy['wpext_layout_option']) && is_string($legacy['wpext_layout_option'])) {
            $opt = $legacy['wpext_layout_option'];
            if ($opt === 'wp_page') {
                $settings['layout_type'] = 'page';
                if (!empty($legacy['wpext_select_layout']) && is_numeric($legacy['wpext_select_layout'])) {
                    $settings['existing_page'] = (int) $legacy['wpext_select_layout'];
                }
            } elseif ($opt === 'sel_layout') {
                $settings['layout_type'] = 'custom';
                if (!empty($legacy['wpext_choose_layout']) && is_string($legacy['wpext_choose_layout'])) {
                    // Expected format: wpe_mm_layout_{1..4}
                    $choice = $legacy['wpext_choose_layout'];
                    $layout_map = array(
                        'wpe_mm_layout_1' => 'layout-1',
                        'wpe_mm_layout_2' => 'layout-2',
                        'wpe_mm_layout_3' => 'layout-3',
                        'wpe_mm_layout_4' => 'layout-3', // best-effort mapping
                    );
                    if (isset($layout_map[$choice])) {
                        $settings['custom_layout'] = $layout_map[$choice];
                    }
                }
            }
        }

        // Authentication mapping
        if (!empty($legacy['wpext_uthentication']) && is_string($legacy['wpext_uthentication'])) {
            $auth_map = array(
                'loggedin'             => 'logged_in',
                'wpext_accessbyrole'   => 'roles',
            );
            $legacy_auth = strtolower(trim($legacy['wpext_uthentication']));
            if (isset($auth_map[$legacy_auth])) {
                $settings['auth_type'] = $auth_map[$legacy_auth];
            }
            if ($settings['auth_type'] === 'roles' && !empty($legacy['access_role']) && is_array($legacy['access_role'])) {
                $roles = array();
                foreach ($legacy['access_role'] as $role) {
                    if (is_string($role) && $role !== '') {
                        $roles[] = sanitize_key($role);
                    }
                }
                if (!empty($roles)) {
                    $settings['access_roles'] = array_values(array_unique($roles));
                }
            }
        }

        // Headline
        if (!empty($legacy['site_heading']) && is_string($legacy['site_heading'])) {
            $settings['headline_text'] = sanitize_text_field($legacy['site_heading']);
        }
        if (!empty($legacy['headline_color']) && is_string($legacy['headline_color'])) {
            $color = sanitize_hex_color($legacy['headline_color']);
            if (!empty($color)) {
                $settings['headline_colour'] = $color;
            }
        }

        // Body
        if (!empty($legacy['discription']) && is_string($legacy['discription'])) {
            $settings['body_text'] = wp_kses_post($legacy['discription']);
        }
        if (!empty($legacy['description_color']) && is_string($legacy['description_color'])) {
            $color = sanitize_hex_color($legacy['description_color']);
            if (!empty($color)) {
                $settings['body_colour'] = $color;
            }
        }

        // Footer
        if (!empty($legacy['footer_text']) && is_string($legacy['footer_text'])) {
            $settings['footer_text'] = sanitize_text_field($legacy['footer_text']);
        }
        if (!empty($legacy['footer_text_color']) && is_string($legacy['footer_text_color'])) {
            $color = sanitize_hex_color($legacy['footer_text_color']);
            if (!empty($color)) {
                $settings['footer_colour'] = $color;
            }
        }

        // Background
        if (!empty($legacy['bg_color_code']) && is_string($legacy['bg_color_code'])) {
            $color = sanitize_hex_color($legacy['bg_color_code']);
            if (!empty($color)) {
                $settings['background_colour'] = $color;
            }
        }

        // Background image toggle and attachment mapping
        if (!empty($legacy['wpext_backgroung'])) {
            if ($legacy['wpext_backgroung'] === 'wpext_bgimg') {
                // Map legacy image mode to new toggle regardless of URL presence
                $settings['enable_background_image'] = true;
                if (!empty($legacy['coming_img']) && filter_var($legacy['coming_img'], FILTER_VALIDATE_URL)) {
                    // Try to resolve URL to attachment ID
                    $bg_id = attachment_url_to_postid($legacy['coming_img']);
                    if (!empty($bg_id)) {
                        $settings['background_image'] = array((int) $bg_id);
                    }
                }
            } elseif ($legacy['wpext_backgroung'] === 'wpext_bgcolor') {
                // Explicitly ensure the toggle is off when color is selected
                $settings['enable_background_image'] = false;
            }
        }

        // Logo settings (map URL to attachment ID when possible)
        if (!empty($legacy['header_logo']) && filter_var($legacy['header_logo'], FILTER_VALIDATE_URL)) {
            $logo_id = attachment_url_to_postid($legacy['header_logo']);
            if (!empty($logo_id)) {
                $settings['enable_logo'] = 'yes';
                $settings['logo_image'] = array((int) $logo_id);
            }
        } elseif (!empty($legacy['wpext_logo_option']) && (string) $legacy['wpext_logo_option'] === '1') {
            // Legacy may indicate enabling logo without providing a URL
            $settings['enable_logo'] = 'yes';
        }
        if (!empty($legacy['logo_width']) && is_numeric($legacy['logo_width'])) {
            $settings['logo_width'] = (int) $legacy['logo_width'];
        }

        // Custom CSS
        if (!empty($legacy['wpext_mm_custom_css']) && is_string($legacy['wpext_mm_custom_css'])) {
            $settings['custom_css'] = (string) $legacy['wpext_mm_custom_css'];
        }

        if (!empty($settings)) {
            Utils::updateSettings('maintenance-mode', $settings);
        }
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-maintanance_mode');
    }
}
