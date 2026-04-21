(function($) {
  $(function() {
    // Remove admin notice
    $('[data-wcb-remove-admin-notice]').click(function(e) {
      var $anchor = $(this);
      if($anchor.hasClass('wcb-nolink')) {
        e.preventDefault();
      }
      var notice = $anchor.data('wcb-remove-admin-notice');
      $.post(ajaxurl, {
        'action': 'wcb_remove_admin_notice',
        'notice_key': notice
      });

      $(this).parents('.notice').hide();
    });
  });
})(jQuery);
