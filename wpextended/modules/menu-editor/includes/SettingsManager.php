<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Manages settings fields and data management
 */
class SettingsManager
{
    private MenuItemFactory $itemFactory;
    private AccessManager $accessManager;

    public function __construct(MenuItemFactory $itemFactory, AccessManager $accessManager)
    {
        $this->itemFactory = $itemFactory;
        $this->accessManager = $accessManager;
    }

    /**
     * Get module settings fields
     *
     * @return array Module settings fields
     */
    public function getSettingsFields(): array
    {
        $settings = [];

        $settings['tabs'] = array(
        array(
            'id' => 'settings',
            'title' => __('Menu Editor', WP_EXTENDED_TEXT_DOMAIN),
        ),
        );

        $settings['sections'] = array(
        array(
            'tab_id' => 'settings',
            'section_id' => 'settings',
            'section_title' => '',
            'section_description' => '',
            'fields' => array(
                array(
                    'id' => 'menu_items',
                    'type' => 'group',
                    'title' => __('Menu Items', WP_EXTENDED_TEXT_DOMAIN),
                    'description' => __('Configure your admin menu structure. Drag and drop to reorder items.', WP_EXTENDED_TEXT_DOMAIN),
                    'sortable' => array(
                        'nesting' => true,
                        'max_depth' => 1,
                        'current_depth' => true,
                    ),
                    'actions' => array(),
                    'collapsible' => true,
                    'collapsed' => true,
                    'title_template' => '{title}',
                    'subfields' => $this->getMenuSubfields(),
                ),
            ),
        ),
        );

        return $settings;
    }

