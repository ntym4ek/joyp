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
          let parentSwiper = document.getElementById(parentId).swiper;

          if (parentId) {
            let nextBtn = element.querySelector('.right');
            if (nextBtn) {
              nextBtn.addEventListener('click', (e) => {
                if (parentSwiper) {
                  e.preventDefault();
                  parentSwiper.slideNext();
                }
              });
            }

            let prevBtn = element.querySelector('.left');
            if (prevBtn) {
              prevBtn.addEventListener('click', (e) => {
                if (parentSwiper) {
                  e.preventDefault();
                  parentSwiper.slidePrev();
                }
              });
            }
          }
        }
      );
    },
  };

})(Drupal, drupalSettings, once);
