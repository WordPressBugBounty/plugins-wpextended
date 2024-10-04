window.addEventListener('DOMContentLoaded', function(){
  const __ = wp.i18n.__;
  const PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
  const MainDashboardButton = wp.editPost.__experimentalMainDashboardButton;
  const registerPlugin = wp.plugins.registerPlugin;
  const el = wp.element.createElement;

  const buttonProps = {
    href: 'post-new.php?post_type=' + wpext_post.post_type,
    className: 'components-button is-secondary',
  };

  registerPlugin( 'wpext-quick-add-post', {
    render(){
      return el(
        MainDashboardButton,
        {},
        [el( 
          'a',
          buttonProps,
          __("New")
        )]
      )       
    }
  });

});