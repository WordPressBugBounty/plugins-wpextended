<?php

namespace Wpextended\Modules\RollbackManager;

/**
 * WordPress.org API Client
 * Used to fetch information about themes and plugins from the WordPress.org API
 */
class WordPressApi
{
    /**
     * Fetch theme information from the WordPress.org API
     *
     * @param string $slug Theme slug
     * @return object|false Theme information or false on failure
     */
    public static function getThemeInfo($slug)
    {
        if (empty($slug)) {
            return false;
        }

        $args = (object) array('slug' => $slug);
        $request = array(
            'action' => 'theme_information',
            'timeout' => 15,
            'request' => serialize($args)
        );

        $response = wp_remote_post('http://api.wordpress.org/themes/info/1.0/', array('body' => $request));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = unserialize($body);

        if (!$data || is_wp_error($data)) {
            return false;
        }

        return $data;
    }

    /**
     * Fetch plugin information from the WordPress.org API
     *
     * @param string $slug Plugin slug
     * @return object|false Plugin information or false on failure
     */
    public static function getPluginInfo($slug)
    {
        if (empty($slug)) {
            return false;
        }

        $args = (object) array('slug' => $slug);
        $request = array(
            'action' => 'plugin_information',
            'timeout' => 15,
            'request' => serialize($args)
        );

        $response = wp_remote_post('http://api.wordpress.org/plugins/info/1.0/', array('body' => $request));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = unserialize($body);

        if (!$data || is_wp_error($data)) {
            return false;
        }

        return $data;
    }

    /**
     * Get available versions for a theme
     *
     * @param string $slug Theme slug
     * @return array Array of available versions
     */
    public static function getThemeVersions($slug)
    {
        if (empty($slug)) {
            return array();
        }

        $args = (object) array(
            'slug' => $slug,
            'fields' => array(
                'versions' => true,
                'download_link' => true
            )
        );
        $request = array(
            'action' => 'theme_information',
            'timeout' => 15,
            'request' => serialize($args)
        );

        $response = wp_remote_post('http://api.wordpress.org/themes/info/1.0/', array('body' => $request));

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = unserialize($body);

        if (!$data || is_wp_error($data)) {
            return array();
        }

        // If no versions are returned, create a version from the current download link
        if (empty($data->versions)) {
            $version = $data->version;
            $download_link = $data->download_link;
            return array($version => $download_link);
        }

        return (array) $data->versions;
    }

    /**
     * Get available versions for a plugin
     *
     * @param string $slug Plugin slug
     * @return array Array of available versions
     */
    public static function getPluginVersions($slug)
    {
        if (empty($slug)) {
            return array();
        }

        $args = (object) array(
            'slug' => $slug,
            'fields' => array(
                'versions' => true,
                'download_link' => true
            )
        );
        $request = array(
            'action' => 'plugin_information',
            'timeout' => 15,
            'request' => serialize($args)
        );

        $response = wp_remote_post('http://api.wordpress.org/plugins/info/1.0/', array('body' => $request));

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = unserialize($body);

        if (!$data || is_wp_error($data)) {
            return array();
        }

        // If no versions are returned, create a version from the current download link
        if (empty($data->versions)) {
            $version = $data->version;
            $download_link = $data->download_link;
            return array($version => $download_link);
        }

        return (array) $data->versions;
    }

    /**
     * Get the latest available version for a theme
     *
     * @param string $slug Theme slug
     * @return string|false Latest version or false if not found
     */
    public static function getLatestThemeVersion($slug)
    {
        $theme_info = self::getThemeInfo($slug);

        if (!$theme_info || !isset($theme_info['version'])) {
            return false;
        }

        return $theme_info['version'];
    }

    /**
     * Get the latest available version for a plugin
     *
     * @param string $slug Plugin slug
     * @return string|false Latest version or false if not found
     */
    public static function getLatestPluginVersion($slug)
    {
        $plugin_info = self::getPluginInfo($slug);

        if (!$plugin_info || !isset($plugin_info['version'])) {
            return false;
        }

        return $plugin_info['version'];
    }

    /**
     * Check if a plugin version is available
     *
     * @param string $slug Plugin slug
     * @param string $version Version to check
     * @return bool Whether the version is available
     */
    public static function isPluginVersionAvailable($slug, $version)
    {
        $versions = self::getPluginVersions($slug);
        return isset($versions[$version]);
    }

