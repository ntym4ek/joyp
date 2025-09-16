(function ($, Drupal, once) {

  Drupal.behaviors.promocodeModal = {
    attach: function (context, settings) {

      once('promocode-modal-once', '.promocode-modal-button', context).forEach(
        (element) => {

          let modalContent =
              '<div class="form-item"><input id="promocode-input" type="text" placeholder="Промокод" autofocus /></div>';

          var modal = Drupal.dialog(modalContent, {
            // autoOpen: false,
            title: 'Введите код',
            width: 500,
            height: 400,
            create: function(){
              let parent = $(this).closest('.ui-dialog');
              parent.removeClass("ui-widget");
              parent.find(".ui-dialog-titlebar button").removeClass("ui-button ui-corner-all ui-widget");
              parent.find(".ui-dialog-buttonpane button").after('<div class="ui-dialog-notes">Промокод можно применить один раз!</div>');
            },
            buttons: [
              {
                text: 'Применить',
                class: 'button button--primary',
                click: function () {
                  let code = $(this).find('#promocode-input').val();
                  $('[name="coupon_redemption[form][code]"]').val(code);
                  $(this).dialog('close');
                  $('[data-drupal-selector="edit-coupon-redemption-form-apply"]').trigger('mousedown').trigger('click');
                }
              }
            ]
          });

          element.addEventListener("click", function() {
            modal.showModal();
          });
        });
    }
  }

}(jQuery, Drupal, once));
