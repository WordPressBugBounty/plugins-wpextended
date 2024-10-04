<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Target_Blank extends Wp_Extended_Export {

  public function __construct() {
    parent::__construct();
    add_filter('the_content', array($this, 'wpext_open_links_in_new_tab'));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Target_Blank( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  public function wpext_open_links_in_new_tab($content){
     if ( !empty($content) ) {
      // regex pattern for "a href"
      $regexp = "<a\\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>";
            
        if ( preg_match_all( "/{$regexp}/siU", $content, $matches, PREG_SET_ORDER ) ) {
            // $matches might contain parts of $content that has links (a href)
            preg_match_all( "/{$regexp}/siU", $content, $matches, PREG_SET_ORDER );
            if ( is_array( $matches ) ) {
                $i = 0;
                foreach ( $matches as $match ) {
                    $original_tag = $match[0];
                    $tag = $match[0];
                    $url = $match[2];
                    if ( false !== strpos( $url, get_site_url() ) ) {
                        // Internal link. Do nothing.
                    } elseif ( false === strpos( $url, 'http' ) ) {
                        
                    } else {
                        // Regex pattern for target="_blank|parent|self|top"
                        $pattern = '/target\\s*=\\s*"\\s*_(blank|parent|self|top)\\s*"/';
                        // If there's no 'target="_blank|parent|self|top"' in $tag, add target="blank"
                        if ( 0 === preg_match( $pattern, $tag ) ) {
                            // Replace closing > with ' target="_blank">'
                            $tag = substr_replace( $tag, ' target="_blank">', -1 );
                        }
                        // If there's no 'rel' attribute in $tag, add rel="noopener noreferrer nofollow"
                        $pattern = '/rel\\s*=\\s*\\"[a-zA-Z0-9_\\s]*\\"/';
                        
                        if ( 0 === preg_match( $pattern, $tag ) ) {
                            // Replace closing > with ' rel="noopener noreferrer nofollow">'
                            $tag = substr_replace( $tag, ' rel="noopener noreferrer nofollow">', -1 );
                        } else {
                            // replace rel="noopener" with rel="noopener noreferrer nofollow"
                            if ( false !== strpos( $tag, 'noopener' ) && false === strpos( $tag, 'noreferrer' ) && false === strpos( $tag, 'nofollow' ) ) {
                                $tag = str_replace( 'noopener', 'noopener noreferrer nofollow', $tag );
                            }
                        }
                        $content = str_replace( $original_tag, $tag, $content );
                    }
                    
                    $i++;
                }
            }
        
        }
      
      }
          
      return $content;
  }
}
Wp_Extended_Target_Blank::init(); 