    /**
     * Get menu subfields configuration
     *
     * @return array Menu subfields configuration
     */
    protected function getMenuSubfields(): array
    {
        return array(
        array(
            'id' => 'type',
            'type' => 'select',
            'title' => __('Type', WP_EXTENDED_TEXT_DOMAIN),
            'choices' => array(
                'item' => __('Menu Item', WP_EXTENDED_TEXT_DOMAIN),
                'separator' => __('Separator', WP_EXTENDED_TEXT_DOMAIN),
            ),
            'default' => 'item',
            'show_if' => array(
                array(
                    'field' => 'type',
                    'value' => 'IMPOSSIBLE',
                ),
            ),
        ),
        array(
            'id' => 'title',
            'type' => 'text',
            'title' => __('Menu Title', WP_EXTENDED_TEXT_DOMAIN),
            'description' => __(
                'Custom title for this menu item. HTML is allowed for badges and styling.',
                WP_EXTENDED_TEXT_DOMAIN
            ),
            'placeholder' => __(
                'Enter custom title or leave empty for default',
                WP_EXTENDED_TEXT_DOMAIN
            ),
            'allow_html' => true,
            'show_if' => array(
                array(
                    'field' => 'type',
                    'value' => 'item',
                ),
            ),
        ),
        array(
            'id' => 'default_title',
            'type' => 'text',
            'title' => __('Default Title', WP_EXTENDED_TEXT_DOMAIN),
            'show_if' => array(
                array(
                    'field' => 'type',
                    'value' => 'IMPOSSIBLE',
                ),
            ),
        ),
        array(
            'id' => 'menu_slug',
            'type' => 'text',
            'title' => __('Menu Slug', WP_EXTENDED_TEXT_DOMAIN),
            'description' => __(
                'The slug of the menu item. This is used to identify the menu item in the WordPress menu structure.',
                WP_EXTENDED_TEXT_DOMAIN
            ),
            'show_if' => array(
                array(
                    'field' => 'type',
                    'value' => 'IMPOSSIBLE',
                ),
            ),
        ),
        array(
            'id' => 'capability',
            'type' => 'text',
            'title' => __('Required Capability', WP_EXTENDED_TEXT_DOMAIN),
            'description' => __(
                'The WordPress capability required to access this menu item. This is automatically detected from the menu registration.',
                WP_EXTENDED_TEXT_DOMAIN
            ),
            'attributes' => array(
                'readonly' => true,
            ),
            'show_if' => array(
                array(
                    'field' => 'type',
                    'value' => 'item',
                ),
            ),
        ),
        array(
            'id' => 'access_roles',
            'type' => 'checkboxes',
            'title' => __('Grant access to Roles', WP_EXTENDED_TEXT_DOMAIN),
            'description' => __(
                'Select which user roles can see this menu item. Leave all unchecked to hide from everyone except users with explicit access below.',
                WP_EXTENDED_TEXT_DOMAIN
            ),
            'default' => array(),
            'choices' => array(),
            'show_if' => array(
                array(
                    'field' => 'menu_slug',
                    'operator' => '!==',
                    'value' => 'index.php',
                ),
            ),
        ),
        array(
            'id' => 'capability_notice',
            'type' => 'custom',
            'callback' => array($this, 'showCapabilityNotice'),
            'show_if' => array(
                array(
                    'field' => 'menu_slug',
                    'operator' => '!==',
                    'value' => 'index.php',
                ),
            ),
        ),
        array(
            'id' => 'access_users',
            'type' => 'select',
            'title' => __('User-Specific Access', WP_EXTENDED_TEXT_DOMAIN),
            'description' => __(
                'Grant or deny access to specific users, overriding role settings. Leave empty to use role-based access only.',
                WP_EXTENDED_TEXT_DOMAIN
            ),
            'choices' => array(),
            'multiple' => true,
            'placeholder' => __('Search and select users...', WP_EXTENDED_TEXT_DOMAIN),
            'select2' => array(
                'placeholder' => __('Search and select users...', WP_EXTENDED_TEXT_DOMAIN),
                'allowClear' => true,
                'closeOnSelect' => false,
                'tags' => true,
                'tokenSeparators' => array(',', ' '),
            ),
            'show_if' => array(
                array(
                    'field' => 'menu_slug',
                    'operator' => '!==',
                    'value' => 'index.php',
                ),
            ),
        ),
        array(
            'id' => 'user_access_mode',
            'type' => 'radio',
            'title' => __('User Access Mode', WP_EXTENDED_TEXT_DOMAIN),
            'description' => __('How should the selected users be treated?', WP_EXTENDED_TEXT_DOMAIN),
            'choices' => array(
                'grant' => __('Always show to these users (override role restrictions)', WP_EXTENDED_TEXT_DOMAIN),
                'deny' => __('Always hide from these users (override role permissions)', WP_EXTENDED_TEXT_DOMAIN),
            ),
            'default' => 'grant',
            'show_if' => array(
                array(
                    'field' => 'access_users',
                    'operator' => '!==',
                    'value' => '',
                ),
            ),
        ),
        );
    }

