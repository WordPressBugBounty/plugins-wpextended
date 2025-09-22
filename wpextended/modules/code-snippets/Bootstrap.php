<?php

namespace Wpextended\Modules\CodeSnippets;

use Wpextended\Includes\Utils;
use Wpextended\Modules\BaseModule;
use Wpextended\Modules\CodeSnippets\Includes\SnippetManager;
use Wpextended\Modules\CodeSnippets\Includes\SnippetExecutor;
use Wpextended\Modules\CodeSnippets\Rest\Controller as RestController;

/**
 * Bootstrap for the Code Snippets module.
 *
 * Provides settings UI, REST endpoints and runtime execution for user-defined
 * PHP/HTML/CSS/JS snippets.
 *
 * @since 3.1.0
 */
class Bootstrap extends BaseModule
{
    /**
     * Construct the module and register its identifier.
     *
     * @since 3.1.0
     */
    public function __construct()
    {
        parent::__construct('code-snippets');
    }

    /**
     * Initialize the module.
     *
     * Registers settings/UI hooks, REST routes, and the snippet executor.
     *
     * @hook filter wpextended/code-snippets/dependencies
     * @hook filter wpextended/code-snippets/register_settings
     * @hook action admin_enqueue_scripts
     *
     * @since 3.1.0
     * @return void
     */
    protected function init(): void
    {
        add_filter('wpextended/code-snippets/dependencies', array($this, 'dependencies'));
        add_filter('wpextended/code-snippets/show_save_changes_button', '__return_false');
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));

        new RestController();

        if (!$this->shouldSkipSnippetExecution()) {
            new SnippetExecutor();
        }
    }

    /**
     * Determine whether snippet execution should be skipped for the current request.
     *
     * Skips during REST, AJAX and CRON contexts to avoid unintended output.
     *
     * @since 3.1.0
     * @return bool True when execution should be skipped.
     */
    /**
     * Determine whether snippet execution should be skipped for the current request.
     *
     * Skips during REST, AJAX and CRON contexts to avoid unintended output.
     *
     * @since 3.1.0
     * @return bool True when execution should be skipped.
     */
    private function shouldSkipSnippetExecution()
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }
        return false;
    }

    /**
     * Get the snippet id from the request (if present).
     *
     * @since 3.1.0
     * @return string|null Sanitized snippet id or null when not present.
     */
    /**
     * Get the snippet id from the request (if present).
     *
     * @since 3.1.0
     * @return string|null Sanitized snippet id or null when not present.
     */
    private function getSnippetId()
    {
        $snippetId = isset($_GET['snippet']) ? sanitize_text_field($_GET['snippet']) : null;
        return $snippetId;
    }

    /**
     * Get the current view for the settings UI.
     *
     * @since 3.1.0
     * @return string One of: 'list', 'new', 'edit'.
     */
    /**
     * Get the current view for the settings UI.
     *
     * @since 3.1.0
     * @return string One of: 'list', 'new', 'edit'.
     */
    private function getCurrentView()
    {
        $snippetId = $this->getSnippetId();
        if (!$snippetId) {
            return 'list';
        }
        return $snippetId === 'new' ? 'new' : 'edit';
    }

    /**
     * Get the currently edited snippet (edit view only).
     *
     * @since 3.1.0
     * @return \Wpextended\Modules\CodeSnippets\Includes\Snippet|null The snippet instance or null.
     */
    /**
     * Get the currently edited snippet (edit view only).
     *
     * @since 3.1.0
     * @return \Wpextended\Modules\CodeSnippets\Includes\Snippet|null The snippet instance or null.
     */
    private function getCurrentSnippet()
    {
        if (!current_user_can('manage_options')) {
            return null;
        }
        if ($this->getCurrentView() !== 'edit') {
            return null;
        }
        $snippetId = $this->getSnippetId();
        if (!$snippetId) {
            return null;
        }
        $snippetManager = new SnippetManager();
        return $snippetManager->getAdminSnippet($snippetId);
    }

    /**
     * Provide dependency notices for the module UI.
     *
     * Outputs an informational notice when there are no snippets yet (list view only).
     *
     * @hook filter wpextended/code-snippets/dependencies
     * @since 3.1.0
     *
     * @return array List of dependency messages (empty when not applicable).
     */
    /**
     * Provide dependency notices for the module UI.
     *
     * Outputs an informational notice when there are no snippets yet (list view only).
     *
     * @hook filter wpextended/code-snippets/dependencies
     * @since 3.1.0
     *
     * @return array List of dependency messages (empty when not applicable).
     */
    public function dependencies()
    {
        if (!current_user_can('manage_options')) {
            return [];
        }
        if ($this->getCurrentView() !== 'list') {
            return [];
        }
        $snippetManager = new SnippetManager();
        $snippets = $snippetManager->getAdminSnippets();
        if (!empty($snippets)) {
            return [];
        }
        return array(
            array(
                'type'    => 'info',
                'message' => sprintf(
                    /* translators: %s: add snippet link */
                    __('No snippets have been created yet. Please <a href="%s">create your first snippet</a>.', WP_EXTENDED_TEXT_DOMAIN),
                    add_query_arg('snippet', 'new')
                ),
            )
        );
    }

    /**
     * Build settings fields and sections for the module settings UI.
     *
     * @hook filter wpextended/code-snippets/register_settings
     * @since 3.1.0
     *
     * @return array Settings array consumed by the framework.
     */
    /**
     * Build settings fields and sections for the module settings UI.
     *
     * @hook filter wpextended/code-snippets/register_settings
     * @since 3.1.0
     *
     * @return array Settings array consumed by the framework.
     */
    protected function getSettingsFields()
    {
        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('Code Snippets', WP_EXTENDED_TEXT_DOMAIN),
        );

        $currentView = $this->getCurrentView();
        if ($currentView === 'list') {
            $settings['sections'][] = $this->getListSection();
            return $settings;
        }

        $settings['sections'][] = $this->getEditorSection();
        return $settings;
    }

    /**
     * Settings: List view section definition.
     *
     * @since 3.1.0
     * @return array Section array.
     */
    /**
     * Settings: List view section definition.
     *
     * @since 3.1.0
     * @return array Section array.
     */
    private function getListSection()
    {
        return array(
            'tab_id' => 'settings',
            'section_id'    => 'settings',
            'section_title' => '',
            'fields' => array(
                array(
                    'id' => 'code_snippets',
                    'type' => 'table',
                    'title' => __('Code Snippets', WP_EXTENDED_TEXT_DOMAIN),
                    'table_config' => array(
                        'endpoint' => rest_url(WP_EXTENDED_API_NAMESPACE . '/code-snippets/snippets'),
                        'columns' => [
                            array(
                                'id' => 'id',
                                'name' => __('ID', WP_EXTENDED_TEXT_DOMAIN),
                                'sort' => true,
                                'hidden' => true,
                            ),
                            array(
                                'id' => 'name',
                                'name' => __('Name', WP_EXTENDED_TEXT_DOMAIN),
                                'sort' => true,
                            ),
                            array(
                                'id' => 'type',
                                'name' => __('Type', WP_EXTENDED_TEXT_DOMAIN),
                                'width' => '80px',
                                'sort' => true
                            ),
                            array(
                                'id' => 'enabled',
                                'name' => __('Status', WP_EXTENDED_TEXT_DOMAIN),
                                'sort' => true,
                                'width' => '70px',
                                'formatter' => 'snippetStatusFormatter'
                            ),
                            array(
                                'id' => 'actions',
                                'name' => __('Actions', WP_EXTENDED_TEXT_DOMAIN),
                                'sort' => false,
                                'width' => '100px',
                                'formatter' => 'snippetActionsFormatter'
                            )
                        ],
                        'per_page' => 10,
                        'search' => true,
                        'sort' => true,
                        'pagination' => true
                    ),
                ),
                array(
                    'id' => 'actions',
                    'type' => 'button',
                    'title' => __('Add Snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'style' => 'primary',
                    'tag' => 'a',
                    'attributes' => array(
                        'href' => add_query_arg('snippet', 'new')
                    )
                ),
            ),
        );
    }

    /**
     * Settings: Editor view section definition.
     *
     * @since 3.1.0
     * @return array Section array.
     */
    /**
     * Settings: Editor view section definition.
     *
     * @since 3.1.0
     * @return array Section array.
     */
    private function getEditorSection()
    {
        $snippet = $this->getCurrentSnippet();
        return array(
            'tab_id' => 'settings',
            'section_id' => 'snippet',
            'section_title' => '',
            'fields' => array(
                array(
                    'id' => 'title',
                    'type' => 'text',
                    'title' => __('Snippet Name', WP_EXTENDED_TEXT_DOMAIN),
                    'required' => true,
                    'placeholder' => __('Enter snippet name', WP_EXTENDED_TEXT_DOMAIN),
                    'default' => $snippet ? $snippet->getName() : ''
                ),
                array(
                    'id' => 'description',
                    'type' => 'textarea',
                    'title' => __('Description', WP_EXTENDED_TEXT_DOMAIN),
                    'placeholder' => __('Enter snippet description', WP_EXTENDED_TEXT_DOMAIN),
                    'attributes' => array(
                        'rows' => 2
                    ),
                    'default' => $snippet ? $snippet->getDescription() : ''
                ),
                array(
                    'id' => 'type',
                    'type' => 'select',
                    'title' => __('Snippet Type', WP_EXTENDED_TEXT_DOMAIN),
                    'required' => true,
                    'choices' => array(
                        'php' => __('PHP', WP_EXTENDED_TEXT_DOMAIN),
                        'html' => __('HTML', WP_EXTENDED_TEXT_DOMAIN),
                        'css' => __('CSS', WP_EXTENDED_TEXT_DOMAIN),
                        'js' => __('JavaScript', WP_EXTENDED_TEXT_DOMAIN)
                    ),
                    'default' => $snippet ? $snippet->getType() : 'php'
                ),
                array(
                    'id' => 'code',
                    'type' => 'code_editor',
                    'title' => __('Snippet Code', WP_EXTENDED_TEXT_DOMAIN),
                    'required' => true,
                    'mimetype' => $this->getMimeType($snippet ? $snippet->getType() : 'php'),
                    'default' => $snippet ? $snippet->getCode() : ''
                ),
                array(
                    'id' => 'css_run_location',
                    'type' => 'select',
                    'title' => __('Where to Run', WP_EXTENDED_TEXT_DOMAIN),
                    'choices' => array(
                        'everywhere' => __('Everywhere (Frontend & Admin)', WP_EXTENDED_TEXT_DOMAIN),
                        'site_header' => __('Frontend Header', WP_EXTENDED_TEXT_DOMAIN),
                        'site_footer' => __('Frontend Footer', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_header' => __('Admin Header', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_footer' => __('Admin Footer', WP_EXTENDED_TEXT_DOMAIN)
                    ),
                    'attributes' => array(
                        'data-no-animation' => true
                    ),
                    'show_if'  => array(
                        array(
                            'field' => 'type',
                            'value' => 'css'
                        ),
                    ),
                    'default' => $snippet ? $snippet->getRunLocation() : 'everywhere'
                ),
                array(
                    'id' => 'php_run_location',
                    'type' => 'select',
                    'title' => __('Where to Run', WP_EXTENDED_TEXT_DOMAIN),
                    'choices' => array(
                        'everywhere' => __('Everywhere (Frontend & Admin)', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_only' => __('Admin Only', WP_EXTENDED_TEXT_DOMAIN),
                        'frontend_only' => __('Frontend Only', WP_EXTENDED_TEXT_DOMAIN)
                    ),
                    'attributes' => array(
                        'data-no-animation' => true
                    ),
                    'show_if' => array(
                        array(
                            'field' => 'type',
                            'value' => 'php'
                        ),
                    ),
                    'default' => $snippet ? $snippet->getRunLocation() : 'everywhere'
                ),
                array(
                    'id' => 'html_run_location',
                    'type' => 'select',
                    'title' => __('Where to Run', WP_EXTENDED_TEXT_DOMAIN),
                    'choices' => array(
                        'everywhere' => __('Everywhere (Frontend & Admin)', WP_EXTENDED_TEXT_DOMAIN),
                        'site_header' => __('Frontend Header', WP_EXTENDED_TEXT_DOMAIN),
                        'site_body_open' => __('Frontend Body Open', WP_EXTENDED_TEXT_DOMAIN),
                        'site_footer' => __('Frontend Footer', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_header' => __('Admin Header', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_footer' => __('Admin Footer', WP_EXTENDED_TEXT_DOMAIN),
                    ),
                    'attributes' => array(
                        'data-no-animation' => true
                    ),
                    'show_if' => array(
                        array(
                            'field' => 'type',
                            'value' => 'html'
                        ),
                    ),
                    'default' => $snippet ? $snippet->getRunLocation() : 'everywhere'
                ),
                array(
                    'id' => 'js_run_location',
                    'type' => 'select',
                    'title' => __('Where to Run', WP_EXTENDED_TEXT_DOMAIN),
                    'choices' => array(
                        'everywhere' => __('Everywhere (Frontend & Admin)', WP_EXTENDED_TEXT_DOMAIN),
                        'site_header' => __('Frontend Header', WP_EXTENDED_TEXT_DOMAIN),
                        'site_footer' => __('Frontend Footer', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_header' => __('Admin Header', WP_EXTENDED_TEXT_DOMAIN),
                        'admin_footer' => __('Admin Footer', WP_EXTENDED_TEXT_DOMAIN)
                    ),
                    'attributes' => array(
                        'data-no-animation' => true
                    ),
                    'show_if' => array(
                        array(
                            'field' => 'type',
                            'value' => 'js'
                        ),
                    ),
                    'default' => $snippet ? $snippet->getRunLocation() : 'everywhere'
                ),
                array(
                    'id' => 'enabled_snippet',
                    'type' => 'toggle',
                    'title' => __('Enable Snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'default' => $snippet ? $snippet->isEnabled() : false
                ),
                array(
                    'id' => 'actions',
                    'type' => 'buttons',
                    'title' => __('Actions', WP_EXTENDED_TEXT_DOMAIN),
                    'buttons' => array(
                        array(
                            'id' => 'save',
                            'title' => $this->getCurrentView() === 'new'
                                ? __('Save Snippet', WP_EXTENDED_TEXT_DOMAIN)
                                : __('Update Snippet', WP_EXTENDED_TEXT_DOMAIN),
                            'type' => 'button',
                            'style' => 'primary'
                        ),
                        array(
                            'id' => 'back',
                            'title' => __('Back to List', WP_EXTENDED_TEXT_DOMAIN),
                            'type' => 'button',
                            'tag' => 'a',
                            'style' => 'ghost',
                            'attributes' => array(
                                'href' => Utils::getModulePageLink('code-snippets')
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Resolve the MIME type for the code editor based on snippet type.
     *
     * @since 3.1.0
     * @param string $type Snippet type (php|js|css|html).
     * @return string MIME type string for the editor.
     */
    /**
     * Resolve the MIME type for the code editor based on snippet type.
     *
     * @since 3.1.0
     * @param string $type Snippet type (php|js|css|html).
     * @return string MIME type string for the editor.
     */
    public function getMimeType($type)
    {
        $mimeTypeMap = [
            'php' => 'application/x-httpd-php-open',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
        ];
        return $mimeTypeMap[$type] ?? 'application/x-httpd-php-open';
    }

    /**
     * Enqueue admin assets for the module screen.
     *
     * @hook action admin_enqueue_scripts
     * @since 3.1.0
     * @return void
     */
    /**
     * Enqueue admin assets for the module screen.
     *
     * @hook action admin_enqueue_scripts
     * @since 3.1.0
     * @return void
     */
    public function enqueueAssets()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!Utils::isPluginScreen('code-snippets')) {
            return;
        }

        Utils::enqueueNotify();

        Utils::enqueueScript(
            'wpext-code-snippets',
            $this->getPath('assets/js/script.js'),
            array('wpext-notify'),
        );

        wp_localize_script(
            'wpext-code-snippets',
            'wpextCodeSnippets',
            array(
                'restUrl' => rest_url(WP_EXTENDED_API_NAMESPACE),
                'nonce' => wp_create_nonce('wp_rest'),
                'currentView' => $this->getCurrentView(),
                'listViewUrl' => Utils::getModulePageLink('code-snippets'),
                'isNew' => $this->getCurrentView() === 'new',
                'snippetId' => $this->getCurrentSnippet() ? $this->getCurrentSnippet()->getId() : null,
                'i18n' => array(
                    'snippetNotFound' => __('Snippet not found', WP_EXTENDED_TEXT_DOMAIN),
                    'nameRequired' => __('Please enter a snippet name', WP_EXTENDED_TEXT_DOMAIN),
                    'codeRequired' => __('Please enter snippet code', WP_EXTENDED_TEXT_DOMAIN),
                    'saving' => __('Saving...', WP_EXTENDED_TEXT_DOMAIN),
                    'saveSnippet' => __('Save Snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'updateSnippet' => __('Update Snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'createSnippetSuccess' => __('Snippet created successfully', WP_EXTENDED_TEXT_DOMAIN),
                    'updateSnippetSuccess' => __('Snippet updated successfully', WP_EXTENDED_TEXT_DOMAIN),
                    'saveSnippetError' => __('Failed to save snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'deleteSnippet' => __('Are you sure you want to delete this snippet?', WP_EXTENDED_TEXT_DOMAIN),
                    'deleteSnippetSuccess' => __('Snippet deleted successfully', WP_EXTENDED_TEXT_DOMAIN),
                    'deleteSnippetError' => __('Failed to delete snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'enableSnippetSuccess' => __('Snippet enabled successfully', WP_EXTENDED_TEXT_DOMAIN),
                    'disableSnippetSuccess' => __('Snippet disabled successfully', WP_EXTENDED_TEXT_DOMAIN),
                    'toggleSnippetError' => __('Failed to update snippet status', WP_EXTENDED_TEXT_DOMAIN),
                    'editSnippet' => __('Edit Snippet', WP_EXTENDED_TEXT_DOMAIN),
                    'edit' => __('Edit', WP_EXTENDED_TEXT_DOMAIN),
                    'delete' => __('Delete', WP_EXTENDED_TEXT_DOMAIN),
                ),
            )
        );

        wp_add_inline_script('wpext-code-snippets', '
            document.addEventListener("DOMContentLoaded", function() {
                document.addEventListener("click", function(e) {
                    if (e.target.matches("a[href*=\'wpextended_safe_mode=0\']")) {
                        e.preventDefault();
                        const button = e.target;
                        const originalText = button.textContent;
                        button.textContent = "Disabling...";
                        button.style.pointerEvents = "none";
                        window.location.href = button.href;
                    }
                });
            });
        ');
    }
}
