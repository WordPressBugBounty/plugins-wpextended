<?php

namespace Wpextended\Modules\SmtpEmail;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

class Bootstrap extends BaseModule
{
    /**
     * SMTP handler instance
     *
     * @var SmtpHandler
     */
    protected $smtp_handler;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('smtp-email');
        $this->smtp_handler = new SmtpHandler();
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('rest_api_init', [$this, 'registerRoutes']);

        $this->smtp_handler->init();
    }

    /**
     * Enqueue module assets
     */
    public function enqueueAssets(): void
    {
        if (!Utils::isPluginScreen($this->module_id)) {
            return;
        }

        Utils::enqueueStyle(
            'wpextended-smtp-email',
            $this->getPath('assets/css/style.css'),
        );

        Utils::enqueueScript(
            'wpextended-smtp-email',
            $this->getPath('assets/js/script.js'),
            ['jquery'],
            WP_EXTENDED_VERSION,
            true
        );

        wp_localize_script('wpextended-smtp-email', 'wpextSmtpEmail', [
            'restUrl' => rest_url('wpextended/v1/smtp-email/test'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'email_required' => __('Please enter an email address.', WP_EXTENDED_TEXT_DOMAIN),
                'invalid_email' => __('Please enter a valid email address.', WP_EXTENDED_TEXT_DOMAIN),
                'sendingEmail' => __('Sending Test Email...', WP_EXTENDED_TEXT_DOMAIN),
                'emailSentSuccess' => __('Test email sent successfully! Please check your inbox.', WP_EXTENDED_TEXT_DOMAIN),
                'emailSentError' => __('Failed to send test email. Please check your SMTP settings and try again.', WP_EXTENDED_TEXT_DOMAIN),
                'buttonText' => __('Send Test Email', WP_EXTENDED_TEXT_DOMAIN)
            ],
        ]);
    }

    /**
     * Get module settings fields
     */
    protected function getSettingsFields(): array
    {
        $settings = array();

        $settings['tabs'] = array(
            array(
                'id' => 'configuration',
                'title' => __('Configuration', WP_EXTENDED_TEXT_DOMAIN),
            ),
            array(
                'id' => 'smtp_settings',
                'title' => __('SMTP Settings', WP_EXTENDED_TEXT_DOMAIN),
            ),
            array(
                'id' => 'test_email',
                'title' => __('Test Email', WP_EXTENDED_TEXT_DOMAIN),
            ),
        );

        $settings['sections'] = array(
            // Sender Settings (Configuration Tab)
            array(
                'tab_id' => 'configuration',
                'section_id'    => 'sender_settings',
                'section_title' => __('Sender Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id'          => 'from_name',
                        'type'        => 'text',
                        'title'       => __('From Name', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The name of the sender.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => false,
                    ),
                    array(
                        'id' => 'from_email',
                        'type' => 'email',
                        'title' => __('From Email', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The email address of the sender.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => true,
                    ),
                    array(
                        'id' => 'force_from_email',
                        'type' => 'toggle',
                        'title' => __('Force From Email', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The from email above will be used for all emails sent by the site.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => false,
                    ),
                    array(
                        'id' => 'force_from_name',
                        'type' => 'toggle',
                        'title' => __('Force Sender Name', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The sender name above will be used for all emails sent by the site.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => false,
                    ),
                    array(
                        'id' => 'message',
                        'type' => 'custom',
                        'callback' => function () {
                            $content = sprintf(
                                '<strong>Note:</strong> SMTP server details have been moved to %s',
                                Utils::getInternalLink('smtp-email', 'smtp_settings', null, [], __('SMTP Settings', WP_EXTENDED_TEXT_DOMAIN))
                            );

                            echo wp_kses_post($content);
                        }
                    ),
                ),
            ),
            // SMTP Settings (SMTP Details Tab)
            array(
                'tab_id' => 'smtp_settings',
                'section_id'    => 'smtp_settings',
                'section_title' => __('SMTP Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id' => 'host',
                        'type' => 'text',
                        'title' => __('SMTP Host', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The host of the SMTP server.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => true,
                    ),
                    array(
                        'id' => 'port',
                        'type' => 'number',
                        'title' => __('SMTP Port', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The port of the SMTP server.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => true,
                    ),
                    array(
                        'id' => 'encryption',
                        'type' => 'radio',
                        'title' => __('Encryption', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select SSL for 465 or TLS for 587.', WP_EXTENDED_TEXT_DOMAIN),
                        'layout' => 'inline',
                        'required'    => true,
                        'default' => 'none',
                        'choices' => array(
                            'none' => 'None',
                            'ssl' => 'SSL',
                            'tls' => 'TLS',
                        ),
                    ),
                    array(
                        'id' => 'auto_tls',
                        'type' => 'toggle',
                        'title' => __('Use Auto TLS', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('TLS encryption is enabled by default but may cause issues on some servers.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => true,
                        'required'    => false,
                    ),
                    array(
                        'id' => 'username',
                        'type' => 'text',
                        'title' => __('Username', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The username for the SMTP server.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => true,
                    ),
                    array(
                        'id' => 'password',
                        'type' => 'password',
                        'title' => __('Password', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The password for the SMTP server.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => true,
                    ),
                ),
            ),
            // Test Email (Test Email Tab)
            array(
                'tab_id' => 'test_email',
                'section_id'    => 'test_email',
                'section_title' => __('Test Email', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id' => 'email_address',
                        'type' => 'email',
                        'title' => __('Email Address', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The email address to send the test email to.', WP_EXTENDED_TEXT_DOMAIN),
                        'required'    => true,
                        'default' => wp_get_current_user()->user_email,
                    ),
                    array(
                        'id' => 'test_email_button',
                        'type' => 'button',
                        'loader' => true,
                        'title' => __('Send Test Email', WP_EXTENDED_TEXT_DOMAIN),
                    )
                ),
            ),
        );

        return $settings;
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // Test email endpoint
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/smtp-email/test', [
            'methods' => 'POST',
            'callback' => [$this, 'sendTestEmail'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'required' => true,
                ],
            ],
        ]);
    }

    /**
     * Check permission
     *
     * @return bool True if user has manage_options capability, false otherwise
     */
    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Verify REST API nonce
     *
     * @param \WP_REST_Request $request The request object
     * @return bool Whether the nonce is valid
     */
    private function verifyRestNonce($request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        return wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Send test email
     *
     * @param \WP_REST_Request $request The REST request object
     * @return \WP_REST_Response The response object
     */
    public function sendTestEmail($request): \WP_REST_Response
    {
        // Verify nonce
        if (!$this->verifyRestNonce($request)) {
            return new \WP_REST_Response([
                'error' => __('Security check failed.', WP_EXTENDED_TEXT_DOMAIN)
            ], 403);
        }

        $email = sanitize_email($request->get_param('email'));
        $result = $this->smtp_handler->sendTestEmail($email);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result ? __('Test email sent successfully.', WP_EXTENDED_TEXT_DOMAIN) : __('Failed to send test email.', WP_EXTENDED_TEXT_DOMAIN)
        ], $result ? 200 : 500);
    }
}
