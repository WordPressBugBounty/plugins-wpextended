<?php

namespace Wpextended\Modules\PostTypeOrder;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * Post Type Order Module
 *
 * Provides drag-and-drop reordering functionality for posts and pages.
 * Free version is limited to posts and pages only.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct("post-type-order");
    }

    /**
     * Initialize the module
     */
    protected function init()
    {
        add_action("wp_insert_post", [$this, "handlePostCreated"], 10, 3);
        add_action("admin_enqueue_scripts", [$this, "enqueueScripts"]);
        add_action("rest_api_init", [$this, "registerRestRoutes"]);
        add_action("current_screen", [$this, "handlePostListRedirect"]);
    }

    /**
     * Enqueue scripts and styles for admin interface
     */
    public function enqueueScripts()
    {
        $screen = get_current_screen();

        if (!$screen || !$this->isEnabledPostType($screen->post_type)) {
            return;
        }

        Utils::enqueueNotify();
        Utils::enqueueStyle(
            "wpext-post-order",
            $this->getPath("assets/css/style.css")
        );
        Utils::enqueueScript(
            "wpext-sortable",
            "includes/framework/assets/lib/sortable/sortable.min.js"
        );
        Utils::enqueueScript(
            "wpext-post-order",
            $this->getPath("assets/js/script.js"),
            ["wpext-notify", "wpext-sortable"]
        );

        $this->localizeScript();
    }

    /**
     * Localize script data for JavaScript
     */
    protected function localizeScript()
    {
        $localizeData = [
            "restUrl" => rest_url(WP_EXTENDED_API_NAMESPACE),
            "restNonce" => wp_create_nonce("wp_rest"),
            "security" => wp_create_nonce("wpext_post_order_security"),
            "i18n" => [
                "order_updated" => __(
                    "%s order updated",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
                "error_item" => __(
                    "Error updating post %s: %s",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
                "no_items_found" => __(
                    "Failed to prepare items for reordering. No valid items found.",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
                "update_failed" => __(
                    "Failed to update post order. Please try again.",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
                "items_fallback" => __("Items", WP_EXTENDED_TEXT_DOMAIN),
                "error_prefix" => __("Error: ", WP_EXTENDED_TEXT_DOMAIN),
            ],
        ];

        wp_localize_script("wpext-post-order", "wpextPostOrder", $localizeData);
        wp_enqueue_script("wp-api");
    }

    /**
     * Handle post list page redirects to show ordered view
     */
    public function handlePostListRedirect()
    {
        $screen = get_current_screen();

        if (
            !$screen ||
            $screen->base !== "edit" ||
            !$this->isEnabledPostType($screen->post_type)
        ) {
            return;
        }

        // Don't redirect pages as they have natural ordering
        if ($screen->post_type === "page") {
            return;
        }

        if (!isset($_REQUEST["orderby"])) {
            $this->redirectToOrderedView();
            return;
        }

        if ($_REQUEST["orderby"] === "menu_order") {
            $this->normalizeMenuOrder($screen->post_type);
        }
    }

    /**
     * Redirect to ordered view with menu_order sorting
     */
    protected function redirectToOrderedView()
    {
        $currentUrl = $_SERVER["REQUEST_URI"];
        $parsedUrl = parse_url($currentUrl);

        $query = $parsedUrl["query"] ?? "";
        $orderParams = http_build_query([
            "orderby" => "menu_order",
            "order" => "asc",
        ]);

        $newQuery = $query ? $query . "&" . $orderParams : $orderParams;
        $redirectUrl = $parsedUrl["path"] . "?" . $newQuery;

        wp_redirect($redirectUrl, 302, "WP Extended: Post Type Order");
        exit();
    }

    /**
     * Normalize menu order values to sequential numbers
     *
     * @param string $postType The post type to normalize
     */
    protected function normalizeMenuOrder($postType)
    {
        if (!$this->isEnabledPostType($postType)) {
            return;
        }

        global $wpdb;

        $duplicates = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$wpdb->posts}
       WHERE post_type = %s
       AND post_status IN ('publish', 'pending', 'draft', 'future', 'private')
       AND menu_order = 1",
                $postType
            )
        );

        if ($duplicates) {
            $this->renumberPostOrder($postType);
        }
    }

    /**
     * Renumber posts with sequential menu_order values
     *
     * @param string $postType The post type to renumber
     */
    protected function renumberPostOrder($postType)
    {
        $page = 1;
        $counter = 1;
        $postsPerPage = 100;

        while (
            $posts = $this->getPostsForRenumbering(
                $postType,
                $page,
                $postsPerPage
            )
        ) {
            foreach ($posts as $postId) {
                wp_update_post([
                    "ID" => $postId,
                    "menu_order" => $counter,
                ]);
                $counter++;
            }
            $page++;
        }
    }

    /**
     * Get posts for renumbering in batches
     *
     * @param string $postType The post type
     * @param int $page The page number
     * @param int $postsPerPage Number of posts per page
     * @return array Array of post IDs
     */
    protected function getPostsForRenumbering($postType, $page, $postsPerPage)
    {
        return get_posts([
            "post_type" => $postType,
            "post_status" => [
                "publish",
                "pending",
                "draft",
                "future",
                "private",
            ],
            "fields" => "ids",
            "orderby" => "menu_order",
            "order" => "ASC",
            "numberposts" => $postsPerPage,
            "paged" => $page,
        ]);
    }

    /**
     * Handle new post creation by setting appropriate menu_order
     *
     * @param int $postId The post ID
     * @param \WP_Post $post The post object
     * @param bool $update Whether this is an update
     */
    public function handlePostCreated($postId, $post, $update)
    {
        if (
            $update ||
            !$this->isEnabledPostType($post->post_type) ||
            $post->menu_order !== 0
        ) {
            return;
        }

        $maxOrder = $this->getMaxMenuOrder($post->post_type);

        wp_update_post(
            [
                "ID" => $postId,
                "menu_order" => $maxOrder + 1,
            ],
            false,
            false
        );
    }

    /**
     * Get the maximum menu_order value for a post type
     *
     * @param string $postType The post type
     * @return int The maximum menu_order value
     */
    protected function getMaxMenuOrder($postType)
    {
        $posts = get_posts([
            "post_type" => $postType,
            "post_status" => [
                "publish",
                "pending",
                "draft",
                "future",
                "private",
            ],
            "numberposts" => 1,
            "orderby" => "menu_order",
            "order" => "DESC",
            "fields" => "ids",
        ]);

        if (empty($posts)) {
            return 0;
        }

        $topPost = get_post($posts[0]);
        return $topPost ? $topPost->menu_order : 0;
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes()
    {
        register_rest_route(WP_EXTENDED_API_NAMESPACE, "/post-order/reorder", [
            "methods" => "POST",
            "callback" => [$this, "reorderPosts"],
            "permission_callback" => [$this, "checkPermissions"],
            "args" => [
                "items" => [
                    "required" => true,
                    "type" => "array",
                    "validate_callback" => [$this, "validateItems"],
                ],
            ],
        ]);
    }

    /**
     * Check permissions for reorder operations
     *
     * @return bool True if user has permissions
     */
    public function checkPermissions()
    {
        return current_user_can("edit_others_posts") &&
            wp_verify_nonce($_SERVER["HTTP_X_WP_NONCE"] ?? "", "wp_rest");
    }

    /**
     * Validate reorder items data
     *
     * @param array $items The items to validate
     * @return bool|\WP_Error True if valid, WP_Error otherwise
     */
    public function validateItems($items)
    {
        if (!is_array($items) || empty($items)) {
            return new \WP_Error(
                "invalid_items",
                __("Items must be a non-empty array", WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        foreach ($items as $item) {
            if (!isset($item["id"]) || !isset($item["order"])) {
                return new \WP_Error(
                    "invalid_item_structure",
                    __(
                        "Each item must have id and order",
                        WP_EXTENDED_TEXT_DOMAIN
                    )
                );
            }

            if (!is_numeric($item["id"]) || !is_numeric($item["order"])) {
                return new \WP_Error(
                    "invalid_item_values",
                    __("ID and order must be numeric", WP_EXTENDED_TEXT_DOMAIN)
                );
            }
        }

        return true;
    }

    /**
     * Handle POST reorder request
     *
     * @param \WP_REST_Request $request The REST request
     * @return \WP_REST_Response The response
     */
    public function reorderPosts($request)
    {
        try {
            $items = $request->get_param("items");
            $results = $this->processReorderItems($items);

            return rest_ensure_response([
                "success" => true,
                "data" => $results,
            ]);
        } catch (\Exception $e) {
            return new \WP_Error("reorder_failed", $e->getMessage(), [
                "status" => 400,
            ]);
        }
    }

    /**
     * Process reorder items and update database
     *
     * @param array $items The items to reorder
     * @return array Processing results
     * @throws \Exception If processing fails
     */
    protected function processReorderItems($items)
    {
        $results = [
            "updated" => [],
            "errors" => [],
            "post_type" => null,
            "post_type_label" => null,
        ];

        // Validate and collect post data
        $postsData = $this->validateAndCollectPostData($items, $results);

        // For hierarchical post types, validate using actual DOM positions instead of menu_order
        if ($this->isHierarchicalPostType($results["post_type"])) {
            $this->validateHierarchicalOrder($postsData);
        }

        // Process updates
        $this->processUpdates($postsData, $results);

        return $results;
    }

    /**
     * Validate and collect post data from items
     *
     * @param array $items The items to process
     * @param array &$results Results array to populate
     * @return array Array of post data
     * @throws \Exception If validation fails
     */
    protected function validateAndCollectPostData($items, &$results)
    {
        $postsData = [];
        $postType = null;

        foreach ($items as $item) {
            $post = get_post($item["id"]);

            if (!$post) {
                throw new \Exception("Post {$item["id"]} not found");
            }

            if (!$this->isEnabledPostType($post->post_type)) {
                throw new \Exception(
                    "Post type {$post->post_type} not supported"
                );
            }

            // Ensure all posts are of the same type
            if ($postType === null) {
                $postType = $post->post_type;
                $postTypeObject = get_post_type_object($postType);
                $results["post_type"] = $postType;
                $results["post_type_label"] = $postTypeObject
                    ? $postTypeObject->labels->singular_name
                    : $postType;
            } elseif ($postType !== $post->post_type) {
                throw new \Exception(
                    "Cannot reorder posts of different types together"
                );
            }

            $postsData[] = [
                "post" => $post,
                "new_order" => $item["order"],
                "item" => $item,
            ];
        }

        return $postsData;
    }

    /**
     * Process the actual updates
     *
     * @param array $postsData Array of post data
     * @param array &$results Results array to populate
     */
    protected function processUpdates($postsData, &$results)
    {
        foreach ($postsData as $postData) {
            try {
                $post = $postData["post"];
                $item = $postData["item"];

                if ($post->menu_order == $item["order"]) {
                    continue; // No change needed
                }

                $updated = wp_update_post([
                    "ID" => $item["id"],
                    "menu_order" => $item["order"],
                ]);

                if (is_wp_error($updated)) {
                    throw new \Exception(
                        sprintf(
                            /* translators: %s: post id, %s: error message */
                            __(
                                "Failed to update post %s: %s",
                                WP_EXTENDED_TEXT_DOMAIN
                            ),
                            $item["id"],
                            $updated->get_error_message()
                        )
                    );
                }

                $results["updated"][] = $item["id"];
            } catch (\Exception $e) {
                $results["errors"][] = [
                    "id" => $postData["item"]["id"],
                    "message" => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Check if post type is enabled for ordering
     *
     * @param string $postType The post type to check
     * @return bool True if enabled
     */
    protected function isEnabledPostType($postType)
    {
        $postTypes = $this->getSetting("post-types", []);

        if (empty($postTypes)) {
            return false;
        }

        return in_array($postType, $postTypes);
    }

    /**
     * Check if post type is hierarchical
     *
     * @param string $postType The post type to check
     * @return bool True if hierarchical
     */
    protected function isHierarchicalPostType($postType)
    {
        return is_post_type_hierarchical($postType);
    }

    /**
     * Validate hierarchical order based on actual drag operation constraints
     *
     * @param array $postsData Array of post data with new orders
     * @throws \Exception If hierarchy constraints are violated
     */
    protected function validateHierarchicalOrder($postsData)
    {
        // Create a map of post ID to new position
        $newPositions = [];
        foreach ($postsData as $index => $postData) {
            $newPositions[$postData["post"]->ID] = $index;
        }

        // Validate parent-child relationships
        foreach ($postsData as $index => $postData) {
            $post = $postData["post"];
            $parentId = intval($post->post_parent);

            // Skip if not a child post
            if ($parentId <= 0) {
                continue;
            }

            // Skip if parent not in reorder list
            if (!isset($newPositions[$parentId])) {
                continue;
            }

            $parentPosition = $newPositions[$parentId];

            // Child must come after parent in the new order
            if ($index <= $parentPosition) {
                throw new \Exception(
                    __(
                        "Invalid hierarchy: Child items cannot appear before their parent items",
                        WP_EXTENDED_TEXT_DOMAIN
                    )
                );
            }
        }
    }

    /**
     * Get module settings fields
     *
     * @return array The settings configuration
     */
    protected function getSettingsFields()
    {
        $settings = [];

        $settings["tabs"][] = [
            "id" => "settings",
            "title" => __("Post Type Order", WP_EXTENDED_TEXT_DOMAIN),
        ];

        $settings["sections"] = [
            [
                "tab_id" => "settings",
                "section_id" => "settings",
                "section_title" => __("Settings", WP_EXTENDED_TEXT_DOMAIN),
                "section_order" => 10,
                "fields" => [
                    [
                        "id" => "post-types",
                        "type" => "checkboxes",
                        "title" => __("Post Types", WP_EXTENDED_TEXT_DOMAIN),
                        "description" => __(
                            "Select post types to enable ordering for.",
                            WP_EXTENDED_TEXT_DOMAIN
                        ),
                        "choices" => $this->getPostTypes(),
                    ],
                ],
            ],
        ];

        return $settings;
    }

    /**
     * Get available post types for free version
     *
     * @return array Available post types
     */
    protected function getPostTypes()
    {
        $postTypes = [
            "post" => __("Posts", WP_EXTENDED_TEXT_DOMAIN),
            "page" => __("Pages", WP_EXTENDED_TEXT_DOMAIN),
        ];

        return apply_filters("wpextended/post-order/post_types", $postTypes);
    }
}
