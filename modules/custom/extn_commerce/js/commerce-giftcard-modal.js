(function ($, Drupal, once) {

  Drupal.behaviors.commerceGiftCardModal = {
    attach: function (context, settings) {

      once('giftcard-modal-once', '.giftcard-modal-button', context).forEach(
        (element) => {

          let modalContent =
              '<div class="form-item"><input id="giftcard-input" type="text" placeholder="Сертификат" autofocus /></div>';

          var modal = Drupal.dialog(modalContent, {
            // autoOpen: false,
            title: 'Введите код',
            width: 500,
            height: 400,
            create: function(){
              let parent = $(this).closest('.ui-dialog');
              parent.find(".ui-dialog-titlebar button").removeClass("ui-button ui-corner-all ui-widget");
              parent.find(".ui-dialog-buttonpane button").after('<div class="ui-dialog-notes">Сертификат можно применить один раз!</div>');
            },
            buttons: [
              {
                text: 'Применить',
                class: 'button button--primary',
                click: function () {
                  let code = $(this).find('#giftcard-input').val();
                  $('[name="commerce_giftcard_redemption[form][code]"]').val(code);
                  $(this).dialog('close');
                  $('[data-drupal-selector="edit-commerce-giftcard-redemption-form-apply"]').trigger('mousedown').trigger('click');
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
