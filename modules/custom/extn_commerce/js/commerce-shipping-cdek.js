/**
 * @file
 * Подключение виджета СДЭК
 * https://widget.cdek.ru/
 */

var widget = null;
var timer = null;

(function (Drupal, once) {

  /**
   * При выборе ПВЗ задать значения элементам формы
   */
  function onChooseCDEK(method, tariff, address) {
    document.querySelector('input.shipping-city').value = address.city;
    document.querySelector('input.shipping-pvz').value = address.address;
    document.querySelector('input.shipping-json').value = JSON.stringify(address);
    widget.close();

    // пересчитать доставку
    setTimeout(() => {
      document.querySelector('[data-drupal-selector="edit-shipping-information-recalculate-shipping"]').dispatchEvent(new Event('mousedown'));
    }, 5);

  }


  Drupal.behaviors.commerceShippingCdek = {
    attach: function (context) {

      // панель доставки
      once('commerce-shipping-once', 'body', context).forEach(
        (element) => {

          document.addEventListener('inputDropdownFilterKeyUp', (event) => {
            let menu = event.detail.source[0].parentElement.querySelector('.input-dropdown-menu');
            let current_city = event.detail.dest[0].value;
            if (current_city.length > 2) {
              clearTimeout(timer);
              timer = setTimeout(() => {
                // заблюрить список
                menu.classList.add('processing');

                // добавить ajax throbber
                const loader = document.createElement('div');
                loader.className = 'ajax-progress ajax-progress-throbber';
                loader.innerHTML = '<div class="throbber">&nbsp;</div><div class="message">Подождите...</div>';
                menu.appendChild(loader);

                // сделать запрос
                const ajax = Drupal.ajax({
                  url: Drupal.url('endpoint/cities/' + current_city),
                  submit: {}
                });
                ajax.execute();
              }, 200);
            } else {
              menu.innerHTML = '<li class="input-dropdown-item">Начните набирать и выберите из списка</li>';
            }
          });
          // обработчик события на выбор Города (библиотека input-dropdown-filter)
          document.addEventListener('inputDropdownFilterSelected', (event) => {
            // Установить значение поля Город (в теге SPAN - регион, для уточнения, его вырезаем).
            let city = event.detail.source[0].innerHTML.replace(/<span>.*<\/span>/, '').trim();
            let city_code = event.detail.source[0].dataset.code;
            let current_city = event.detail.dest[0].value;

            event.detail.dest[0].value = city;

            // если выбран другой город, стереть старый адрес ПВЗ
            if (current_city !== city && document.querySelector('input.shipping-pvz')) {
              document.querySelector('input.shipping-pvz').value = '';
            }

            // сохранить данные о выбранном адресе в JSON формате
            let data = {
              city_code: city_code,
              city: city
            };
            element.querySelector('input.shipping-json').value = JSON.stringify(data);

            // обновить локацию в виджете
            if (widget) widget.updateLocation(city);

            // пересчитать доставку
            setTimeout(() => {
              document.querySelector('[data-drupal-selector="edit-shipping-information-recalculate-shipping"]').dispatchEvent(new Event('mousedown'));
            }, 5);
          });

        });

      // Подключение виджета выбора ПВЗ СДЭК (https://widget.cdek.ru/)
      once('shipping-pvz-once', 'input.shipping-pvz', context).forEach(
        (element) => {

          element.addEventListener('focus', () => {
            widget.open();
          });

          if (!widget) {
            const div = document.createElement('div');
            div.id = 'cdek-map';
            div.className = 'cdek-map';
            document.body.appendChild(div);

            let city = document.querySelector('input.shipping-city').value;
            let site = window.location.protocol + "//" + window.location.hostname;

            widget = new window.CDEKWidget({
              from: 'Москва',
              root: 'cdek-map',
              apiKey: 'df470b3b-b068-41a0-8ebb-93bbc0f7621a', servicePath: site + '/service.php',
              hideDeliveryOptions: {
                door: true,
              },
              defaultLocation: city ? city : 'Москва',
              popup: true,
              onChoose: onChooseCDEK
            })
          }

        });

    }
  }

}(Drupal, once));
