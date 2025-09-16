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

      // Повестить обработчик для кнопок закрытия,
      // создаваемых через OpenModalDialogCommand
      once('body-once', '#buttonDialogClose', context).forEach(
        (element) => {
          element.addEventListener("click", function() {
            document.querySelector('.ui-dialog-titlebar-close').dispatchEvent(new Event('click'));
          });
        }
      );
    },
  };

})(jQuery, Drupal, drupalSettings, once);