    /**
     * Check if a theme version is available
     *
     * @param string $slug Theme slug
     * @param string $version Version to check
     * @return bool Whether the version is available
     */
    public static function isThemeVersionAvailable($slug, $version)
    {
        $versions = self::getThemeVersions($slug);
        return isset($versions[$version]);
    }

    /**
     * Get download URL for a plugin version
     *
     * @param string $slug Plugin slug
     * @param string $version Version to download
     * @return string|false Download URL or false on failure
     */
    public static function getPluginDownloadUrl($slug, $version)
    {
        $versions = self::getPluginVersions($slug);

        if (empty($versions) || !isset($versions[$version])) {
            return false;
        }

        return $versions[$version];
    }

    /**
     * Get download URL for a theme version
     *
     * @param string $slug Theme slug
     * @param string $version Version to download
     * @return string|false Download URL or false on failure
     */
    public static function getThemeDownloadUrl($slug, $version)
    {
        $versions = self::getThemeVersions($slug);

        if (empty($versions) || !isset($versions[$version])) {
            return false;
        }

        return $versions[$version];
    }

    /**
     * Get all available version information for a theme formatted for display
     *
     * @param string $slug Theme slug
     * @param string $current_version The currently installed version for comparison
     * @return array Formatted version information
     */
    public static function getThemeVersionsForDisplay($slug, $current_version = '')
    {
        $versions = self::getThemeVersions($slug);
        $theme_info = self::getThemeInfo($slug);
        $results = [];

        if (empty($versions)) {
            return $results;
        }

        // Sort versions by version number
        uksort($versions, 'version_compare');

        foreach ($versions as $version => $download_url) {
            $is_current = version_compare($version, $current_version, '==');
            $is_newer = version_compare($version, $current_version, '>');
            $is_older = version_compare($version, $current_version, '<');

            $release_date = '';
            if ($version === $theme_info['version']) {
                $release_date = $theme_info['last_updated'];
            }

            $results[] = array(
                'version' => $version,
                'download_url' => $download_url,
                'is_current' => $is_current,
                'is_newer' => $is_newer,
                'is_older' => $is_older,
                'release_date' => $release_date
            );
        }

        return $results;
    }

    /**
     * Get all available version information for a plugin formatted for display
     *
     * @param string $slug Plugin slug
     * @param string $current_version The currently installed version for comparison
     * @return array Formatted version information
     */
    public static function getPluginVersionsForDisplay($slug, $current_version = '')
    {
        $versions = self::getPluginVersions($slug);
        $plugin_info = self::getPluginInfo($slug);
        $results = [];

        if (empty($versions)) {
            return $results;
        }

        // Sort versions by version number
        uksort($versions, 'version_compare');

        foreach ($versions as $version => $download_url) {
            $is_current = version_compare($version, $current_version, '==');
            $is_newer = version_compare($version, $current_version, '>');
            $is_older = version_compare($version, $current_version, '<');

            $release_date = '';
            if ($version === $plugin_info['version']) {
                $release_date = $plugin_info['last_updated'];
            }

            $results[] = array(
                'version' => $version,
                'download_url' => $download_url,
                'is_current' => $is_current,
                'is_newer' => $is_newer,
                'is_older' => $is_older,
                'release_date' => $release_date
            );
        }

        return $results;
    }

    /**
     * Get detailed information about a theme for display
     *
     * @param string $slug Theme slug
     * @return array|false Formatted theme information or false if not found
     */
    public static function getThemeDetailsForDisplay($slug)
    {
        $theme_info = self::getThemeInfo($slug);

        if (!$theme_info) {
            return false;
        }

        $details = array(
            'name' => $theme_info['name'],
            'slug' => $theme_info['slug'],
            'version' => $theme_info['version'],
            'author' => $theme_info['author']['display_name'],
            'author_url' => $theme_info['author']['author_url'],
            'screenshot_url' => $theme_info['screenshot_url'],
            'rating' => $theme_info['rating'] / 100 * 5, // Convert to 5-star rating
            'num_ratings' => $theme_info['num_ratings'],
            'downloaded' => $theme_info['downloaded'],
            'last_updated' => $theme_info['last_updated'],
            'requires_wp' => isset($theme_info['requires']) ? $theme_info['requires'] : '',
            'requires_php' => isset($theme_info['requires_php']) ? $theme_info['requires_php'] : '',
            'download_link' => $theme_info['download_link'],
            'description' => isset($theme_info['sections']['description']) ? $theme_info['sections']['description'] : '',
            'homepage' => isset($theme_info['homepage']) ? $theme_info['homepage'] : '',
        );

        return $details;
    }

