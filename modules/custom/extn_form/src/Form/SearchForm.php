<?php

namespace Drupal\extn_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

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
    $form['q'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Search...'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#attributes' => ['class' => ['button--link']],
      '#prefix' => '<div class="button--icon">',
      '#markup' => Markup::create('<label for="edit-submit"><i class="picon picon--search"></i></label>'),
      '#suffix' => '</div>',
    ];

    // Чтобы форма отправлялась методом GET.
    $form['#method'] = 'get';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $query = trim($form_state->getValue('q'));

    if (!empty($query)) {
      // Кодируем запрос и делаем редирект.
      $url = Url::fromUserInput('/search/' . rawurlencode($query));
      $form_state->setRedirectUrl($url);
    }
  }

}
