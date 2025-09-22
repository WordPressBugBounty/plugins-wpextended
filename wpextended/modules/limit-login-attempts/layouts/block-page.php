<?php

/**
 * Blocked IP Page Layout
 *
 * This file is included when an IP is blocked from accessing login pages.
 * It shows a styled error message with remaining time and prevents further execution.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if this is a blocked username or IP
$is_blocked_username = isset($GLOBALS['wpext_blocked_username']) && !empty($GLOBALS['wpext_blocked_username']);
$blocked_username = $is_blocked_username ? $GLOBALS['wpext_blocked_username'] : '';

// Early return if no IP provided (for IP blocking)
if (empty($ip) && !$is_blocked_username) {
    return;
}

// Get remaining time (only for IP blocking)
$remaining_time = 0;
$minutes = 0;
$seconds = 0;

if (!$is_blocked_username && !empty($ip)) {
    $remaining_time = $this->attemptsHandler->getRemainingBlockTime($ip);
    $minutes = floor($remaining_time / 60);
    $seconds = $remaining_time % 60;
}

// Prevent caching
nocache_headers();

// Set content type
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Access Denied', WP_EXTENDED_TEXT_DOMAIN); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #334155;
            line-height: 1.5;
        }

        .container {
            background: white;
            padding: 48px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        .icon {
            width: 48px;
            height: 48px;
            background-color: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: #dc2626;
            font-size: 24px;
        }

        h1 {
            color: #1e293b;
            margin: 0 0 16px 0;
            font-size: 24px;
            font-weight: 600;
        }

        .description {
            margin: 0 0 32px 0;
            color: #64748b;
            font-size: 15px;
        }

        .details {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 32px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 600;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, monospace;
        }

        .time-value {
            color: #dc2626;
            font-size: 16px;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .button:hover {
            background-color: #2563eb;
        }

        @media (max-width: 640px) {
            body {
                padding: 16px;
            }

            .container {
                padding: 32px 24px;
            }

            h1 {
                font-size: 20px;
            }

            .icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠</div>
        <?php if ($is_blocked_username) : ?>
            <h1><?php esc_html_e('Username Not Allowed', WP_EXTENDED_TEXT_DOMAIN); ?></h1>
            <p class="description">
                <?php esc_html_e('The username you attempted to use is not allowed for login on this site.', WP_EXTENDED_TEXT_DOMAIN); ?>
            </p>

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Blocked Username', WP_EXTENDED_TEXT_DOMAIN); ?></span>
                    <span class="detail-value"><?php echo esc_html($blocked_username); ?></span>
                </div>
            </div>
        <?php else : ?>
            <h1><?php esc_html_e('Access Temporarily Restricted', WP_EXTENDED_TEXT_DOMAIN); ?></h1>
            <p class="description">
                <?php esc_html_e('Too many failed login attempts have been detected from your IP address. Access has been temporarily restricted for security purposes.', WP_EXTENDED_TEXT_DOMAIN); ?>
            </p>

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('IP Address', WP_EXTENDED_TEXT_DOMAIN); ?></span>
                    <span class="detail-value"><?php echo esc_html($ip); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Time Remaining', WP_EXTENDED_TEXT_DOMAIN); ?></span>
                    <span class="detail-value time-value"><?php printf('%02d:%02d', $minutes, $seconds); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <a href="<?php echo esc_url(home_url()); ?>" class="button">
            <?php esc_html_e('Return to Homepage', WP_EXTENDED_TEXT_DOMAIN); ?>
        </a>
    </div>
    <script>
        <?php if (!$is_blocked_username) : ?>
        // Initialize countdown with total remaining seconds from PHP
        let countdown = <?php echo (int) ($minutes * 60 + $seconds); ?>;

        // Get the DOM element that displays the remaining time
        const timeValue = document.querySelector('.time-value');

        /**
         * Format seconds as MM:SS
         * @param {number} secs - Number of seconds remaining
         * @returns {string} - Formatted time string
         */
        function formatTime(secs) {
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            return ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
        }

        /**
         * Update the countdown timer on the page every second.
         * When the timer reaches zero, reload the page to check if the block is lifted.
         */
        function updateCountdown() {
            if (countdown <= 0) {
                timeValue.textContent = '00:00';
                window.location.reload();
                return;
            }
            timeValue.textContent = formatTime(countdown);
            countdown--;
        }

        // Initial update to set the timer immediately on page load
        updateCountdown();

        const timer = setInterval(updateCountdown, 1000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Exit to prevent further execution
exit;
?>
