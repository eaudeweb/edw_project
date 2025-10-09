<?php

namespace Drupal\edw_project_gef\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal API for GEF Projects.
 *
 * @MigrateSource(
 *   id = "gef_projects",
 *   source_module = "node"
 * )
 */
class GefProjectsAPI extends Url implements ContainerFactoryPluginInterface {

  /**
   * Current Row Data.
   *
   * @var \Drupal\migrate\Row
   */
  protected $currentRowData;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration, ClientInterface $httpClient, LoggerChannelInterface $logger) {
    $this->httpClient = $httpClient;
    $this->logger = $logger;
    $configuration['headers']['accept'] = 'application/json';
    $configuration['base_url'] = $configuration['url'];
    $configuration['ids'] = $this->getIds();
    $configuration['data_parser_plugin'] = $configuration['data_parser_plugin'] ?? 'json';
    $configuration['data_fetcher_plugin'] = $configuration['data_fetcher_plugin'] ?? 'http';

    $configuration['pager'] = $configuration['pager'] ?? [
      'type' => 'paginator',
      'page_key' => 'page',
      'size_key' => 'per_page',
      'default_num_items' => 200,
    ];

    $configuration['item_selector'] = $configuration['item_selector'] ?? 'value';

    parent::__construct($configuration, $pluginId, $pluginDefinition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('http_client'),
      $container->get('logger.factory')->get('gef_projects')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $this->currentRowData = $row;
    foreach (['title', 'body', 'field_executing_agencies'] as $fieldName) {
      $value = $row->getSourceProperty($fieldName);
      if (empty($value)) {
        continue;
      }
      $value = Html::decodeEntities($value);
      $row->setSourceProperty($fieldName, $value);
    }

    $date = NULL;
    if (!empty($row->getSourceProperty('field_date')) || is_string($row->getSourceProperty('field_date'))) {
      if (preg_match('/datetime="(\d{4}-\d{2}-\d{2})T/', $row->getSourceProperty('field_date'), $matches)) {
        $date = ($matches[1] && $matches[1] !== '1970-01-01') ? $date : NULL;
      } else {
        $this->log('Invalid date pattern');
      }
    }
    $row->setSourceProperty('field_date', $date);

    $gefUrl = sprintf("https://www.thegef.org/projects-operations/projects/%s", trim((string) $row->getSourceProperty('field_original_id')));
    $row->setSourceProperty('field_url', $gefUrl);
    foreach (['field_project_status', 'field_trust_fund', 'field_project_phase', 'field_project_type'] as $fieldName) {
      $value = $row->getSourceProperty($fieldName);
      if (empty($value)) {
        continue;
      }

      $field = FieldConfig::loadByName('node', 'project', $fieldName);
      if ($field) {
        $allowed = $field->getSetting('allowed_values');
        $matchedKey = array_search($value, $allowed);
        if (!empty($matchedKey)) {
          $row->setSourceProperty($fieldName, $matchedKey);
        }
        else {
          $this->log('Invalid value for ' . $fieldName . ': ' . $value);
        }
      }
    }

    if ($row->getSourceProperty('field_countries')) {
      $countriesIso = $row->getSourceProperty('field_countries');
      $countriesIso = Html::decodeEntities($countriesIso);
      $countriesIso = array_map('trim', explode(',', $countriesIso));
      $countriesIso = array_filter($countriesIso);
      $row->setSourceProperty('field_countries', $countriesIso);
    }

    if ($row->getSourceProperty('field_implementing_agencies')) {
      $sourceValue = $row->getSourceProperty('field_implementing_agencies');
      $sourceValue = array_map('trim', explode(',', $sourceValue));
      $sourceValue = array_filter($sourceValue);
      $field = FieldConfig::loadByName('node', 'project', 'field_implementing_agencies');
      $allowed = $field->getSetting('allowed_values');
      $targets = [];
      foreach ($sourceValue as $item) {
        $agencies = array_search($item, $allowed, TRUE);
        if ($agencies === FALSE) {
          $this->log('Invalid value for field_implementing_agencies: ' . $item);
          continue;
        }
        $targets[] = $agencies;
      }
      $row->setSourceProperty('field_implementing_agencies', $targets);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    $total = 0;
    $page = 0;
    while(TRUE) {
      try {
        $response = $this->httpClient
          ->get($this->configuration['base_url'] . '?page='. $page, $this->configuration['headers']);
        $data = json_decode($response->getBody());
        $countThisPage = count($data);
        if($countThisPage === 0) {
          break;
        }
        $total += $countThisPage;
        $page++;
        if ($page >= 200) {
          $this->log('Stopped counting after @n pages to avoid infinite loop.', ['@n' => $page]);
          break;
        }
      } catch (\Exception $e) {
        $this->log('Error: @e.', ['@e' => $e]);
        return -1;
      }
    }

    return $total;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $ids['field_original_id']['type'] = 'string';
    return $ids;
  }

  /**
   * Helper function used to log useful information about a row.
   *
   * @param string $message
   *   The message.
   * @param int $level
   *   The log level.
   */
  protected function log($message, $level = RfcLogLevel::WARNING) {
    if ($this->currentRowData) {
      $message = "Entry {$this->currentRowData->getSourceProperty('field_original_id')}: $message";
    }
    $this->logger->log($level, $message);
  }
}
