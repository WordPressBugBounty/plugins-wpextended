<?php
if (!defined('ABSPATH')) {
    die();
}

$data = $this->getLayoutData();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <title><?php echo esc_html($data['meta']['title']); ?></title>
    <meta charset="<?php esc_attr(bloginfo('charset')); ?>" />
    <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, minimum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="<?php echo esc_attr($data['meta']['description']); ?>" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
    <meta property="og:title" content="<?php echo esc_attr($data['meta']['title']); ?>" />
    <meta property="og:url" content="<?php echo esc_url(home_url()); ?>" />
    <meta property="og:description" content="<?php echo esc_attr($data['meta']['description']); ?>" />

    <!-- Twitter -->
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="<?php echo esc_attr($data['meta']['title']); ?>" />
    <meta name="twitter:description" content="<?php echo esc_attr($data['meta']['description']); ?>" />

    <!-- Favicon -->
    <?php if (function_exists('has_site_icon') && has_site_icon()) : ?>
        <?php wp_site_icon(); ?>
    <?php endif; ?>

    <!-- Preconnect to external resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- DNS prefetch for performance -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">

    <?php
    // Load CSS files
    if (isset($data['css_files'])) {
        foreach ($data['css_files'] as $handle => $css_file) {
            Wpextended\Includes\Utils::enqueueStyle(sprintf('wpextended-maintenance-mode-%s', $handle), $css_file);
        }
    }
    ?>

    <?php wp_head(); ?>

    <?php if ($data['layout']['type'] !== 'page') : ?>
        <style id="wpextended-maintenance-mode-variables">
            :root {
                --wpext-headline-color: <?php echo esc_attr($data['content']['headline']['color']); ?>;
                --wpext-body-color: <?php echo esc_attr($data['content']['body']['color']); ?>;
                --wpext-footer-color: <?php echo esc_attr($data['content']['footer']['color']); ?>;
                --wpext-background-color: <?php echo esc_attr($data['background']['color']); ?>;
                --wpext-logo-width: <?php echo esc_attr($data['header']['logo']['dimensions']['width']); ?>px;
                <?php if ($data['background']['enable_image']) :
                    ?>--wpext-background-image: url('<?php echo esc_url($data['background']['image']); ?>');
                <?php endif; ?>
            }
        </style>
    <?php endif; ?>


    <?php do_action('wpextended/maintenance-mode/head'); ?>
</head>

<body <?php body_class('wpextended__body'); ?> data-background-image="<?php echo esc_attr($data['background']['enable_image']) ? 'true' : 'false'; ?>">
    <?php wp_body_open(); ?>
