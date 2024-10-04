<?php

if (! defined('ABSPATH')) {
    die();
}

class Wp_Extended_Menu_Visibility extends Wp_Extended
{
    public function __construct()
    {
        parent::__construct();
        add_action('wp_nav_menu_item_custom_fields', array($this, 'wpext_custom_menu_item_fields'), 10, 4);
        add_action('wp_update_nav_menu_item', array($this, 'wpext_save_custom_menu_item_fields'), 10, 3);
        add_filter('wp_get_nav_menu_items', array($this, 'wpext_filter_menu_items_by_login'), 10, 3);
    }
    public static function init()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new Wp_Extended_Menu_Visibility(get_called_class(), WP_EXTENDED_VERSION);
        }
        return $instance;
    } // init

    public function wpext_custom_menu_item_fields($item_id, $item, $depth, $args)
    {
        // Get the saved checkbox value
        $is_visible = get_post_meta($item_id, '_wpext_menu_item_visible', true);

        // Output the radio button field
        ?>
    <fieldset class="field_wpext_menu_role nav_menu_logged_in_out_field description-wide mt-2">
        <span class="menu-item-title"><?php esc_html_e('Menu Item Visibility For', WP_EXTENDED_TEXT_DOMAIN); ?></span><br/>
        <label>
              <input type="radio" class="widefat" name="wpext_menu_item_visible[<?php echo esc_attr($item_id); ?>]" value="1" <?php checked('1', $is_visible); ?> />
              <?php esc_html_e('Logged In', WP_EXTENDED_TEXT_DOMAIN); ?>
          </label>
          <label>
              <input type="radio" class="widefat" name="wpext_menu_item_visible[<?php echo esc_attr($item_id); ?>]" value="2" <?php checked('2', $is_visible); ?> />
              <?php esc_html_e('Logged Out', WP_EXTENDED_TEXT_DOMAIN); ?>
          </label>
          <label>
              <input type="radio" class="widefat" name="wpext_menu_item_visible[<?php echo esc_attr($item_id); ?>]" value="" <?php checked('', $is_visible); ?> />
              <?php esc_html_e('Everyone', WP_EXTENDED_TEXT_DOMAIN); ?>
          </label>
      </fieldset>
        <?php
    }
    public function wpext_save_custom_menu_item_fields($menu_id, $menu_item_db_id, $menu_item_args)
    {
        if (isset($_POST['wpext_menu_item_visible'][$menu_item_db_id]) && !empty($_POST['wpext_menu_item_visible'][$menu_item_db_id])) {
            $visibility_option = wp_unslash($_POST['wpext_menu_item_visible'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_wpext_menu_item_visible', $visibility_option);
        } else {
            delete_post_meta($menu_item_db_id, '_wpext_menu_item_visible');
        }
    }

    // Filter menu items based on user login status
    public function wpext_filter_menu_items_by_login($items, $menu, $args)
    {
        if (is_admin()) {
            return $items; // Return all items in the admin area
        }

        foreach ($items as $key => $item) {
            $remove = false;
            $visibility = get_post_meta($item->ID, '_wpext_menu_item_visible', true);

            if (empty($visibility) || $visibility == '') {
                continue;
            }

            /* Logged out condition */
            if ($visibility == '2' && is_user_logged_in()) {
                $remove = true;
            }

            /* Logged in condition */
            if ($visibility == '1' && !is_user_logged_in()) {
                $remove = true;
            }

            if ($remove) {
                unset($items[$key]);
            }
        }

        return $items;
    }
}
Wp_Extended_Menu_Visibility::init();
