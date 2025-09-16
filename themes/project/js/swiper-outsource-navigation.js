/**
 * @file
 * Управление навигацией swiper с помощью вынесенных за его пределы навигационных элементов
 */

((Drupal, settings, once) => {
  /**
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   * Найти все элементы .swiper-outsource-navigation
   * и задать расположенные в них элементы навигации свайперу с id,
   * указанному в атрибуте data-parent-swiper-id
   */
  Drupal.behaviors.swiperOutsourceNavigation = {
    attach(context) {
      once('swiper-outsource-navigation-once', '.swiper-outsource-navigation', context).forEach(
        (element) => {
          let parentId = element.dataset.parentSwiperId;

          if (parentId) {
            let parentSwiper = drupalSettings.swiper_formatter.swipers[parentId];
            if (parentSwiper) {
              parentSwiper.navigation = {
                nextEl: element.querySelector('.right'),
                prevEl: element.querySelector('.left'),
              };
            }
          }
        }
      );
    },
  };

})(Drupal, drupalSettings, once);
