/**
 * @file
 * Вспомогательные функции
 */

(($, Drupal, settings, once) => {
  /**
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   */
  Drupal.behaviors.project = {
    attach(context) {

      // Повесить обработчик для кнопок закрытия диалога,
      // создаваемых через OpenModalDialogCommand
      once('body-once', '#buttonDialogClose', context).forEach(
        (element) => {
          element.addEventListener("click", function() {
            document.querySelector('.ui-dialog-titlebar-close').dispatchEvent(new Event('click'));
          });
        }
      );

      // Раскрытый фильтр в Каталоге.
      once('filter-once', '.views-exposed-form', context).forEach(
        (element) => {

          // банкет только для мобильных
          if ($(window).width() < settings.theme.page_offside_hide_width) {

            // стили фильтра
            $(element).addClass('mobile-exposed');

            // добавить кнопки сортировки и фильтров
            $(element).before(
              '<div class="mobile-exposed-trigger">' +
                '<div class="row border-b">' +
                  '<div class="col-sm-5 col-pd-5 border-r">' +
                    '<div class="mobile-exposed--sort"></div>' +
                  '</div>' +
                  '<div class="col-sm-5 col-pd-5">' +
                    '<div class="mobile-exposed--filters"><i class="picon picon--filter"></i><div>Фильтры</div></div>' +
                  '</div>' +
                '</div>' +
              '</div>');

            $(element).prepend(
              '<div class="mobile-exposed__header padding-side border-b">' +
                '<div class="h4">Фильтры</div>' +
                '<i class="picon picon--cross"></i>' +
              '</div>'
            );


            // обработчик открытия фильтров
            $('.mobile-exposed--filters').on("click", function () {
              $(element).addClass('mobile-exposed--open');
            });

            $('.mobile-exposed__header i').on("click", function () {
              $(element).removeClass('mobile-exposed--open');
            });

          }
        }
      );

      // так как фильтры обновляются по ajax, обработчики нажатия кнопок Применить и Сброс вешаем отдельно
      once('filter-button-once', '.mobile-exposed .form-actions input', context).forEach(
        (element) => {

          let $block = $(element).closest('.block-views-exposed-filter-blockkatalog-page-1');

          // обработчик закрытия фильтров
          $(element).on("click", function () {
            $block.removeClass('mobile-exposed--open');
          });
        }
      );

    },
  };

})(jQuery, Drupal, drupalSettings, once);
