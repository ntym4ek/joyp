<?php


namespace Drupal\extn_views\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Фильтр для блоков. Исключает из вывода текущий материал.
 *
 * @ViewsFilter("recent_nodes_filter")
 */
class RecentNodesFilter extends FilterPluginBase
{

  /**
   * {@inheritdoc}
   */
  public function adminSummary()
  {
    return $this->t('Recent nodes.');
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Получаем текущий материал из маршрута.
    if ($node = \Drupal::routeMatch()->getParameter('node')) {

      // Добавляем условие к запросу Views.
      $this->query->addWhereExpression(0, 'node_field_data.nid != :current', [
        ':current' => $node->id(),
      ]);
    }
  }
}
