<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}
?>
 <?php $mentinance_record = get_option('wpext-maintanance_mode' ); ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <title><?php if(!empty($mentinance_record['site_title'])){ echo esc_html($mentinance_record['site_title']); } ?> </title>
  <meta charset="<?php esc_attr( bloginfo( 'charset' ) ); ?>" />
  <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, minimum-scale=1">
  <meta name="description" content="<?php echo get_bloginfo( 'description' );?>"/>
  <meta http-equiv="X-UA-Compatible" content="<?php echo get_bloginfo( 'description' );?>"/>
  <meta property="og:site_name" content="<?php echo get_bloginfo( 'name' ); ?>">  
  <meta property="og:title" content="<?php echo get_bloginfo( 'name' ); ?>"/>
  <meta property="og:type" content="Maintenance"/>
  <meta property="og:url" content="<?php echo esc_url( site_url() ); ?>"/>
  <meta property="og:description" content="<?php echo get_bloginfo( 'description' );?>"/>
  <link rel="stylesheet" type="text/css" href='<?php echo plugin_dir_url( __FILE__ ) . "css/wpext_maintenance_mode.css?ver="?><?php echo WP_EXTENDED_VERSION; ?>'>
  <link rel="pingback" href="<?php esc_url( bloginfo( 'pingback_url' ) ); ?>" />
  <style>
    <?php
    $text_color = '#ffffff';
    $bg_color_code = '#ffffff';
    if(!empty($mentinance_record['text_color_code'])){ $text_color =  $mentinance_record['text_color_code']; } 
    $logo_height = 150;
    if(!empty($mentinance_record['logo_height'])){ $logo_height =  $mentinance_record['logo_height']+100; } 

    if(($mentinance_record['wpext_backgroung'] == 'wpext_bgcolor' ) && !empty($mentinance_record['bg_color_code'])){ $bg_color_code =  $mentinance_record['bg_color_code']; } 
    ?>
    body,body.wpext_maintenance a{
      color: <?php echo $text_color; ?>;
    }
    body,body.wpext_maintenance h2{
     color: <?php if(!empty($mentinance_record['headline_color'])) {echo $mentinance_record['headline_color'];} ?>;
    }
    body,body.wpext_maintenance .wpext_maintinance_description{
     color: <?php if(!empty($mentinance_record['description_color'])) {echo $mentinance_record['description_color'];} ?>;
    }
     body,body.wpext_maintenance footer center{
     color: <?php if(!empty($mentinance_record['footer_text_color'])) {echo $mentinance_record['footer_text_color'];} ?>;
    }
    <?php if( ($mentinance_record['wpext_backgroung'] == 'wpext_bgimg') && !empty($mentinance_record['coming_img']) ){ ?>
      body > .main-container:after{ content: "";  background-color: <?php echo $bg_color_code; ?>; opacity: 0.4;  } <?php } ?>
  </style>
</head>
 <body class="wpext_maintenance" style="background-color:<?php if( (!empty($mentinance_record['wpext_backgroung']) && $mentinance_record['wpext_backgroung'] == 'wpext_bgcolor' ) && !empty($mentinance_record['bg_color_code'])){ echo $mentinance_record['bg_color_code']; } ?>">
 <?php do_action( 'before_wpext_main_container' ); ?>
 <div class="main-container">
  <?php do_action( 'before_wpext_content_section' ); ?>
  <div id="wrapper">
    <div class="center logotype">
      <header>
        <?php if(($mentinance_record['wpext_logo_option'] == '1') && !empty($mentinance_record['header_logo'])){
          echo '<div class="wpext_header_logo">
          <img src="'.$mentinance_record['header_logo'].'" style="width:'.$mentinance_record['logo_width'].'px; height:'.$mentinance_record['logo_height'].'px"></div>'; 
        } 
        else {
          echo '<div class="wpext-logo-text" rel="home"><h1 class="site-title">' . esc_html(get_bloginfo('name')) . '</h1></div>';
        }?>
      </header>
    </div>
    <div id="content" class="site-content"> 
      <div class="center">
        <h2 class="heading"><?php echo $mentinance_record['site_heading']; ?></h2>
        <div class="wpext_maintinance_description">
         <?php if($mentinance_record['discription']) { echo wpautop(wp_kses_post(stripslashes($mentinance_record['discription'])), true); } ?>
      </div>
      </div>
    </div>
  </div> <!-- end wrapper -->
  <footer>
    <div class="center">
      <?php do_action( 'before_wpext_footer_section' ); ?>
      <?php echo $mentinance_record['footer_text']; ?>
      <?php do_action( 'after_wpext_footer_section' ); ?>
    </div>
  </footer>
  <?php do_action( 'after_wpext_content_section' ); ?>
  <picture class="wpext-bg-img">
    <?php if(($mentinance_record['wpext_backgroung'] == 'wpext_bgimg') && !empty($mentinance_record['coming_img'])){ ?>
     <source media="(max-width: 100vh)" srcset="<?php echo $mentinance_record['coming_img']; ?>">
      <img src="<?php echo $mentinance_record['coming_img']; ?>">
    <?php } ?>
  </picture>
</div> 
</body>
</html>