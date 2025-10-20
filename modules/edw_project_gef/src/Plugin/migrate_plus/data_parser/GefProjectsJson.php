<?php

namespace Drupal\edw_project_gef\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON Drupal data.
 *
 * @DataParser(
 *   id = "gef_projects_json",
 *   title = @Translation("EDW Gef Projects API")
 * )
 */
class GefProjectsJson extends Json {

  /**
   * {@inheritdoc}
   */
  protected function getNextUrls(string $url): array {
    $response = $this->getDataFetcherPlugin()->getResponse($url);
    $content = $response->getBody()->getContents();

    // Stop if we get a 200 but the response is an empty object or array.
    if ($response->getStatusCode() === 200) {
      $data = json_decode($content, TRUE);
      if (empty($data) || (is_array($data) && count($data) === 0) || (is_object($data) && (array)$data === [])) {
        return [];
      }
    }

    return parent::getNextUrls($url);
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow(): void {
    $current = $this->iterator->current();
    if ($current) {
      if (!empty($current['changed'] && !empty($this->configuration['last_run']))) {
        preg_match('/datetime="([^"]+)"/', $current['changed'], $changed);
        $updated = $changed[1];
        if (!empty($this->configuration['last_run']) && $updated < date('Y-m-d\TH:i:s', $this->configuration['last_run'])) {
          $this->currentItem = NULL;
          return;
        }
      }
      parent::fetchNextRow();
    }
  }

}
