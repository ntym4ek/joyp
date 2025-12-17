<?php

namespace Drupal\extn_form\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;

/**
 * Простейшая форма поиска с редиректом.
 */
class SearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'extn_form_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['#prefix'] = '<div id="extn-form-search-form-wrapper" class="extn-form-search-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'] ='project/search-form';

    $form['search'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<div class="search-icon"><i class="picon picon--search"></i></div>'),
    ];

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Search...'),
      '#theme' => 'input_dropdown_filter',
      '#dropdown_options' => [],
      '#attributes' => ['data-diacritics' => 'off'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'custom:delayedInput',
        'wrapper' => '',
      ],
    ];

    $form['close'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<div class="close-icon"><i class="picon picon--cross"></i></div>'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    // проверить поисковый запрос
    $key = $form_state->getValue('key');
    $clean_text = \Drupal\Component\Utility\Xss::filter($key);
    $form_state->setValue('key', $clean_text);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
  }

  public function ajaxCallback(array $form, FormStateInterface $form_state)
  {
    $results_limit = 6;                                      // количество выводимых в строке результатов
    $output = '';
    $results_count = 0;
    if ($key = $form_state->getValue('key')) {
      /** @var \Drupal\search_api\IndexInterface $index_storage */
      $index = \Drupal\search_api\Entity\Index::load('product_variations_index');
      $query = $index->query();
      $query->addCondition('type', 'care');
      $query->addCondition('status', 1);
      $query->addCondition('availability_status', 'in_stock');
      $query->keys('*' . $key . '*');
      /** @var \Drupal\search_api\Query\ResultSetInterface $search_result */
      $results = $query->execute();
      $results_count = $results->getResultCount();

      // Выводим результаты
      $counter = 0;
      foreach ($results->getResultItems() as $result) {
        // наименование Товара берём из индекса
        $title_values = $result->getField('title')->getValues();
        $title = trim($title_values[0]->getText());

        $object = $result->getOriginalObject();
        $entity = $object->getEntity();

        $volume = $entity->get('attribute_volume')->entity->label();
        $url = $entity->toUrl()->toString();
        $output .= Markup::create('<li class="input-dropdown-item item--link"><a href="' . $url . '">' . $title . ($volume ? '<span>, ' . $volume . '</span>' : '') . '</a></li>');
        if (++$counter >= $results_limit) break;
      }
    }

    if ($output) {
      if ($results_count > $results_limit) {
        $output .= '<li class="input-dropdown-item item--link item--footer"><a href="/poisk/' . $key . '">Показать все ' . $results_count . ' ' . new PluralTranslatableMarkup($results_count, 'result', 'results') . '</a></li>';
      }
    } else {
      $output = '<li class="input-dropdown-item">Ничего не найдено, попробуйте изменить запрос.</li>';
    }
    $output = '<ul class="input-dropdown-menu">' . $output . '</ul>';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.input-dropdown-menu', $output));

    return $response;
  }

}
