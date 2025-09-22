<?php

namespace Wpextended\Modules\PixelTagManager;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct("pixel-tag-manager");
    }

    /**
     * Initialize module hooks
     */
    protected function init()
    {
        add_action("wp_head", [$this, "renderTrackingScripts"]);
    }

    /**
     * Get module settings fields
     *
     * @return array Settings configuration
     */
    protected function getSettingsFields()
    {
        $settings = array();

        $settings["tabs"][] = array(
            "id" => "settings",
            "title" => __("Pixel Tag Manager", WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings["sections"] = array(
            array(
                "tab_id" => "settings",
                "section_id" => "pixel-tag-manager",
                "section_title" => __("Settings", WP_EXTENDED_TEXT_DOMAIN),
                "section_order" => 10,
                "fields" => array(
                    array(
                        "id" => "google-analytics",
                        "title" => __(
                            "Google Analytics",
                            WP_EXTENDED_TEXT_DOMAIN
                        ),
                        "type" => "text",
                        "placeholder" => __(
                            "Enter Google Analytics tracking ID",
                            WP_EXTENDED_TEXT_DOMAIN
                        ),
                        "description" => sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            "https://support.google.com/analytics/answer/9539598?hl=en",
                            __(
                                "How to find the tracking ID",
                                WP_EXTENDED_TEXT_DOMAIN
                            )
                        ),
                    ),
                    array(
                        "id" => "facebook-pixel",
                        "title" => __(
                            "Facebook Pixel",
                            WP_EXTENDED_TEXT_DOMAIN
                        ),
                        "type" => "text",
                        "placeholder" => __(
                            "Enter Facebook Pixel ID",
                            WP_EXTENDED_TEXT_DOMAIN
                        ),
                        "description" => sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            "https://en-gb.facebook.com/business/help/952192354843755?id=1205376682832142",
                            __(
                                "How to find the Facebook pixel ID",
                                WP_EXTENDED_TEXT_DOMAIN
                            )
                        ),
                    ),
                    array(
                        "id" => "pinterest-tag",
                        "title" => __("Pinterest Tag", WP_EXTENDED_TEXT_DOMAIN),
                        "type" => "text",
                        "placeholder" => __(
                            "Enter Pinterest Tag ID",
                            WP_EXTENDED_TEXT_DOMAIN
                        ),
                        "description" => sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            "https://help.pinterest.com/en/business/article/install-the-pinterest-tag",
                            __(
                                "How to find the Pinterest tag ID",
                                WP_EXTENDED_TEXT_DOMAIN
                            )
                        ),
                    ),
                ),
            ),
        );

        return $settings;
    }

    /**
     * Validate tracking ID
     *
     * @param string $id The ID to validate
     * @param string $type The type of ID (google-analytics, facebook-pixel, pinterest-tag)
     * @return bool Whether the ID is valid
     */
    private function isValidTrackingId($id, $type)
    {
        if (empty($id)) {
            return false;
        }

        $validation_rules = array(
            "google-analytics" => '/^G-[A-Z0-9]{10,}$/',
            "facebook-pixel" => '/^\d+$/',
            "pinterest-tag" => '/^\d+$/',
        );

        return isset($validation_rules[$type]) &&
            preg_match($validation_rules[$type], $id);
    }

    /**
     * Validate settings
     *
     * @param array $validations Array of validation errors
     * @param array $input The input data to validate
     * @return array Updated validation errors
     */
    public function validate($validations, $input)
    {
        if (empty($input)) {
            return $validations;
        }

        $validation_rules = array(
            "google-analytics" => array(
                "error" => __(
                    "Invalid Google Analytics tracking ID format. Please use a GA4 tracking ID (G-XXXXXXXXXX)",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
            ),
            "facebook-pixel" => array(
                "error" => __(
                    "Facebook Pixel ID must contain only numbers",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
            ),
            "pinterest-tag" => array(
                "error" => __(
                    "Pinterest Tag ID must contain only numbers",
                    WP_EXTENDED_TEXT_DOMAIN
                ),
            ),
        );

        foreach ($validation_rules as $field => $rule) {
            if (empty($input[$field])) {
                continue;
            }

            if (!$this->isValidTrackingId($input[$field], $field)) {
                $validations[] = array(
                    "field" => $field,
                    "code" => sprintf("invalid_%s", $field),
                    "message" => $rule["error"],
                    "type" => "error",
                    );
            }
        }

        return $validations;
    }

    /**
     * Render all tracking scripts
     */
    public function renderTrackingScripts()
    {
        $google_analytics = $this->getSetting("google-analytics");
        $facebook_pixel = $this->getSetting("facebook-pixel");
        $pinterest_tag = $this->getSetting("pinterest-tag");

        $this->renderGoogleAnalytics($google_analytics);
        $this->renderFacebookPixel($facebook_pixel);
        $this->renderPinterestTag($pinterest_tag);
    }

    /**
     * Render Google Analytics tracking script
     *
     * @param string $tracking_id Google Analytics tracking ID
     */
    private function renderGoogleAnalytics($tracking_id)
    {
        if (!$this->isValidTrackingId($tracking_id, "google-analytics")) {
            return;
        } ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($tracking_id); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];

      function gtag() {
        dataLayer.push(arguments);
      }
      gtag("js", new Date());
      gtag("config", <?php echo wp_json_encode($tracking_id); ?>);
    </script>
        <?php
    }

    /**
     * Render Facebook Pixel tracking script
     *
     * @param string $pixel_id Facebook Pixel ID
     */
    private function renderFacebookPixel($pixel_id)
    {
        if (!$this->isValidTrackingId($pixel_id, "facebook-pixel")) {
            return;
        } ?>
    <script>
      ! function(f, b, e, v, n, t, s) {
        if (f.fbq) return;
        n = f.fbq = function() {
          n.callMethod ?
            n.callMethod.apply(n, arguments) : n.queue.push(arguments)
        };
        if (!f._fbq) f._fbq = n;
        n.push = n;
        n.loaded = !0;
        n.version = '2.0';
        n.queue = [];
        t = b.createElement(e);
        t.async = !0;
        t.src = v;
        s = b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t, s)
      }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', <?php echo wp_json_encode($pixel_id); ?>);
      fbq('track', 'PageView');
    </script>
    <noscript>
      <img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1" />
    </noscript>
        <?php
    }

    /**
     * Render Pinterest Tag tracking script
     *
     * @param string $tag_id Pinterest Tag ID
     */
    private function renderPinterestTag($tag_id)
    {
        if (!$this->isValidTrackingId($tag_id, "pinterest-tag")) {
            return;
        } ?>
    <script>
      ! function(e) {
        if (!window.pintrk) {
          window.pintrk = function() {
            window.pintrk.queue.push(Array.prototype.slice.call(arguments))
          };
          var n = window.pintrk;
          n.queue = [];
          n.version = "3.0";
          var t = document.createElement("script");
          t.async = true;
          t.src = e;
          var r = document.getElementsByTagName("script")[0];
          r.parentNode.insertBefore(t, r);
        }
      }("https://s.pinimg.com/ct/core.js");
      pintrk('load', <?php echo wp_json_encode($tag_id); ?>);
      pintrk('page');
    </script>
    <noscript>
      <img height="1" width="1" style="display:none;" alt=""
        src="https://ct.pinterest.com/v3/?tid=<?php echo esc_attr($tag_id); ?>&noscript=1" />
    </noscript>
        <?php
    }
}
