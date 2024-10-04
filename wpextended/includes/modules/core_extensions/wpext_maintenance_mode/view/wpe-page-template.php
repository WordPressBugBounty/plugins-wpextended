<?php
/*
* Template Name: WP Extended
* Template Post Type: post, page, product, your-custom-post-name
*/ 

defined( 'ABSPATH' ) || exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php do_action( 'wpmm_head' ); ?>
	<?php wp_head(); ?>
</head>
<body  <?php body_class(); ?>>
	<?php
	wp_body_open();
	the_post();
	the_content();
	?>
	<script type='text/javascript'>
		var wpmmVars = {"ajaxURL": "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"};
	</script>

	<?php
	wp_footer();
	?>
</body>
</html>
	<?php
	exit();
 
