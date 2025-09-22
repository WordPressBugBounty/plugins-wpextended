<?php

namespace Wpextended\Modules\DisableXmlRpc;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('disable-xml-rpc');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        // Block XML-RPC requests immediately
        $this->blockXmlRpcRequests();

        // Disable XML-RPC completely
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', '__return_empty_array');

        // Remove X-Pingback headers
        add_filter('wp_headers', [$this, 'removeXPingback']);

        // Disable pings
        add_filter('pings_open', '__return_false', 9999);

        // Prevent XML-RPC from being enabled via options
        add_filter('pre_update_option_enable_xmlrpc', '__return_false');
        add_filter('pre_option_enable_xmlrpc', '__return_zero');

        // Remove pingback links from HTML output
        add_filter('wp_head', [$this, 'removePingbackLinks'], 9999);

        // Block XML-RPC method calls
        add_action('xmlrpc_call', [$this, 'blockXmlRpcCall'], 1);
    }

    /**
     * Remove X-Pingback headers
     *
     * @param array $headers
     * @return array
     */
    public function removeXPingback($headers)
    {
        unset($headers['X-Pingback'], $headers['x-pingback']);
        return $headers;
    }

    /**
     * Remove pingback links from HTML output
     *
     * @return void
     */
    public function removePingbackLinks()
    {
        ob_start(function ($html) {
            preg_match_all('#<link[^>]+rel=["\']pingback["\'][^>]+?\/?>#is', $html, $links, PREG_SET_ORDER);
            if (!empty($links)) {
                foreach ($links as $link) {
                    $html = str_replace($link[0], "", $html);
                }
            }
            return $html;
        });
    }

    /**
     * Block XML-RPC requests
     *
     * @return void
     */
    public function blockXmlRpcRequests()
    {
        // Check if this is an XML-RPC request
        if (!$this->isXmlRpcRequest()) {
            return;
        }

        // Block immediately with 403 Forbidden
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo __('XML-RPC is disabled.', WP_EXTENDED_TEXT_DOMAIN);
        exit();
    }

    /**
     * Check if current request is XML-RPC
     *
     * @return bool
     */
    private function isXmlRpcRequest()
    {
        // Check if XMLRPC_REQUEST constant is defined
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return true;
        }

        // Check if we're accessing xmlrpc.php directly
        if (isset($_SERVER['SCRIPT_FILENAME']) && 'xmlrpc.php' === basename($_SERVER['SCRIPT_FILENAME'])) {
            return true;
        }

        // Check if the request is to xmlrpc.php
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'xmlrpc.php') !== false) {
            return true;
        }

        // Check if this is a POST request with XML-RPC content
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'text/xml') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * Block XML-RPC calls (WordPress method calls)
     *
     * @return void
     */
    public function blockXmlRpcCall()
    {
        $this->blockXmlRpcRequests();
    }
}
