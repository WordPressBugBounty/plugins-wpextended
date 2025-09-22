<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Admin Customiser (Color Picker) settings.
 */
class AdminCustomiser
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
        $existing = Utils::getSettings('admin-customiser');
        if (!empty($existing)) {
            return;
        }

        $legacy = get_option('wpext_admin_color', array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $settings = array();

        // Global appearance
        if (!empty($legacy['admin_body_color_field'])) {
            $color = sanitize_hex_color($legacy['admin_body_color_field']);
            if (!empty($color)) {
                $settings['body_bg_color'] = $color;
            }
        }

        // Admin footer text is not present in legacy; skip unless found
        if (!empty($legacy['admin_footer_text']) && is_string($legacy['admin_footer_text'])) {
            $settings['admin_footer_text'] = wp_kses_post($legacy['admin_footer_text']);
        }

        // Additional visibility
        $visibility = array();
        if (!empty($legacy['hide_help']) && Utils::isTruthy($legacy['hide_help'])) {
            $visibility[] = 'hide_help';
        }
        if (!empty($legacy['hide_screen_options']) && Utils::isTruthy($legacy['hide_screen_options'])) {
            $visibility[] = 'hide_screen_options';
        }
        if (!empty($visibility)) {
            $settings['additional_visibility'] = array_values(array_unique($visibility));
        }

        // Admin bar
        if (!empty($legacy['admin_bar_color_field'])) {
            $color = sanitize_hex_color($legacy['admin_bar_color_field']);
            if (!empty($color)) {
                $settings['admin_bar_bg_color'] = $color;
                // Set dropdown background to match main background if not separately provided
                $settings['admin_bar_bg_dropdown_color'] = $color;
            }
        }
        if (!empty($legacy['admin_bar_text_color_field'])) {
            $color = sanitize_hex_color($legacy['admin_bar_text_color_field']);
            if (!empty($color)) {
                $settings['admin_bar_text_color'] = $color;
            }
        }
        if (!empty($legacy['admin_bar_icons_color_field'])) {
            $color = sanitize_hex_color($legacy['admin_bar_icons_color_field']);
            if (!empty($color)) {
                $settings['admin_bar_text_hover_color'] = $color;
            }
        }
        if (!empty($legacy['wpext_admin_logo']) && filter_var($legacy['wpext_admin_logo'], FILTER_VALIDATE_URL)) {
            $logo_id = attachment_url_to_postid($legacy['wpext_admin_logo']);
            if (!empty($logo_id)) {
                $settings['admin_bar_logo'] = array((int) $logo_id);
            }
        }

        // Sidebar
        if (!empty($legacy['admin_side_bar_icons_color_field'])) {
            $color = sanitize_hex_color($legacy['admin_side_bar_icons_color_field']);
            if (!empty($color)) {
                $settings['sidebar_icons_color'] = $color;
            }
        }
        // Sidebar background and text colors from legacy generic fields
        if (!empty($legacy['color_picker_field'])) {
            $color = sanitize_hex_color($legacy['color_picker_field']);
            if (!empty($color)) {
                $settings['sidebar_bg_color'] = $color;
            }
        }
        if (!empty($legacy['font_color_field'])) {
            $color = sanitize_hex_color($legacy['font_color_field']);
            if (!empty($color)) {
                $settings['sidebar_text_color'] = $color;
            }
        }
        if (!empty($legacy['selected_menu_color_field'])) {
            $color = sanitize_hex_color($legacy['selected_menu_color_field']);
            if (!empty($color)) {
                $settings['selected_menu_bg_color'] = $color;
            }
        }
        if (!empty($legacy['selected_menu_text_color_field'])) {
            $color = sanitize_hex_color($legacy['selected_menu_text_color_field']);
            if (!empty($color)) {
                $settings['selected_menu_text_color'] = $color;
            }
        }
        if (!empty($legacy['hoverd_menu_color_field'])) {
            $color = sanitize_hex_color($legacy['hoverd_menu_color_field']);
            if (!empty($color)) {
                $settings['hover_menu_bg_color'] = $color;
            }
        }
        if (!empty($legacy['hoverd_menu_text_color_field'])) {
            $color = sanitize_hex_color($legacy['hoverd_menu_text_color_field']);
            if (!empty($color)) {
                $settings['hover_menu_text_color'] = $color;
            }
        }
        if (!empty($legacy['sidebar_width']) && is_numeric($legacy['sidebar_width'])) {
            $settings['sidebar_width'] = (int) $legacy['sidebar_width'];
        }

        // Sidebar icons hide toggle
        if (!empty($legacy['icon_toggle_field']) && Utils::isTruthy($legacy['icon_toggle_field'])) {
            $settings['hide_sidebar_icons'] = true;
        }

        // Admin bar visibility toggles → hide_admin_bar_elements
        $hide_elements = array();
        $map_toggles = array(
            'hide_admin_bar_logo_field'       => 'wp_logo',
            'hide_admin_bar_icons_field'      => 'icons',
            'hide_admin_bar_update_item_field' => 'updates',
            'hide_admin_bar_customize_item_field' => 'customize',
            'hide_admin_bar_comments_item_field'  => 'comments',
            'hide_admin_bar_new_item_field'   => 'new_item',
        );

        foreach ($map_toggles as $legacy_key => $choice) {
            if (!empty($legacy[$legacy_key]) && is_string($legacy[$legacy_key]) && strtolower($legacy[$legacy_key]) === 'on') {
                $hide_elements[] = $choice;
            }
        }

        if (!empty($hide_elements)) {
            $settings['hide_admin_bar_elements'] = array_values(array_unique($hide_elements));
        }

        // Login page
        if (!empty($legacy['login_screen_body_color_field'])) {
            $color = sanitize_hex_color($legacy['login_screen_body_color_field']);
            if (!empty($color)) {
                $settings['login_bg_color'] = $color;
            }
        }
        if (!empty($legacy['login_screen_text_color_field'])) {
            $color = sanitize_hex_color($legacy['login_screen_text_color_field']);
            if (!empty($color)) {
                $settings['login_text_color'] = $color;
            }
        }
        if (!empty($legacy['login_screen_logo_field']) && filter_var($legacy['login_screen_logo_field'], FILTER_VALIDATE_URL)) {
            $logo_id = attachment_url_to_postid($legacy['login_screen_logo_field']);
            if (!empty($logo_id)) {
                $settings['login_logo'] = array((int) $logo_id);
            }
        }

        if (!empty($legacy['login_screen_btn_back_color_field'])) {
            $color = sanitize_hex_color($legacy['login_screen_btn_back_color_field']);
            if (!empty($color)) {
                $settings['login_button_bg_color'] = $color;
            }
        }
        if (!empty($legacy['login_screen_button_color_field'])) {
            $color = sanitize_hex_color($legacy['login_screen_button_color_field']);
            if (!empty($color)) {
                $settings['login_button_text_color'] = $color;
            }
        }

        // Additional form options
        $form_options = array();
        if (!empty($legacy['hide_login_logo']) && Utils::isTruthy($legacy['hide_login_logo'])) {
            $form_options[] = 'hide_login_logo';
        }
        if (!empty($legacy['remember_me_checked']) && Utils::isTruthy($legacy['remember_me_checked'])) {
            $form_options[] = 'remember_me_checked';
        }
        if (!empty($form_options)) {
            $settings['form_options'] = array_values(array_unique($form_options));
        }

        if (!empty($settings)) {
            Utils::updateSettings('admin-customiser', $settings);
        }
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext_admin_color');
    }
}
