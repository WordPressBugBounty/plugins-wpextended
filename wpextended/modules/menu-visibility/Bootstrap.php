<?php

/**
 * Menu Visibility Module
 *
 * @package WP Extended Pro
 * @subpackage MenuVisibility
 */

namespace Wpextended\Modules\MenuVisibility;

use Wpextended\Modules\BaseModule;

/**
 * Class Bootstrap
 *
 * @since 1.0.0
 */
class Bootstrap extends BaseModule
{
    /**
     * Visibility options for menu items
     *
     * @var array
     */
    private $visibility_options;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('menu-visibility');
        $this->setVisibilityOptions();
    }

    /**
     * Initialize visibility options
     *
     * @return void
     */
    private function setVisibilityOptions(): void
    {
        $this->visibility_options = array(
            '1' => __('Logged In', WP_EXTENDED_TEXT_DOMAIN),
            '2' => __('Logged Out', WP_EXTENDED_TEXT_DOMAIN),
            ''  => __('Everyone', WP_EXTENDED_TEXT_DOMAIN),
        );
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('wp_nav_menu_item_custom_fields', array($this, 'renderCustomMenuItemFields'), 10, 4);
        add_action('wp_update_nav_menu_item', array($this, 'saveCustomMenuItemFields'), 10, 3);
        add_filter('wp_get_nav_menu_items', array($this, 'filterMenuItemsByLogin'), 10, 3);
    }

    /**
     * Render custom menu item fields
     *
     * @param int      $item_id The menu item ID
     * @param \WP_Post $item   The menu item object
     * @param int      $depth  The depth of the menu item
     * @param \stdClass $args  Additional arguments
     * @return void
     */
    public function renderCustomMenuItemFields(int $item_id, \WP_Post $item, int $depth, \stdClass $args): void
    {
        $is_visible = $this->getMenuItemVisibility($item_id);
        ?>
        <fieldset class="field_wpext_menu_role nav_menu_logged_in_out_field description-wide mt-2">
            <legend class="menu-item-title">
                <?php esc_html_e('Menu Item Visibility For', WP_EXTENDED_TEXT_DOMAIN); ?>
            </legend>
            <?php wp_nonce_field('wpext_menu_visibility', 'wpext_menu_visibility_nonce'); ?>
            <?php foreach ($this->visibility_options as $value => $label) : ?>
                <label class="menu-item-visibility-option">
                    <input
                        type="radio"
                        class="widefat"
                        name="<?php echo esc_attr($this->getFieldName($item_id)); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        <?php checked($value, $is_visible); ?>
                        aria-label="<?php echo esc_attr(sprintf(
                                        /* translators: %s: visibility option */
                            __('Set visibility to %s', WP_EXTENDED_TEXT_DOMAIN),
                            $label
                        )); ?>" />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    /**
     * Save custom menu item fields
     *
     * @param int   $menu_id         The menu ID
     * @param int   $menu_item_db_id The menu item database ID
     * @param array $menu_item_args  The menu item arguments
     * @return void
     */
    public function saveCustomMenuItemFields(int $menu_id, int $menu_item_db_id, array $menu_item_args): void
    {
        // Check user capabilities first
        if (!current_user_can('edit_theme_options')) {
            return;
        }

        // Verify our custom nonce
        if (
            !isset($_POST['wpext_menu_visibility_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['wpext_menu_visibility_nonce']), 'wpext_menu_visibility')
        ) {
            return;
        }

        // Sanitize and validate the visibility option
        $visibility_option = isset($_POST['wpext_menu_item_visible'][$menu_item_db_id])
            ? sanitize_text_field(wp_unslash($_POST['wpext_menu_item_visible'][$menu_item_db_id]))
            : '';

        // Validate the visibility option against allowed values
        if (!array_key_exists($visibility_option, $this->visibility_options)) {
            $visibility_option = '';
        }

        if (!empty($visibility_option)) {
            update_post_meta($menu_item_db_id, '_wpext_menu_item_visible', $visibility_option);
            return;
        }

        delete_post_meta($menu_item_db_id, '_wpext_menu_item_visible');
    }

    /**
     * Filter menu items based on user login status
     *
     * @param array  $items The menu items
     * @param object $menu  The menu object
     * @param array  $args  Additional arguments
     * @return array
     */
    public function filterMenuItemsByLogin(array $items, object $menu, array $args): array
    {
        if (is_admin()) {
            return $items;
        }

        return array_filter($items, array($this, 'shouldDisplayMenuItem'));
    }

    /**
     * Check if a menu item should be displayed based on user login status
     *
     * @param \WP_Post $item The menu item object
     * @return bool True if the menu item should be displayed, false otherwise
     */
    private function shouldDisplayMenuItem(\WP_Post $item): bool
    {
        $visibility = $this->getMenuItemVisibility($item->ID);

        // If no visibility setting is set, show the menu item to everyone
        if (empty($visibility)) {
            return true;
        }

        $is_logged_in = is_user_logged_in();

        // visibility is '1' (logged in only) AND user is logged in
        if ($visibility === '1' && $is_logged_in) {
            return true;
        }

        // visibility is '2' (logged out only) AND user is not logged in
        if ($visibility === '2' && !$is_logged_in) {
            return true;
        }

        return false;
    }

    /**
     * Get menu item visibility setting
     *
     * @param int $item_id The menu item ID
     * @return string
     */
    private function getMenuItemVisibility(int $item_id): string
    {
        return (string) get_post_meta($item_id, '_wpext_menu_item_visible', true);
    }

    /**
     * Get field name for menu item
     *
     * @param int $item_id The menu item ID
     * @return string
     */
    private function getFieldName(int $item_id): string
    {
        return sprintf('wpext_menu_item_visible[%d]', $item_id);
    }
}
