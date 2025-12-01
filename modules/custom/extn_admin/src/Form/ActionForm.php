<?php

namespace Drupal\extn_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActionForm extends FormBase {

  public function getFormId() {
    return 'extn_admin_action_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#markup' => '<p>Нажмите кнопку для выполнения действия.</p>',
    ];

    $form['options']['dry_run'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Тестовый режим'),
      '#description' => $this->t('Если отмечено, изменения не будут сохранены, только показан результат.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Выполнить действие'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $dry_run = $form_state->getValue('dry_run');
//    $this->processProducts($dry_run);

    $this->messenger()->addStatus($this->t('Действие успешно выполнено!'));
  }

  /**
   * Обрабатываем продукты.
   *
   * @param bool $dry_run
   *   Тестовый режим.
   */
  private function processProducts($dry_run = TRUE) {
    try {
      // Получаем все продукты типа "care"
      $query = \Drupal::entityTypeManager()
        ->getStorage('commerce_product')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'care');

      $product_ids = $query->execute();

      if (empty($product_ids)) {
        $this->messenger()->addWarning($this->t('Продукты типа "care" не найдены.'));
        return;
      }

      $products = \Drupal::entityTypeManager()
        ->getStorage('commerce_product')
        ->loadMultiple($product_ids);

      $updated = 0;
      $skipped = 0;

      foreach ($products as $product) {
        $title = $product->getTitle();
        $title_en = $product->get('field_title_en')->value;

        if ($title !== $title_en) {
          if (!$dry_run) {
            // Сохраняем значение
            $product->set('field_title_en', $title);
            $product->save();
          }

          $updated++;
        }
        else {
          $skipped++;
        }
      }

      // Выводим результаты
      $message = $dry_run
        ? $this->t('<strong>Тестовый режим:</strong> Найдено @updated продуктов для обновления из @total проверенных. Пропущено: @skipped', [
          '@updated' => $updated,
          '@total' => count($products),
          '@skipped' => $skipped,
        ])
        : $this->t('<strong>Обновление завершено:</strong> Обновлено @updated продуктов. Пропущено: @skipped', [
          '@updated' => $updated,
          '@skipped' => $skipped,
        ]);

      $this->messenger()->addStatus($message);

      // Логируем операцию
      \Drupal::logger('extn_admin')->info('Синхронизация заголовков: @mode. Обновлено: @updated, Пропущено: @skipped', [
        '@mode' => $dry_run ? 'тестовый режим' : 'реальный запуск',
        '@updated' => $updated,
        '@skipped' => $skipped,
      ]);

    }
    catch (\Exception $e) {
      $this->messenger()->addError(
        $this->t('Ошибка при обработке продуктов: @error', [
          '@error' => $e->getMessage()
        ])
      );

      \Drupal::logger('my_module')->error('Ошибка при синхронизации заголовков: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

  }
}

