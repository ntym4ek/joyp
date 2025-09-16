/**
 * @file
 * Скрипт изменения количества в поле добавления в корзину
 */

((Drupal, once) => {
  /**
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   * Повесить обработчики клика на кнопки увеличения с классом commerce-quantity-control,
   * которые расположены вокруг элемента с классом commerce-quantity-input
   */
  Drupal.behaviors.commerceQuantityControls = {
    attach(context) {
      once('commerce-quantity-buttons-once', '.commerce-quantity-controls', context).forEach(
        (element) => {

          // повесить обработчики
          element.querySelectorAll(".commerce-quantity-control").forEach((button) => {

            button.addEventListener("click", function() {
              let value = element.querySelector('input').value;
              let new_value = value;

              if (button.classList.contains('increment')) {
                new_value++;
              } else {
                if (new_value > 1) new_value--;
              }

              if (new_value !== value) {
                element.querySelector('input').value = new_value;
                let event = new Event("change");
                element.querySelector('input').dispatchEvent(event);
              }
            }, false);
          });

        }
      );
    },
  };

})(Drupal, once);
