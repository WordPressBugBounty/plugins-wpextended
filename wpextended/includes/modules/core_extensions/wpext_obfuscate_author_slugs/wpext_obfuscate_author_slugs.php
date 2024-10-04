<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Obfuscate_Author_Slugs extends Wp_Extended {

  public function __construct() {
    parent::__construct();
    add_action( 'pre_get_posts', array($this, 'wpext_menipulate_author_query' ), 10 );
    add_filter('author_link',  array($this , 'wpext_menipulate_author_link' ),10,3);
    add_filter('rest_prepare_user', array($this, 'wpext_menipulate_json_users' ),10,3);
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Obfuscate_Author_Slugs( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function wpext_menipulate_author_query( $query )
    {
      // Check if it's a query for author data, and that 'author_name' is not empty
      if ( $query->is_author() && $query->query_vars['author_name'] != '' ) {
          // Check for character(s) representing a hexadecimal digit
          if ( ctype_xdigit( $query->query_vars['author_name'] ) ) {
              $user = get_user_by( 'id', $this->wpext_decrypt_url( $query->query_vars['author_name'] ) );
              if ( $user ) {
                  $query->set( 'author_name', $user->user_nicename );
              } else { $query->is_404 = true;
                  $query->is_author = false;
                  $query->is_archive = false;
              }
          } else {
              // No hexadecimal digit detected in URL
              $query->is_404 = true;
              $query->is_author = false;
              $query->is_archive = false;
          }
      
      }
      return ;
  }
  
  /**
   * Replace author slug in author link to encrypted value. Used by author_link filter.
   * 
  */
  public function wpext_menipulate_author_link( $link, $user_id, $author_slug )
  {
      $encrypted_author_slug = $this->wpext_encrypt_url( $user_id );
      return str_replace( '/' . $author_slug, '/' . $encrypted_author_slug, $link );
  }

  /**
   * Helper function to return an encrypted user ID, which will then be used to replace the author slug.
   * 
   */
  private function wpext_encrypt_url( $user_id )
  {
      // Returns encrypted encrypted author slug from user ID, e.g. encrypt user ID 3 to author slug 4e3062d8c8626a14
      return bin2hex( openssl_encrypt(
          base_convert( $user_id, 10, 36 ),
          'DES-EDE3',
          md5( sanitize_text_field( $_SERVER['SERVER_ADDR'] ) . plugins_url( '/', __FILE__ ) ),
          OPENSSL_RAW_DATA
      ) );
  }

   /**
   * Replace author slug in REST API /users/ endpoint to encrypted value. Used by rest_prepare_user filter.
   *
   */
  public function wpext_menipulate_json_users( $response, $user, $request )
  {
      $data = $response->get_data();
      $data['slug'] = $this->wpext_encrypt_url( $data['id'] );
      $response->set_data( $data );
      return $response;
  }

  /**
   * Helper function to wpext_decrypt_url an (encrypted) author slug and returns the user ID
   * 
   */
  private function wpext_decrypt_url( $encrypted_author_slug )
  {
      // Returns user ID, e.g. wpext_decrypt_url author slug 4e3062d8c8626a14 into user ID 
      return base_convert( openssl_decrypt(
          pack( 'H*', $encrypted_author_slug ),
          'DES-EDE3',
          md5( sanitize_text_field( $_SERVER['SERVER_ADDR'] ) . plugins_url( '/', __FILE__ ) ),
          OPENSSL_RAW_DATA
      ), 36, 10 );
  }

}
Wp_Extended_Obfuscate_Author_Slugs::init(); 