    /**
     * Get detailed information about a plugin for display
     *
     * @param string $slug Plugin slug
     * @return array|false Formatted plugin information or false if not found
     */
    public static function getPluginDetailsForDisplay($slug)
    {
        $plugin_info = self::getPluginInfo($slug);

        if (!$plugin_info) {
            return false;
        }

        $details = array(
            'name' => $plugin_info['name'],
            'slug' => $plugin_info['slug'],
            'version' => $plugin_info['version'],
            'author' => $plugin_info['author'],
            'author_profile' => isset($plugin_info['author']['profile']) ? $plugin_info['author']['profile'] : '',
            'rating' => $plugin_info['rating'] / 100 * 5, // Convert to 5-star rating
            'num_ratings' => $plugin_info['num_ratings'],
            'active_installs' => isset($plugin_info['active_installs']) ? $plugin_info['active_installs'] : 0,
            'downloaded' => $plugin_info['downloaded'],
            'last_updated' => $plugin_info['last_updated'],
            'requires_wp' => isset($plugin_info['requires']) ? $plugin_info['requires'] : '',
            'requires_php' => isset($plugin_info['requires_php']) ? $plugin_info['requires_php'] : '',
            'tested' => isset($plugin_info['tested']) ? $plugin_info['tested'] : '',
            'download_link' => $plugin_info['download_link'],
            'description' => isset($plugin_info['sections']['description']) ? $plugin_info['sections']['description'] : '',
            'homepage' => isset($plugin_info['homepage']) ? $plugin_info['homepage'] : '',
        );

        return $details;
    }

    /**
     * Get all available version information for a theme formatted for display
     *
     * @param string $slug Theme slug
     * @param string $current_version The currently installed version for comparison
     * @return array Formatted version information
     */
    public static function getThemeVersionsForDisplayDetailed($slug, $current_version = '')
    {
        $versions = self::getThemeVersions($slug);
        $theme_info = self::getThemeInfo($slug);
        $results = [];

        if (empty($versions)) {
            return $results;
        }

        // Sort versions by version number
        uksort($versions, 'version_compare');

        foreach ($versions as $version => $download_url) {
            $is_current = version_compare($version, $current_version, '==');
            $is_newer = version_compare($version, $current_version, '>');
            $is_older = version_compare($version, $current_version, '<');

            $release_date = '';
            if ($version === $theme_info['version']) {
                $release_date = $theme_info['last_updated'];
            }

            $results[] = array(
                'version' => $version,
                'download_url' => $download_url,
                'is_current' => $is_current,
                'is_newer' => $is_newer,
                'is_older' => $is_older,
                'release_date' => $release_date
            );
        }

        return $results;
    }

    /**
     * Get all available version information for a plugin formatted for display
     *
     * @param string $slug Plugin slug
     * @param string $current_version The currently installed version for comparison
     * @return array Formatted version information
     */
    public static function getPluginVersionsForDisplayDetailed($slug, $current_version = '')
    {
        $versions = self::getPluginVersions($slug);
        $plugin_info = self::getPluginInfo($slug);
        $results = [];

        if (empty($versions)) {
            return $results;
        }

        // Sort versions by version number
        uksort($versions, 'version_compare');

        foreach ($versions as $version => $download_url) {
            $is_current = version_compare($version, $current_version, '==');
            $is_newer = version_compare($version, $current_version, '>');
            $is_older = version_compare($version, $current_version, '<');

            $release_date = '';
            if ($version === $plugin_info['version']) {
                $release_date = $plugin_info['last_updated'];
            }

            $results[] = array(
                'version' => $version,
                'download_url' => $download_url,
                'is_current' => $is_current,
                'is_newer' => $is_newer,
                'is_older' => $is_older,
                'release_date' => $release_date
            );
        }

        return $results;
    }
}
