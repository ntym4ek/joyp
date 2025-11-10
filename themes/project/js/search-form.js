/**
 * @file
 * форма поиска в шапке
 */

(($, Drupal,  once) => {
  /**
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   */
  Drupal.behaviors.searchForm = {
    attach(context) {

      once('search-form-once', '.extn-form-search-form', context).forEach(
        (element) => {
          let $wrapper = $(element).closest('.page-header__search');
          let $input = $(element).find('[data-drupal-selector="edit-key"]');

          // открытие и закрытие строки ввода
          $wrapper.find('.search-icon').on('click', () => {
            $wrapper.addClass('open');
            $input.focus();
          });
          $wrapper.find('.close-icon').on('click', () => {
            $wrapper.removeClass('open');
          });

          // отложенное срабатывание ajax при вводе текста
          let timeoutId;
          $input.on('keyup paste', function () {
            clearTimeout(timeoutId);

            let key = $input.val();
            if (key.length > 3) {
              timeoutId = setTimeout(function () {
                $input.trigger('custom:delayedInput');
              }, 800);
            }
          });
        }
      );

    },
  };

})(jQuery, Drupal, once);
