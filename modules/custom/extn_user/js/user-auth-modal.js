var timer = null;

(function ($, Drupal, once) {

  Drupal.behaviors.verificationCodeModal = {
    attach: function (context) {

      once('user-auth-modal-once', '.user-auth-modal.code', context).forEach(
        (element) => {

          let $wrapper = $(element).closest('#drupal-modal');

          /**
           * Обратный отсчёт до появления кнопки отправки нового кода.
           */
          let $resend_countdown = $wrapper.find('.resend-countdown');
          let $counter = $resend_countdown.find('.counter');
          let time = parseInt($counter.text());

          let endTime = Date.now() + time * 1000;

          clearInterval(timer);
          timer = setInterval(function () {
            let remaining = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
            $counter.text(remaining);

            if (remaining <= 0) {
              clearInterval(timer);
              $resend_countdown.hide();
              $wrapper.find('.form-with-verification-dialog-button.resend').removeClass('hide');
            }
          }, 100);

          /**
           * Обратный отсчёт до возможности нового ввода кода
           */
          $wrapper.find('.input-countdown').each((key, el) => {
            let $counter = $(el).find('.counter');
            let time = parseInt($counter.text());

            let endTime = Date.now() + time * 1000;

            clearInterval(timer);
            timer = setInterval(function () {
              let remaining = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
              $counter.text(remaining);

              // закрываем окно, жмём сабмит для вывода обновлённого окна
              if (remaining <= 0) {
                clearInterval(timer);
                $wrapper.dialog('close');
                $('.form-with-verification-submit').trigger('mousedown').trigger('click');
              }
            }, 100);
          });


          /**
           * Обработчик нажатия кнопок.
           */
          $wrapper.find('.form-with-verification-dialog-button').each((key, el) => {
            $(el).on('click', () => {
              let code = "";
              // нажата кнопка "получить новый код"
              if ($(el).hasClass("resend")) {
                code = "resend";
              }
              // нажата кнопка "отправить код"
              else {
                let code = '';
                for (let i = 1; i <= 4; i++) {
                  code += $('[data-id=' + i + ']').val().toString();
                }

                if (code.length !== 4) {
                  $wrapper.find('.message').html('<span class="text-danger">Код должен состоять из 4х цифр.</span>');
                  return;
                }
              }
              // передать форме значение и нажать submit
              $('.form-with-verification-code').val(code);
              $('.form-with-verification-submit').trigger('mousedown').trigger('click');
              $wrapper.dialog('close');
            });
          });

          $wrapper.find('.form-with-verification-dialog-input input').each((key, el) => {
            $(el).on('keydown', (e) => {
              // вводить можно только числовое значение
              let key = e.key;
              if (key.length === 1 && /[a-zA-Z]/.test(key)) {
                e.preventDefault();
              }
            });

            $(el).on('keyup', (e) => {
              let key = e.key;
              if (!$(e.target).val() && !['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight'].includes(key)) {
                return;
              }

              // Если число в поле уже есть, то нажатие нового не приведёт к замене,
              // меняем вручную.
              if (key.length === 1) $(e.target).val(key);

              let id = $(e.target).data('id');

              // собрать введённый код
              let code = '';
              for (let i = 1; i <= 4; i++) {
                code += $('[data-id=' + i + ']').val().toString();
              }

              // если курсор в последней клетке и код полный
              if (id === 4 && code.length === 4) {
                // передать форме значение и нажать submit
                $('.form-with-verification-code').val(code);
                $('.form-with-verification-submit').trigger('mousedown').trigger('click');
                $wrapper.dialog('close');
              }

              // перемещение курсора
              if (['Backspace', 'ArrowLeft'].includes(key)) { id--; } else {
                if (!['Delete', 'Tab'].includes(key)) { id++; }
              }
              if (id < 1) id = 1;
              if (id > 4) id = 4;
              $('[data-id=' + id + ']').focus();
            });
          });

      });


      once('user-auth-modal-once', '.user-auth-modal.pass', context).forEach(
        (element) => {

          let $wrapper = $(element).closest('#drupal-modal');

          /**
           * Обработчик нажатия кнопок.
           */
          $wrapper.find('.form-with-verification-dialog-button').each((key, el) => {
            $(el).on('click', () => {
              let $input = $('.form-with-verification-dialog-input input');
              let pass = $input.val();

              if (pass.trim().length === 0) {
                $wrapper.find('.message').html('<span class="text-danger">Пароль не может быть пустым.</span>');
                $input.focus();
                return;
              }

              // передать форме значение и нажать submit
              $('.form-with-verification-pass').val(pass);
              $('.form-with-verification-submit').trigger('mousedown').trigger('click');
              $wrapper.dialog('close');
            });
          });

        });

    }
  }

}(jQuery, Drupal, once));
