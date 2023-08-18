(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.stanfordSamlauth = {
    attach: function (context) {
      $('a.samlauth-login', context).each(function () {
        const href = $(this).attr('href');
        const url = new URL(href.startsWith('/') ? window.location.origin + href : href);

        if (url.searchParams.size === 0 && window.location.search) {
          $(this).attr('href', href + window.location.search);
        }
      });
    },
  };

})(jQuery, Drupal);