    /**
     * Clean menu items before saving
     * Remove duplicates, fix data formats, and ensure required fields
     *
     * @param array $data Data to clean
     * @return array Cleaned data
     */
    public function beforeSave($data)
    {
        if (empty($data['menu_items']) || !is_array($data['menu_items'])) {
            return $data;
        }

        // Intentionally no debug logging here in production builds


        // Load previously saved roles to avoid unintentionally wiping them when the user didn't change them
        $previous_items = \Wpextended\Includes\Utils::getSetting('menu-editor', 'menu_items', array());
        $previous_roles_map = $this->buildAccessRolesLookup($previous_items);

        $cleaned = array();
        $processed_slugs = array(); // Track all processed slugs to prevent duplicates

        // Helper to recursively clean children
        $clean_children = function ($children) use (&$clean_children, &$processed_slugs, $previous_roles_map) {
            if (!is_array($children)) {
                return array();
            }

            $cleaned_children = array();
            foreach ($children as $child) {
                if (!is_array($child)) {
                    continue;
                }

                // Ensure required fields early to make decisions
                $child['type'] = $child['type'] ?? 'item';

                // For non-separator items, skip if slug missing
                if ($child['type'] !== 'separator' && empty($child['menu_slug'])) {
                    continue;
                }

                // Generate a stable slug for separators if missing
                if ($child['type'] === 'separator' && empty($child['menu_slug'])) {
                    $child['menu_slug'] = 'separator-' . uniqid();
                }

                $slug = $child['menu_slug'];

                // Skip if this slug has already been processed
                if (in_array($slug, $processed_slugs)) {
                    continue;
                }

                // Ensure required fields
                if ($child['type'] === 'separator' && (empty($child['title']) || $child['title'] === '{title}')) {
                    $child['title'] = '-- Separator --';
                }
                $child['title'] = $child['title'] ?? '';
                $child['default_title'] = $child['default_title'] ?? $child['title'];
                $child['capability'] = $child['capability'] ?? 'manage_options';

                // Normalize access_roles: convert scalar 0/'0'/'' to empty array, keep arrays
                if (isset($child['access_roles'])) {
                    if (!is_array($child['access_roles'])) {
                        $child['access_roles'] = array();
                    }
                } else {
                    $child['access_roles'] = array();
                }

                // No debug logging for access_roles
                $child['access_users'] = isset($child['access_users']) && is_array($child['access_users']) ? $child['access_users'] : array();
                $child['user_access_mode'] = $child['user_access_mode'] ?? 'grant';

                // If no roles selected, preferably preserve previously saved roles if any; otherwise remove key for fallback
                if (empty($child['access_roles'])) {
                    $slug = isset($child['menu_slug']) ? $child['menu_slug'] : '';
                    if ($slug && isset($previous_roles_map[$slug]) && is_array($previous_roles_map[$slug]) && !empty($previous_roles_map[$slug])) {
                        $child['access_roles'] = $previous_roles_map[$slug];
                    } else {
                        unset($child['access_roles']);
                    }
                }

                // Clean children recursively
                $child['children'] = $clean_children($child['children'] ?? array());

                $cleaned_children[] = $child;
                $processed_slugs[] = $slug;
            }

            return $cleaned_children;
        };

        // Clean parent items
        foreach ($data['menu_items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Ensure required fields early
            $item['type'] = $item['type'] ?? 'item';

            // For non-separator items, skip if slug missing
            if ($item['type'] !== 'separator' && empty($item['menu_slug'])) {
                continue;
            }

            // Generate a stable slug for separators if missing
            if ($item['type'] === 'separator' && empty($item['menu_slug'])) {
                $item['menu_slug'] = 'separator-' . uniqid();
            }

            $slug = $item['menu_slug'];

            // Skip if this slug has already been processed
            if (in_array($slug, $processed_slugs)) {
                continue;
            }

            // Ensure required fields
            if ($item['type'] === 'separator' && (empty($item['title']) || $item['title'] === '{title}')) {
                $item['title'] = '-- Separator --';
            }
            $item['title'] = $item['title'] ?? '';
            $item['default_title'] = $item['default_title'] ?? $item['title'];
            $item['capability'] = $item['capability'] ?? 'manage_options';

            // Normalize access_roles for parent: convert scalar to empty array, keep arrays
            if (isset($item['access_roles'])) {
                if (!is_array($item['access_roles'])) {
                    $item['access_roles'] = array();
                }
            } else {
                $item['access_roles'] = array();
            }

            // No debug logging for access_roles
            $item['access_users'] = isset($item['access_users']) && is_array($item['access_users']) ? $item['access_users'] : array();
            $item['user_access_mode'] = $item['user_access_mode'] ?? 'grant';

            // If no roles selected, preferably preserve previously saved roles if any; otherwise remove key for fallback
            if (empty($item['access_roles'])) {
                $slug = isset($item['menu_slug']) ? $item['menu_slug'] : '';
                if ($slug && isset($previous_roles_map[$slug]) && is_array($previous_roles_map[$slug]) && !empty($previous_roles_map[$slug])) {
                    $item['access_roles'] = $previous_roles_map[$slug];
                } else {
                    unset($item['access_roles']);
                }
            }

            // Clean children
            $item['children'] = $clean_children($item['children'] ?? array());

            // If separator has children, flatten children to top-level but keep the separator (with children emptied)
            $extracted_children = array();
            if ($item['type'] === 'separator' && !empty($item['children'])) {
                $extracted_children = $item['children'];
                $item['children'] = array();
            }

            // Always keep the item itself
            $cleaned[] = $item;
            $processed_slugs[] = $slug;

            // Append extracted children after the separator
            if (!empty($extracted_children)) {
                foreach ($extracted_children as $child) {
                    if (!in_array($child['menu_slug'], $processed_slugs)) {
                        $cleaned[] = $child;
                        $processed_slugs[] = $child['menu_slug'];
                    }
                }
            }
        }

        $data['menu_items'] = $cleaned;



        return $data;
    }

    /**
     * Build a lookup of menu_slug => access_roles from previously saved items (including children)
     *
     * @param array $items
     * @return array
     */
    protected function buildAccessRolesLookup(array $items): array
    {
        $map = array();
        $walk = function ($nodes) use (&$walk, &$map) {
            if (!is_array($nodes)) {
                return;
            }
            foreach ($nodes as $n) {
                if (!is_array($n)) {
                    continue;
                }
                $slug = isset($n['menu_slug']) ? $n['menu_slug'] : '';
                if ($slug && isset($n['access_roles']) && is_array($n['access_roles']) && !empty($n['access_roles'])) {
                    $map[$slug] = $n['access_roles'];
                }
                if (!empty($n['children']) && is_array($n['children'])) {
                    $walk($n['children']);
                }
            }
        };
        $walk($items);
        return $map;
    }

    /**
     * Add field arguments for dynamic choices and default values
     *
     * @param array $args Field arguments
     * @param array $field Field data
     * @param array $menu_structure Menu structure for default values
     * @return array Field arguments
     */
    public function addFieldArgs(array $args, array $field, array $menu_structure = array()): array
    {
        // Only set default for the menu_items field
        if ($field['id'] === 'menu_items') {
            // Filter out WP Extended items that appear as root items (duplicates)
            $filtered_structure = $this->filterWpextendedRootItems($menu_structure);

            // Provide defaults only. Let the framework use the saved value if present.
            $args['default'] = $filtered_structure;
        }

        // Update role choices in subfields
        if (isset($args['subfields']) && is_array($args['subfields'])) {
            foreach ($args['subfields'] as &$subfield) {
                if ($subfield['id'] === 'access_roles') {
                    $subfield['choices'] = $this->accessManager->getUserRolesChoices();
                    $subfield['default'] = array(); // Empty by default
                } elseif ($subfield['id'] === 'access_users') {
                    $subfield['choices'] = $this->accessManager->getUserChoices();
                }
            }
        }

        return $args;
    }

    /**
     * Filter out WP Extended items that appear as root items (duplicates)
     * These items should only appear as submenu items under the WP Extended parent
     *
     * @param array $menu_structure Menu structure to filter
     * @return array Filtered menu structure
     */
    private function filterWpextendedRootItems(array $menu_structure): array
    {
        $filtered = array();

        foreach ($menu_structure as $item) {
            if (!isset($item['menu_slug'])) {
                $filtered[] = $item;
                continue;
            }

            $menu_slug = $item['menu_slug'];

            // Skip WP Extended items that appear as root items (except wpextended-settings)
            if (strpos($menu_slug, 'wpextended-') === 0 && $menu_slug !== 'wpextended-settings') {
                continue;
            }

            // Skip post menu item if disable-blog module is enabled
            if ($this->isDisableBlogEnabled() && $menu_slug === 'edit.php') {
                continue;
            }

            // Recursively filter children
            if (!empty($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterWpextendedRootItems($item['children']);
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Check if Disable Blog module is enabled
     *
     * @return bool
     */
    private function isDisableBlogEnabled(): bool
    {
        return \Wpextended\Includes\Modules::isModuleEnabled('disable-blog');
    }

    /**
     * Show capability notice
     *
     * @param mixed $value Current field value (optional)
     * @param array $field Field configuration (optional)
     * @param array $item Current item data (optional)
     */
    public function showCapabilityNotice()
    {
        ?>
            <div class="notice notice-info inline" style="margin: 10px 0;">
                <p style="margin-block: .5em;">
                    <?php echo esc_html__(
                        'Users who do not have the required capability will be granted it to access this menu item.',
                        WP_EXTENDED_TEXT_DOMAIN
                    ); ?>
                </p>
            </div>
        <?php
    }
}
