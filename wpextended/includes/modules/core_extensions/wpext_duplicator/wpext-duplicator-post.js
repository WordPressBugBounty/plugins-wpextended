window.addEventListener('DOMContentLoaded', function(){
  const __ = wp.i18n.__;
  const PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
  const MainDashboardButton = wp.editPost.__experimentalMainDashboardButton;
  const registerPlugin = wp.plugins.registerPlugin;
  const el = wp.element.createElement;
  const buttonProps = {
    href: ajaxurl + '?action=wp-extended-duplicate-post&post_ID=' + wpext_post.ID+'&wpext_nonce=' + wpext_post_nonce.wpext_post_nonce,
    className: 'components-button is-secondary',
    'data-duplicate': ''
  };
  registerPlugin( 'wpext-duplicator', {
    render(){
      return wp.element.createElement(
          PluginPostStatusInfo,
          {
              className: 'my-plugin-post-status-info'
          },
          wp.element.createElement(
            'a',
            buttonProps,
            __("Duplicate") + " " + wpext_post.post_type
          )
      );
    }
  });
 registerPlugin( 'wpext-duplicator-main-btn', {
    render(){
      return el(
        MainDashboardButton,
        {},
        [el( 
          'a',
          buttonProps,
          __("Duplicate")
        )]
      )       
    }
  });

});