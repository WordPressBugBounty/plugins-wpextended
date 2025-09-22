<?php

namespace Wpextended\Modules\DiskUsageWidget;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * Bootstrap class for the DiskUsage module
 *
 * Provides disk usage information in the WordPress dashboard
 */
class Bootstrap extends BaseModule
{
    /**
     * The thresholds for the disk usage
     *
     * @var array
     */
    private $thresholds;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('disk-usage-widget');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('wp_dashboard_setup', array($this, 'addDashboardWidget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'), 110);

        $this->thresholds = $this->getThresholds();
    }

    /**
     * Get thresholds for disk usage monitoring
     *
     * @return array Threshold configuration
     */
    private function getThresholds(): array
    {
        $thresholds = [
            // WordPress Core
            'wordpress_size' => [
                'size' => '250MB', // Error at 250MB (typical WP core is ~50MB)
                'percentage' => 80 // Warning at 80% of limit
            ],

            // Database
            'database_size' => [
                'size' => '1GB', // Error at 1GB
                'percentage' => 80 // Warning at 80% of limit
            ],

            // Disk Space
            'disk_space' => [
                'percentage' => 90
            ],

            // WP Content Directories
            'wp_content_directories' => [
                'plugins' => [
                    'size' => '',
                    'percentage' => 0
                ],
                'themes' => [
                    'size' => '',
                    'percentage' => 0
                ],
                'uploads' => [
                    'size' => '50GB',
                    'percentage' => 80
                ],
                'languages' => [
                    'size' => '',
                    'percentage' => 0
                ],
                'upgrade' => [
                    'size' => '',
                    'percentage' => 0
                ],
                'backup' => [
                    'size' => '',
                    'percentage' => 0
                ],
                'cache' => [
                    'size' => '',
                    'percentage' => 0
                ]
            ]
        ];

        return apply_filters('wpextended/disk_usage/thresholds', $thresholds);
    }

    /**
     * Convert size threshold to bytes
     *
     * @param string $size Size string (e.g., '100MB', '1GB')
     * @return float Size in bytes
     */
    private function convertSizeToBytes(string $size): float
    {
        // Return 0 for empty strings or '0'
        if (empty($size) || $size === '0') {
            return 0;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(MB|GB)$/i', $size, $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'GB':
                    return $value * 1024 * 1024 * 1024;
                case 'MB':
                default:
                    return $value * 1024 * 1024;
            }
        }

        return 0;
    }

    /**
     * Format a size threshold for display
     *
     * @param string $size Size string (e.g., '100MB', '1GB')
     * @return string Formatted size
     */
    private function formatSizeThreshold(string $size): string
    {
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(MB|GB)$/i', $size, $matches)) {
            return esc_html($matches[1] . ' ' . strtoupper($matches[2]));
        }

        return esc_html($size);
    }

    /**
     * Enqueue the assets
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        $screen = get_current_screen();

        if ($screen->id !== 'dashboard') {
            return;
        }

        Utils::enqueueStyle(
            'wpext-disk-usage',
            $this->getPath('assets/css/style.css'),
        );
    }

    /**
     * Add the dashboard widget
     *
     * @return void
     */
    public function addDashboardWidget(): void
    {
        wp_add_dashboard_widget(
            'dashboard_disk_usage',
            esc_html__('Server Disk Usage', WP_EXTENDED_TEXT_DOMAIN),
            array($this, 'dashboardWidget')
        );
    }

    /**
     * Displays disk usage and directory sizes on the WordPress dashboard
     *
     * @return void
     */
    public function dashboardWidget(): void
    {
        // Collect all necessary data
        $server_space_data = $this->getServerSpaceData();
        $installation_data = $this->getInstallationData();
        $wp_content_data = $this->getWpContentData();

        // Generate sections
        $current_installation_section = $this->generateMarkup(
            esc_html__('Current Installation', WP_EXTENDED_TEXT_DOMAIN),
            $installation_data
        );

        $wp_content_sizes_section = $this->generateMarkup(
            esc_html__('WP Content Sizes', WP_EXTENDED_TEXT_DOMAIN),
            $wp_content_data
        );

        $server_space_section = $this->generateMarkup(
            esc_html__('Server Space', WP_EXTENDED_TEXT_DOMAIN),
            $server_space_data
        );

        // Output the information
        echo $current_installation_section;
        echo $wp_content_sizes_section;
        echo $server_space_section;
        echo $this->getDocumentationMessage();
    }

    /**
     * Format a value with optional warning/error
     *
     * @param string $value The value to format
     * @param array|null $alert The alert data if any
     * @return array Formatted value and alert status
     */
    private function formatValueWithAlert(string $value, ?array $alert = null): array
    {
        return [
            'value' => $value,
            'alert' => $alert
        ];
    }

    /**
     * Generates HTML markup for a section with a title and data
     *
     * @param string $title The title of the section
     * @param array $data The data to display in the section
     * @return string The generated HTML markup
     */
    public function generateMarkup(string $title, array $data): string
    {
        $rows = '';

        foreach ($data as $key => $value) {
            // Skip special warning keys
            if (strpos($key, '__') === 0) {
                continue;
            }

            // Handle both old format (string) and new format (array with alert)
            $display_value = is_array($value) ? $value['value'] : $value;
            $alert = is_array($value) && isset($value['alert']) ? $value['alert'] : null;
            $has_alert = !empty($alert);

            // Generate the main row
            $row_class = $has_alert ? ' class="has-' . esc_attr($alert['type']) . '"' : '';
            $rows .= sprintf(
                '<tr%s>
                    <th scope="row">%s</th>
                    <td>%s</td>
                </tr>',
                $row_class,
                wp_kses($key, array()),
                wp_kses($display_value, array())
            );

            // Add alert row if needed
            if ($has_alert) {
                $rows .= sprintf(
                    '<tr class="%s-message">
                        <td colspan="2">%s</td>
                    </tr>',
                    esc_attr($alert['type']),
                    wp_kses($alert['message'], array())
                );
            }
        }

        return sprintf(
            '<div class="wpextended-dashboard-widget-section">
                <h3>%s</h3>
                <table class="widefat">
                    <tbody>
                        %s
                    </tbody>
                </table>
            </div>',
            esc_html($title),
            $rows
        );
    }

    /**
     * Check thresholds and return alert if needed
     *
     * @param float $size Current size in bytes
     * @param float $total_size Total size for percentage calculation (unused for wp-content directories)
     * @param array $threshold Threshold configuration
     * @return array|null Alert data if threshold exceeded, null otherwise
     */
    private function checkThresholds(float $size, float $total_size, array $threshold): ?array
    {
        // First check size limit (error state)
        if (!empty($threshold['size'])) {
            $size_limit = $this->convertSizeToBytes($threshold['size']);
            if ($size_limit > 0 && $size > $size_limit) {
                return [
                    'type' => 'error',
                    'message' => sprintf(
                        esc_html__('Error: Size exceeds %s limit', WP_EXTENDED_TEXT_DOMAIN),
                        $this->formatSizeThreshold($threshold['size'])
                    )
                ];
            }

            // Then check percentage of the size limit (warning state)
            if (!empty($threshold['percentage']) && $threshold['percentage'] > 0) {
                $percentage = ($size / $size_limit) * 100;
                if ($percentage > $threshold['percentage']) {
                    return [
                        'type' => 'warning',
                        'message' => sprintf(
                            esc_html__('Warning: Size is %d%% of %s limit', WP_EXTENDED_TEXT_DOMAIN),
                            round($percentage),
                            $this->formatSizeThreshold($threshold['size'])
                        )
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get server space data including used, free and total disk space
     *
     * @return array Server space data
     */
    private function getServerSpaceData(): array
    {
        $base_path = ABSPATH;
        $disk_free = @disk_free_space($base_path);
        $disk_total = @disk_total_space($base_path);
        $disk_usage = $this->calculateDiskUsage($disk_free, $disk_total);

        $used_display = $this->humanFilesize($disk_usage['used']) . ' (' . esc_html($disk_usage['used_perc']) . '%)';
        $free_display = $this->humanFilesize($disk_usage['free']) . ' (' . esc_html($disk_usage['free_perc']) . '%)';

        $free_alert = null;
        if (isset($this->thresholds['disk_space'])) {
            // For disk space, we still want to use total disk space for percentage
            $free_alert = $this->checkDiskSpaceThresholds(
                $disk_free,
                $disk_total,
                $this->thresholds['disk_space']
            );
        }

        return array(
            esc_html__('Used Disk Space', WP_EXTENDED_TEXT_DOMAIN) => $this->formatValueWithAlert($used_display),
            esc_html__('Free Disk Space', WP_EXTENDED_TEXT_DOMAIN) => $this->formatValueWithAlert($free_display, $free_alert),
            esc_html__('Total Disk Space', WP_EXTENDED_TEXT_DOMAIN) => $this->formatValueWithAlert($this->humanFilesize($disk_total))
        );
    }

    /**
     * Check disk space thresholds specifically
     *
     * @param float $free_space Free disk space in bytes
     * @param float $total_space Total disk space in bytes
     * @param array $threshold Threshold configuration
     * @return array|null Alert data if threshold exceeded, null otherwise
     */
    private function checkDiskSpaceThresholds(float $free_space, float $total_space, array $threshold): ?array
    {
        // First check absolute size limit
        if (!empty($threshold['size'])) {
            $size_limit = $this->convertSizeToBytes($threshold['size']);
            if ($size_limit > 0 && $free_space < $size_limit) {
                return [
                    'type' => 'error',
                    'message' => sprintf(
                        esc_html__('Error: Free space is below %s', WP_EXTENDED_TEXT_DOMAIN),
                        $this->formatSizeThreshold($threshold['size'])
                    )
                ];
            }
        }

        // Then check percentage of total disk space
        if (!empty($threshold['percentage']) && $threshold['percentage'] > 0 && $total_space > 0) {
            $used_space = $total_space - $free_space;
            $used_percentage = ($used_space / $total_space) * 100;
            if ($used_percentage > $threshold['percentage']) {
                return [
                    'type' => 'warning',
                    'message' => sprintf(
                        esc_html__('Warning: Disk usage is at %d%%, exceeds %d%% threshold', WP_EXTENDED_TEXT_DOMAIN),
                        round($used_percentage),
                        $threshold['percentage']
                    )
                ];
            }
        }

        return null;
    }

    /**
     * Calculate disk usage statistics
     *
     * @param float $disk_free Free disk space in bytes
     * @param float $disk_total Total disk space in bytes
     * @return array Disk usage statistics
     */
    private function calculateDiskUsage($disk_free, $disk_total): array
    {
        // Check if disk total space is valid to avoid division by zero
        if ($disk_total > 0) {
            $disk_used = $disk_total - $disk_free;
            $disk_used_perc = round($disk_used / $disk_total * 100, 2);
            $disk_free_perc = round($disk_free / $disk_total * 100, 2);
        } else {
            // Default values if disk total space is not available
            $disk_used = 0;
            $disk_used_perc = 0;
            $disk_free_perc = 0;
        }

        return array(
            'used' => $disk_used,
            'free' => $disk_free,
            'used_perc' => $disk_used_perc,
            'free_perc' => $disk_free_perc,
        );
    }

    /**
     * Get installation data including WordPress size, database size, and total size
     *
     * @return array Installation data
     */
    private function getInstallationData(): array
    {
        $wp_size = $this->getDirectorySize(ABSPATH);
        $wp_size_display = $this->humanFilesize($wp_size);
        $wp_alert = null;

        if (isset($this->thresholds['wordpress_size'])) {
            $wp_alert = $this->checkThresholds(
                $wp_size,
                $wp_size,
                $this->thresholds['wordpress_size']
            );
        }

        $db_size = $this->getDatabaseSize();
        $db_size_display = $this->humanFilesize($db_size);
        $db_alert = null;

        if (isset($this->thresholds['database_size'])) {
            $db_alert = $this->checkThresholds(
                $db_size,
                $db_size,
                $this->thresholds['database_size']
            );
        }

        $install_size = $wp_size + $db_size;

        return array(
            esc_html__('WordPress Size', WP_EXTENDED_TEXT_DOMAIN) => $this->formatValueWithAlert($wp_size_display, $wp_alert),
            esc_html__('Database Size', WP_EXTENDED_TEXT_DOMAIN) => $this->formatValueWithAlert($db_size_display, $db_alert),
            esc_html__('Full Website Size', WP_EXTENDED_TEXT_DOMAIN) => $this->formatValueWithAlert($this->humanFilesize($install_size))
        );
    }

    /**
     * Calculate the total size of the WordPress database
     *
     * @global wpdb $wpdb WordPress database abstraction object
     * @return float Total database size in bytes
     */
    private function getDatabaseSize(): float
    {
        global $wpdb;

        $db_size = 0;
        // wpextended:ignore Security.QueryPreparation
        $rows = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);

        if (!is_array($rows)) {
            return $db_size;
        }

        foreach ($rows as $row) {
            $db_size += $row['Data_length'] + $row['Index_length'];
        }

        return $db_size;
    }

    /**
     * Get the sizes of wp-content subdirectories
     *
     * @return array Directory sizes data
     */
    private function getWpContentData(): array
    {
        $wp_content_dir = WP_CONTENT_DIR;
        $dir_sizes = array();
        $total_wp_content_size = 0;

        if (is_dir($wp_content_dir) && is_readable($wp_content_dir)) {
            $dir_sizes = $this->getSubdirectorySizes($wp_content_dir);
            $total_wp_content_size = array_sum($dir_sizes);
        }

        $wp_content_sizes_data = array();
        foreach ($dir_sizes as $name => $size) {
            $display_name = ucwords(str_replace('-', ' ', $name)) . ' Size';
            $size_display = $this->humanFilesize($size);
            $alert = null;

            if (isset($this->thresholds['wp_content_directories'][$name])) {
                $alert = $this->checkThresholds(
                    $size,
                    $total_wp_content_size,
                    $this->thresholds['wp_content_directories'][$name]
                );
            }

            $wp_content_sizes_data[esc_html($display_name)] = $this->formatValueWithAlert($size_display, $alert);
        }

        return $wp_content_sizes_data;
    }

    /**
     * Get the sizes of subdirectories within a given directory
     *
     * @param string $directory Parent directory path
     * @return array Subdirectory sizes
     */
    private function getSubdirectorySizes(string $directory): array
    {
        $dir_sizes = array();

        try {
            $subdirs = new \DirectoryIterator($directory);

            foreach ($subdirs as $subdir) {
                if ($subdir->isDir() && !$subdir->isDot()) {
                    $subdir_path = $directory . '/' . $subdir->getFilename();
                    $dir_sizes[$subdir->getFilename()] = $this->getDirectorySize($subdir_path);
                }
            }

            // Sort the directories by size in descending order
            arsort($dir_sizes);
        } catch (\Exception $e) {
            // Handle directory access errors silently
        }

        return $dir_sizes;
    }

    /**
     * Check if a user has full access
     *
     * @return bool Returns true if the user is an administrator
     */
    public function hasFullAccess(): bool
    {
        return is_user_logged_in() && current_user_can('administrator');
    }

    /**
     * Calculates the total size of a directory
     *
     * @param string $directory Directory path
     * @return float Total size of the directory in bytes
     */
    public function getDirectorySize(string $directory): float
    {
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Handle directory access errors silently
        }

        return $size;
    }

    /**
     * Converts bytes into human-readable format
     *
     * @param float $bytes Number of bytes
     * @return string Human-readable file size
     */
    private function humanFilesize(float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Get the documentation message
     *
     * @return string Formatted HTML message.
     */
    public function getDocumentationMessage(): string
    {
        $documentation_url = Utils::generateTrackedLink(
            'https://wpextended.io/docs/disk-usage-widget/',
            'dashboard'
        );

        $doc_link = sprintf(
            '<a href="%s" aria-label="%s" target="_blank">%s</a>',
            esc_url($documentation_url),
            esc_html__('Disk Usage Widget Documentation, opens in new tab', WP_EXTENDED_TEXT_DOMAIN),
            esc_html__('documentation', WP_EXTENDED_TEXT_DOMAIN),
        );

        return sprintf(
            '<p>%s</p>',
            sprintf(
                /* translators: %s: Documentation link HTML */
                esc_html__('For details on configuring disk usage thresholds, see %s.', WP_EXTENDED_TEXT_DOMAIN),
                $doc_link
            )
        );
    }
}
