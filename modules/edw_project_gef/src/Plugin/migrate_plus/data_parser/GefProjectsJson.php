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
    // Skip rows not changed since the last run. The API is sorted by project
    // ID, not by change date, so an old row must not end the current page.
    if (!empty($this->configuration['last_run'])) {
      while (is_array($current = $this->iterator->current()) && $this->isUnchanged($current)) {
        $this->iterator->next();
      }
    }
    parent::fetchNextRow();
  }

  /**
   * Checks whether a row was not changed since the last run.
   *
   * @param array $row
   *   The raw row from the API.
   *
   * @return bool
   *   TRUE if the row has a change date older than the last run.
   */
  protected function isUnchanged(array $row): bool {
    if (empty($row['changed']) || !preg_match('/datetime="([^"]+)"/', $row['changed'], $changed)) {
      return FALSE;
    }
    $updated = strtotime($changed[1]);
    return $updated !== FALSE && $updated < $this->configuration['last_run'];
  }

}
