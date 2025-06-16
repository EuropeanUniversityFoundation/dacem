(function ($, Drupal) {
  Drupal.behaviors.universityMenuBehavior = {
    attach: function (context, settings) {
      $('.mobile-menu-toggle', context).once('universityMenuBehavior').click(function () {
        $('.navbar-collapse', context).collapse('toggle');
      });
    }
  };
})(jQuery, Drupal);

