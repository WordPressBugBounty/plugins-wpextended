<?php

namespace Wpextended\Modules\SmtpEmail;

use Wpextended\Includes\Utils;

class SmtpHandler
{
    /**
     * Initialize the SMTP handler
     */
    public function init()
    {
        // Override wp_mail using pre_wp_mail filter
        add_filter('pre_wp_mail', [$this, 'handleWpMail'], 10, 2);
    }

    /**
     * Handle wp_mail using pre_wp_mail filter
     */
    public function handleWpMail($null, $atts)
    {
        // Get settings using Utils::getSettings
        $settings = Utils::getSettings('smtp-email');

        // Validate required settings
        if (empty($settings['host']) || empty($settings['username']) || empty($settings['password'])) {
            $error = __('SMTP settings are incomplete. Please configure host, username and password.', WP_EXTENDED_TEXT_DOMAIN);
            $this->emailResult($atts['to'], $atts['subject'], 'Fail', $error);
            return false;
        }

        // Extract email data
        $to = $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = $atts['headers'];
        $attachments = $atts['attachments'];

        // Format recipients for logging
        $recipients = is_array($to) ? implode(', ', $to) : $to;

        try {
            // Get PHPMailer instance
            global $phpmailer;
            if (!($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer)) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            }

            // Configure PHPMailer for SMTP
            $phpmailer->isSMTP();
            $phpmailer->SMTPAuth = true;
            $phpmailer->From = $settings['username'] ?? '';
            $phpmailer->Host = $settings['host'] ?? '';
            $phpmailer->Port = $settings['port'] ?? '';

            // Set encryption based on port
            if ($settings['port'] == 465) {
                $phpmailer->SMTPSecure = 'ssl';
            } else {
                $phpmailer->SMTPSecure = 'tls';
            }

            $phpmailer->Username = $settings['username'] ?? '';
            $phpmailer->Password = $settings['password'] ?? '';
            $phpmailer->Timeout = 10;

            // Override FROM name if force_from_name is enabled or it's the default WordPress name
            $defaultFromName = $phpmailer->FromName;
            if (($settings['force_from_name'] ?? false) || 'WordPress' === $defaultFromName) {
                $phpmailer->FromName = $settings['from_name'] ?? '';
            }

            // Override FROM email if force_from_email is enabled or it starts with 'wordpress'
            $fromEmailAsWordpress = substr($phpmailer->From, 0, 9);
            if (($settings['force_from_email'] ?? false) || 'wordpress' === $fromEmailAsWordpress) {
                $phpmailer->From = $settings['from_email'] ?? '';
            }

            // Set custom X-Mailer header
            $phpmailer->XMailer = 'WP Extended v' . WP_EXTENDED_VERSION;

            // Set recipients
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        throw new \Exception(sprintf(__('Invalid recipient email address: %s', WP_EXTENDED_TEXT_DOMAIN), $recipient));
                    }
                    $phpmailer->addAddress($recipient);
                }
            } else {
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception(sprintf(__('Invalid recipient email address: %s', WP_EXTENDED_TEXT_DOMAIN), $to));
                }
                $phpmailer->addAddress($to);
            }

            // Set subject and message
            $phpmailer->Subject = $subject;
            $phpmailer->Body = $message;

            // Handle headers
            if (!empty($headers)) {
                if (is_array($headers)) {
                    foreach ($headers as $header) {
                        if (strpos($header, 'Content-Type:') === 0) {
                            $content_type = str_replace('Content-Type: ', '', $header);
                            $phpmailer->ContentType = $content_type;
                        } else {
                            $phpmailer->addCustomHeader($header);
                        }
                    }
                } else {
                    $phpmailer->addCustomHeader($headers);
                }
            }

            // Handle attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (!file_exists($attachment)) {
                        throw new \Exception(sprintf(__('Attachment file not found: %s', WP_EXTENDED_TEXT_DOMAIN), $attachment));
                    }
                    $phpmailer->addAttachment($attachment);
                }
            }

            // Send the email
            $result = $phpmailer->send();

            // Log the email if logging is enabled
            $this->emailResult($recipients, $subject, 'Success', '');

            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // Log the failure if logging is enabled
            $error = sprintf(
                __('%s (Code: %s)', WP_EXTENDED_TEXT_DOMAIN),
                $e->getMessage(),
                $e->getCode()
            );
            $this->emailResult($recipients, $subject, 'Fail', $error);

            return false;
        } catch (\Exception $e) {
            // Log the failure if logging is enabled
            $error = sprintf(
                __('General Error: %s', WP_EXTENDED_TEXT_DOMAIN),
                $e->getMessage()
            );
            $this->emailResult($recipients, $subject, 'Fail', $error);

            return false;
        }
    }

    /**
     * Send SMTP test email
     */
    public function sendTestEmail($email, $errorReason = '')
    {
        $subject = __('Test email from WP Extended SMTP Module', WP_EXTENDED_TEXT_DOMAIN);
        // Validate email address
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->emailResult($email, $subject, 'Fail', __('Invalid test email address', WP_EXTENDED_TEXT_DOMAIN));
            return false;
        }

        // Get email content (HTML template)
        $message = $this->getTestEmailContent();

        // Set headers for HTML email
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Add error reason to message if provided
        if (!empty($errorReason)) {
            $message .= "<br><br><strong>Error Message:</strong> " . $errorReason;
        }

        if (empty($email)) {
            $email = get_option('admin_email');
        }

        wp_mail($email, $subject, $message, $headers);

        global $phpmailer;

        $has_error = isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo);

        return $has_error ? $phpmailer->ErrorInfo : __('Test email sent successfully.', WP_EXTENDED_TEXT_DOMAIN);
    }

    /**
     * Get test email HTML content
     */
    public function getTestEmailContent()
    {
        $user = wp_get_current_user();
        $username = $user->user_login;
        $firstName = $user->first_name != '' ? $user->first_name : $username;
        $websiteUrl = get_bloginfo('url');
        $testTimestamp = current_time('mysql');
        $emailAddress = $user->user_email;

        // Get the email template
        $template = file_get_contents(__DIR__ . '/TestEmail.php');

        // Replace placeholders
        $replacements = array(
            '{{logo_url}}' => WP_EXTENDED_URL . 'admin/assets/images/wpe-logo-horizontal.png',
            '{{first_name}}' => $firstName,
            '{{username}}' => $username,
            '{{email_address}}' => $emailAddress,
            '{{website_url}}' => $websiteUrl,
            '{{test_timestamp}}' => $testTimestamp,
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Trigger the email result
     *
     * @param string $to The email address of the recipient
     * @param string $subject The subject of the email
     * @param string $status The status of the email
     * @param string $errorReason The reason for the email failure
     * @return void
     */
    public function emailResult($to, $subject, $status, $errorReason = '')
    {
        do_action('wpextended/smtp-email/email_sent', $to, $subject, $status, $errorReason, $this);

        if ($status === 'Fail') {
            do_action('wpextended/smtp-email/email_fail', $to, $subject, $status, $errorReason, $this);
        } else {
            do_action('wpextended/smtp-email/email_success', $to, $subject, $status, $errorReason, $this);
        }
    }
}
