<?php

namespace Wpextended\Modules\HideAuthorSlugs;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Notices;
use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;

/**
 * HideAuthorSlugs module Bootstrap class.
 *
 * Enhances WordPress security by encrypting author slugs in URLs and REST API responses.
 * This prevents user enumeration attacks by making author URLs unpredictable.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('hide-author-slugs');
    }

    /**
     * Initialize the module.
     * Sets up hooks to modify author URLs and queries.
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('pre_get_posts', [$this, 'handleAuthorQuery']);
        add_filter('author_link', [$this, 'encryptAuthorLink'], 10, 3);
        add_filter('rest_prepare_user', [$this, 'encryptRestApiUser'], 10, 3);
        add_action('admin_notices', [$this, 'adminNotices']);
    }

    /**
     * Handle author archive queries by decrypting author slugs.
     * Redirects to 404 if the slug is invalid or cannot be decrypted.
     *
     * @param WP_Query $query The WordPress query object
     *
     * @return void
     */
    public function handleAuthorQuery($query)
    {
        if (!$query->is_author() || empty($query->query_vars['author_name'])) {
            return;
        }

        $author_slug = $query->query_vars['author_name'];

        // Return 404 if slug is not a valid hexadecimal string
        if (!ctype_xdigit($author_slug)) {
            $this->set404($query);
            return;
        }

        // Attempt to decrypt the author slug
        $user_id = $this->decryptAuthorSlug($author_slug);
        $user = get_user_by('id', $user_id);

        if (!$user) {
            $this->set404($query);
            return;
        }

        $query->set('author_name', $user->user_nicename);
    }

    /**
     * Set query to return a 404 page.
     *
     * @param WP_Query $query The WordPress query object
     *
     * @return void
     */
    private function set404($query)
    {
        $query->is_404 = true;
        $query->is_author = false;
        $query->is_archive = false;
    }

    /**
     * Replace author slug with encrypted version in author URLs.
     *
     * @param string $link        The author's URL
     * @param int    $user_id     The author's user ID
     * @param string $author_slug The author's slug
     *
     * @return string Modified URL with encrypted author slug
     */
    public function encryptAuthorLink($link, $user_id, $author_slug)
    {
        $encrypted_slug = $this->encryptAuthorSlug($user_id);
        return str_replace('/' . $author_slug, '/' . $encrypted_slug, $link);
    }

    /**
     * Encrypt author slugs in REST API responses.
     *
     * @param WP_REST_Response $response The response object
     * @param WP_User          $user     The user object
     * @param WP_REST_Request  $request  The request object
     *
     * @return WP_REST_Response Modified response with encrypted slug
     */
    public function encryptRestApiUser($response, $user, $request)
    {
        $data = $response->get_data();
        $data['slug'] = $this->encryptAuthorSlug($data['id']);
        $response->set_data($data);

        return $response;
    }

    /**
     * Encrypt a user ID to create a secure author slug.
     * Uses 3DES encryption with a server-specific key.
     *
     * @param int $user_id The user ID to encrypt
     *
     * @return string Encrypted hexadecimal string
     */
    private function encryptAuthorSlug($user_id)
    {
        $key = $this->getEncryptionKey();
        $base_36_id = base_convert($user_id, 10, 36);

        $encrypted = openssl_encrypt(
            $base_36_id,
            'DES-EDE3',
            $key,
            OPENSSL_RAW_DATA
        );

        return bin2hex($encrypted);
    }

    /**
     * Decrypt an encrypted author slug back to a user ID.
     *
     * @param string $encrypted_slug The encrypted author slug
     *
     * @return int|false The decrypted user ID, or false on failure
     */
    private function decryptAuthorSlug($encrypted_slug)
    {
        $key = $this->getEncryptionKey();

        $decrypted = openssl_decrypt(
            pack('H*', $encrypted_slug),
            'DES-EDE3',
            $key,
            OPENSSL_RAW_DATA
        );

        if ($decrypted === false) {
            return false;
        }

        return base_convert($decrypted, 36, 10);
    }

    /**
     * Get the encryption key based on server address and plugin path.
     * This ensures the key is unique to each installation.
     *
     * @return string MD5 hash of the server-specific key
     */
    private function getEncryptionKey()
    {
        return md5(
            sanitize_text_field($_SERVER['SERVER_ADDR']) .
                plugins_url('/', __FILE__)
        );
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function adminNotices()
    {
        // Check if User Enumeration module is active
        if (!Modules::isModuleLoaded('user-enumeration')) {
            return;
        }

        Notices::add(array(
            'message' => sprintf(
                __('Warning: Hide Author Slugs module will not work properly while the User Enumeration module is enabled. Please <strong><a href="%s">disable the User Enumeration module</a></strong> for Hide Author Slugs to function correctly.', WP_EXTENDED_TEXT_DOMAIN),
                Utils::getModulePageLink('modules', array('search' => 'user enumeration', 'status' => 'all'))
            ),
            'type' => 'warning',
            'id' => 'hide_author_slugs_conflict',
            'persistent' => false,
            'dismissible' => false
        ));
    }
}
