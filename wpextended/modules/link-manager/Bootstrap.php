<?php

namespace Wpextended\Modules\LinkManager;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

class Bootstrap extends BaseModule
{
    /**
     * List of classes that should be excluded
     *
     * @var array
     */
    private $exclude_classes;

    /**
     * Flag to track if any arrows were added
     *
     * @var bool
     */
    private $arrows_added = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('link-manager');

        // Allow filtering of exclude classes
        $this->exclude_classes = apply_filters(
            'wpextended/link-manager/exclude_classes',
            $this->setExcludeClasses()
        );
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        // External links functionality
        add_filter('the_content', array($this, 'processContent'));
        add_action('wp_footer', array($this, 'maybeRenderArrow'));

        // Initialize speculative loading configuration
        $this->initSpeculativeLoading();
    }

    /**
     * Set the exclude classes
     *
     * @return array
     */
    private function setExcludeClasses()
    {
        $classes = array(
            'arrow' => array(
                'wpe-exclude--arrow',
                'wpe-no-arrow'
            ),
            'rel' => array(
                'wpe-exclude--rel',
                'wpe-no-rel'
            ),
            'new-tab' => array(
                'wpe-exclude--new-tab',
                'wpe-no-new-tab'
            ),
            'aria-label' => array(
                'wpe-exclude--aria-label',
                'wpe-no-aria-label'
            ),
            'all' => array(
                'wpe-exclude--link',
                'wpe-no-external-link'
            )
        );

        $exclude_classes = $this->getSetting('exclude_classes');
        if (!empty($exclude_classes)) {
            $custom_classes = array_map('trim', explode(',', $exclude_classes));
            $classes['all'] = array_merge($classes['all'], $custom_classes);
        }

        return $classes;
    }

    /**
     * Get module settings fields configuration.
     *
     * @return array Array of settings field configurations
     */
    public function getSettingsFields()
    {
        $settings = array(
            'tabs' => array(
                array(
                    'id' => 'external_links',
                    'title' => __('External Links', WP_EXTENDED_TEXT_DOMAIN),
                ),
            ),
            'sections' => array(
                array(
                    'tab_id'        => 'external_links',
                    'section_id'    => 'external_links_settings',
                    'section_title' => __('External Links Settings', WP_EXTENDED_TEXT_DOMAIN),
                    'section_description' => __('Control how external links behave on your site.', WP_EXTENDED_TEXT_DOMAIN),
                    'section_order' => 10,
                    'fields' => array(
                        array(
                            'id' => 'exclude_classes',
                            'type' => 'text',
                            'title' => __('Exclude classes', WP_EXTENDED_TEXT_DOMAIN),
                            'description' => __('Enter a comma-separated list of classes to exclude from the external link arrow', WP_EXTENDED_TEXT_DOMAIN),
                            'default' => '',
                        ),
                        array(
                            'id'          => 'disable_arrow',
                            'type'        => 'toggle',
                            'title'       => __('Disable external link arrow', WP_EXTENDED_TEXT_DOMAIN),
                            'description' => __('Note: Not recommended as it may cause accessibility issues.', WP_EXTENDED_TEXT_DOMAIN),
                            'default'     => false,
                        ),
                        array(
                            'id' => 'message',
                            'type' => 'custom',
                            'callback' => array($this, 'renderMessage'),
                            'default' => '',
                        ),
                    ),
                ),
            ),
        );

        // Add speculative loading tab if WordPress 6.8+
        if (version_compare(get_bloginfo('version'), '6.8', '>=')) {
            $settings['tabs'][] = array(
                'id' => 'speculative_loading',
                'title' => __('Speculative Loading', WP_EXTENDED_TEXT_DOMAIN),
            );

            $settings['sections'][] = array(
                'tab_id'        => 'speculative_loading',
                'section_id'    => 'speculative_loading_settings',
                'section_title' => __('Speculative Loading Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Control WordPress 6.8+ speculative loading behavior.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields' => array(
                    array(
                        'id'          => 'disable_speculative_loading',
                        'type'        => 'toggle',
                        'title'       => __('Disable speculative loading', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Completely disable speculative loading feature.', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => false,
                    ),
                    array(
                        'id'          => 'speculative_loading_mode',
                        'type'        => 'select',
                        'title'       => __('Loading Mode', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose how aggressively to preload links.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices'     => array(
                            'auto'       => __('Auto (WordPress Default)', WP_EXTENDED_TEXT_DOMAIN),
                            'prefetch'   => __('Prefetch', WP_EXTENDED_TEXT_DOMAIN),
                            'prerender'  => __('Prerender', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'default'     => 'auto',
                        'show_if'     => array(
                            array(
                                'field' => 'disable_speculative_loading',
                                'operator' => '!==',
                                'value' => array(1),
                            ),
                        ),
                    ),
                    array(
                        'id'          => 'speculative_loading_eagerness',
                        'type'        => 'select',
                        'title'       => __('Loading Eagerness', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose how early to start preloading links.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices'     => array(
                            'auto'        => __('Auto (WordPress Default)', WP_EXTENDED_TEXT_DOMAIN),
                            'conservative' => __('Conservative', WP_EXTENDED_TEXT_DOMAIN),
                            'moderate'    => __('Moderate', WP_EXTENDED_TEXT_DOMAIN),
                            'eager'       => __('Eager', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'default'     => 'auto',
                        'show_if'     => array(
                            array(
                                'field' => 'disable_speculative_loading',
                                'operator' => '!==',
                                'value' => array(1),
                            ),
                        ),
                    ),
                    array(
                        'id'          => 'exclude_paths',
                        'type'        => 'textarea',
                        'title'       => __('Exclude Paths', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter URL patterns to exclude from speculative loading, one per line. Use URL Pattern format (e.g. /cart/*, /checkout/*).', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => '',
                        'show_if'     => array(
                            array(
                                'field' => 'disable_speculative_loading',
                                'operator' => '!==',
                                'value' => array(1),
                            ),
                        ),
                    ),
                    array(
                        'id'          => 'exclude_prerender_paths',
                        'type'        => 'textarea',
                        'title'       => __('Exclude from Prerender', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter URL patterns to exclude only from prerendering, one per line. Use URL Pattern format (e.g. /personalized-area/*).', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => '',
                        'show_if'     => array(
                            array(
                                'field' => 'disable_speculative_loading',
                                'operator' => '!==',
                                'value' => array(1),
                            ),
                        ),
                    ),
                ),
            );
        }

        return $settings;
    }

    /**
     * Render the message about exclusion classes
     */
    public function renderMessage()
    {
        $title = esc_html__('Control external links on a per link basis:', WP_EXTENDED_TEXT_DOMAIN);
        $message = wp_kses(
            sprintf(
                __('Add exclusion classes to links or their parent elements to control how external links are modified. Available classes include: <code>wpe-exclude--link</code>, <code>wpe-exclude--arrow</code>, <code>wpe-exclude--rel</code>, <code>wpe-exclude--new-tab</code>, and <code>wpe-exclude--aria-label</code> for complete exclusion.', WP_EXTENDED_TEXT_DOMAIN),
                Utils::generateTrackedLink('https://wpextended.io/docs/link-manager/', 'link-manager'),
            ),
            array(
                'code' => array(),
                'strong' => array(),
                'a' => array('href' => array(), 'title' => array(), 'target' => array(), 'aria-label' => array())
            )
        );

        $documentation = sprintf(
            'For a complete list of available classes and their usage, please refer to the <a href="%s" target="_blank" aria-label="%s">%s</a>.',
            Utils::generateTrackedLink('https://wpextended.io/docs/link-manager/', 'link-manager'),
            __('Link Manager documentation, opens in a new tab', WP_EXTENDED_TEXT_DOMAIN),
            __('Documentation', WP_EXTENDED_TEXT_DOMAIN)
        );

        printf(
            '<h3>%s</h3><p>%s</p><p>%s</p>',
            $title,
            $message,
            $documentation
        );
    }

    /**
     * Process HTML content to modify external links
     *
     * @param string $content The HTML content to process
     * @return string The modified HTML content
     */
    public function processContent($content)
    {
        if (empty($content)) {
            return $content;
        }

        $dom = new \DOMDocument();

        // Preserve entities
        $dom->substituteEntities = false;

        // Prevent DOCTYPE, HTML and BODY tags from being added automatically
        libxml_use_internal_errors(true);

        // Load the HTML content
        // Handle encoding properly without using deprecated mb_convert_encoding
        $content = '<?xml encoding="UTF-8">' . $content;
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Clear libxml errors
        libxml_clear_errors();

        // Process all links
        $this->processLinks($dom);

        // Save the processed HTML
        $processed_content = $dom->saveHTML();

        return $processed_content;
    }

    /**
     * Process all links in the DOM document
     *
     * @param DOMDocument $dom The DOM document
     */
    private function processLinks(\DOMDocument $dom)
    {
        $links = $dom->getElementsByTagName('a');

        if (empty($links)) {
            return;
        }

        // Avoid issues with removing nodes during iteration by using a reverse loop
        $links_array = [];
        foreach ($links as $link) {
            $links_array[] = $link;
        }

        // Process each link
        foreach ($links_array as $link) {
            $href = $link->getAttribute('href');

            // Skip if href is empty
            if (empty($href)) {
                continue;
            }

            // Skip excluded link types
            if ($this->shouldSkipLink($link, $href)) {
                continue;
            }

            // Check if link has target="_blank", is an external URL, or has window.open() onclick
            $has_window_open = $this->hasWindowOpenOnclick($link);
            if (
                $link->getAttribute('target') === '_blank' ||
                !$this->isInternalUrl($href) ||
                $has_window_open
            ) {
                $this->modifyLink($link, $href, $has_window_open);
            }
        }
    }

    /**
     * Check if a link has an onclick attribute with window.open
     *
     * @param \DOMElement $link The link element
     * @return bool True if the link has window.open in onclick
     */
    private function hasWindowOpenOnclick(\DOMElement $link)
    {
        if (!$link->hasAttribute('onclick')) {
            return false;
        }

        $onclick = $link->getAttribute('onclick');
        return strpos($onclick, 'window.open') !== false;
    }

    /**
     * Check if a link has any of the specified exclusion classes
     *
     * @param \DOMElement $link The link element
     * @param string $type The type of exclusion to check (arrow, rel, new-tab, all)
     * @return bool True if the link has any of the exclusion classes
     */
    private function hasExclusionClass(\DOMElement $link, $type)
    {
        // Check link's classes
        if ($link->hasAttribute('class')) {
            $link_classes = $link->getAttribute('class');
            foreach ($this->exclude_classes[$type] as $class) {
                if (strpos($link_classes, $class) !== false) {
                    return true;
                }
            }
        }

        // Check parent elements' classes
        $current_element = $link->parentNode;
        while ($current_element && $current_element->nodeType === XML_ELEMENT_NODE) {
            if ($current_element->hasAttribute('class')) {
                $parent_classes = $current_element->getAttribute('class');
                foreach ($this->exclude_classes[$type] as $class) {
                    if (strpos($parent_classes, $class) !== false) {
                        return true;
                    }
                }
            }
            $current_element = $current_element->parentNode;
        }

        return false;
    }

    /**
     * Check if a link should be skipped
     *
     * @param \DOMElement $link The link element
     * @param string $href The href attribute value
     * @return bool True if the link should be skipped
     */
    private function shouldSkipLink(\DOMElement $link, $href)
    {
        // Skip mailto: links
        if (strpos($href, 'mailto:') === 0) {
            return true;
        }

        // Skip tel: links
        if (strpos($href, 'tel:') === 0) {
            return true;
        }

        // Skip internal links (but allow those with target="_blank")
        if ($this->isInternalUrl($href) && $link->getAttribute('target') !== '_blank') {
            return true;
        }

        // Check for complete exclusion
        return $this->hasExclusionClass($link, 'all');
    }

    /**
     * Modify a link by adding required attributes and elements
     *
     * @param \DOMElement $link The link element
     * @param string $href The href attribute value
     * @param bool $has_window_open Whether the link has window.open onclick
     */
    private function modifyLink(\DOMElement $link, $href, $has_window_open = false)
    {
        // Add rel attributes if not excluded and link is external
        if (!$this->hasExclusionClass($link, 'rel') && !$this->isInternalUrl($href)) {
            $this->addRelAttributes($link);
        }

        // Add target="_blank" if not excluded and needed
        if (
            !$this->hasExclusionClass($link, 'new-tab') &&
            ($has_window_open || !$this->isInternalUrl($href)) &&
            $link->getAttribute('target') !== '_blank'
        ) {
            $link->setAttribute('target', '_blank');
        }

        // Add or update aria-label
        $this->addAriaLabel($link);

        // Add SVG arrow icon if not globally disabled and not excluded
        if (!$this->getSetting('disable_arrow') && !$this->hasExclusionClass($link, 'arrow')) {
            $this->addSvgArrow($link);
        }
    }

    /**
     * Add rel attributes to link
     *
     * @param \DOMElement $link The link element
     */
    private function addRelAttributes(\DOMElement $link)
    {
        // Skip if rel attributes are excluded
        if ($this->hasExclusionClass($link, 'rel')) {
            return;
        }

        $rel_attributes = ['noopener', 'noreferrer', 'nofollow'];
        $current_rel = $link->getAttribute('rel');
        $current_rel_array = empty($current_rel) ? [] : explode(' ', $current_rel);

        // Add missing rel attributes
        foreach ($rel_attributes as $attr) {
            if (!in_array($attr, $current_rel_array)) {
                $current_rel_array[] = $attr;
            }
        }

        // Set the updated rel attribute
        $link->setAttribute('rel', implode(' ', $current_rel_array));
    }

    /**
     * Add or update aria-label for link
     *
     * @param \DOMElement $link The link element
     */
    private function addAriaLabel(\DOMElement $link)
    {
        // Skip if aria-label is excluded or if new-tab is excluded
        if ($this->hasExclusionClass($link, 'aria-label') || $this->hasExclusionClass($link, 'new-tab')) {
            return;
        }

        /* Translators: This text is appended to the aria-label of external links to indicate that they open in a new tab. */
        $new_tab_text = __(' opens in a new tab', WP_EXTENDED_TEXT_DOMAIN);

        // Get current aria-label or link text
        if ($link->hasAttribute('aria-label')) {
            $aria_label = $link->getAttribute('aria-label');

            // Only append if "opens in a new tab" is not already in the label
            if (strpos($aria_label, $new_tab_text) === false) {
                $link->setAttribute('aria-label', sprintf('%s,%s', $aria_label, $new_tab_text));
            }
        } else {
            // Use link text as base for aria-label
            $link_text = $link->textContent;
            if (!empty($link_text)) {
                $link->setAttribute('aria-label', sprintf('%s,%s', $link_text, $new_tab_text));
            }
        }
    }

    /**
     * Add SVG arrow icon inside link
     *
     * @param \DOMElement $link The link element
     */
    private function addSvgArrow(\DOMElement $link)
    {
        // Check if arrow already exists
        $xpath = new \DOMXPath($link->ownerDocument);
        $existing_arrows = $xpath->query('.//svg[contains(@class, "wpextended-external-link")]', $link);

        if ($existing_arrows->length > 0) {
            return;
        }

        // Create SVG element
        $svg = $link->ownerDocument->createElement('svg');
        $svg->setAttribute('class', 'wpextended-external-link');
        $svg->setAttribute('aria-hidden', 'true');

        $use = $link->ownerDocument->createElement('use');
        $use->setAttribute('xlink:href', '#wpextended-external-link');

        $svg->appendChild($use);

        $link->appendChild($svg);

        // Set flag that we've added at least one arrow
        $this->arrows_added = true;
    }

    /**
     * Checks if a given URL is internal.
     *
     * @param  string $url The URL to check.
     * @return bool True if the URL is internal, false otherwise.
     */
    private function isInternalUrl($url)
    {
        // Trim whitespace from the URL.
        $url = trim($url);

        // Check if the URL starts with a hash link or a relative link starting with '/'.
        if (strpos($url, '#') === 0 || strpos($url, '/') === 0 || strpos($url, '?') === 0) {
            return true;
        }

        // Parse the URL and get its components.
        $parsed_url = parse_url($url);

        // Get the home URL and parse it.
        $home_url = home_url();
        $parsed_home_url = parse_url($home_url);

        // Ensure the parsed URL has a host and scheme for comparison.
        if (!isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
            return false;
        }

        // Compare host and scheme.
        if ($parsed_url['host'] === $parsed_home_url['host'] && $parsed_url['scheme'] === $parsed_home_url['scheme']) {
            return true;
        }

        // Check if the URL starts with the site's home URL.
        if (strpos($url, $home_url) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Render SVG arrow symbol in footer only if arrows were added
     *
     * @return void
     */
    public function maybeRenderArrow()
    {
        // Only output the SVG if at least one arrow was added to the page
        if (!$this->arrows_added || $this->getSetting('disable_arrow')) {
            return;
        }

        ?>
        <style>
            .wpextended-external-link {
                width: 1em;
                height: 1em;
            }
        </style>
        <svg class="wpextended-external-link" aria-hidden="true" style="position: absolute; width: 0; height: 0; overflow: hidden;">
            <symbol viewBox="0 0 256 256" id="wpextended-external-link"><!-- Icon from Phosphor by Phosphor Icons - https://github.com/phosphor-icons/core/blob/main/LICENSE -->
                <path fill="currentColor" d="M200 64v104a8 8 0 0 1-16 0V83.31L69.66 197.66a8 8 0 0 1-11.32-11.32L172.69 72H88a8 8 0 0 1 0-16h104a8 8 0 0 1 8 8"></path>
            </symbol>
        </svg>
        <?php
    }

    /**
     * Maybe disable WordPress 6.8+ pre-link loading
     *
     * @param array $urls Array of URLs to be preloaded
     * @param string $relation_type The relation type of the URLs
     * @return array Modified array of URLs
     */
    public function maybeDisablePreloadLinks($urls, $relation_type)
    {
        return $urls;
    }

    /**
     * Initialize speculative loading configuration
     */
    private function initSpeculativeLoading()
    {
        if (!version_compare(get_bloginfo('version'), '6.8', '>=')) {
            return;
        }

        // Only initialize if not disabled
        if (!$this->getSetting('disable_speculative_loading')) {
            add_filter('wp_speculation_rules_configuration', array($this, 'configureSpeculativeLoading'));
            add_filter('wp_speculation_rules_href_exclude_paths', array($this, 'getExcludedPaths'), 10, 2);

            return;
        }

        add_filter('wp_speculation_rules_configuration', '__return_null', 10);
    }

    /**
     * Configure speculative loading behavior
     */
    public function configureSpeculativeLoading($config)
    {
        if (!is_array($config)) {
            return $config;
        }

        $mode = $this->getSetting('speculative_loading_mode');
        $eagerness = $this->getSetting('speculative_loading_eagerness');

        if ($mode !== 'auto') {
            $config['mode'] = $mode;
        }
        if ($eagerness !== 'auto') {
            $config['eagerness'] = $eagerness;
        }

        return $config;
    }

    /**
     * Get excluded paths for speculative loading
     */
    public function getExcludedPaths($href_exclude_paths, $mode)
    {
        $exclude_paths = $this->getSetting('exclude_paths');
        $exclude_prerender_paths = $this->getSetting('exclude_prerender_paths');

        // Ensure $href_exclude_paths is an array
        if (!is_array($href_exclude_paths)) {
            $href_exclude_paths = array();
        }

        // Process exclude paths
        if (!empty($exclude_paths)) {
            $paths = array_filter(array_map('trim', explode("\n", $exclude_paths)));
            if (!empty($paths)) {
                $href_exclude_paths = array_merge($href_exclude_paths, $paths);
            }
        }

        // Process prerender exclude paths
        if ($mode === 'prerender' && !empty($exclude_prerender_paths)) {
            $paths = array_filter(array_map('trim', explode("\n", $exclude_prerender_paths)));
            if (!empty($paths)) {
                $href_exclude_paths = array_merge($href_exclude_paths, $paths);
            }
        }

        return $href_exclude_paths;
    }
}
