<?php

namespace Drupal\edw_project_gef\Plugin\migrate\source;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\facets\Exception\Exception;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
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
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The data parser plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $dataParserManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;


  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration, StateInterface $state, ClientInterface $httpClient, PluginManagerInterface $dataParserManager, LoggerChannelInterface $logger) {
    $this->state = $state;
    $this->httpClient = $httpClient;
    $this->dataParserManager = $dataParserManager;
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
      $container->get('state'),
      $container->get('http_client'),
      $container->get('plugin.manager.migrate_plus.data_parser'),
      $container->get('logger.factory')->get('gef_projects')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $url = $this->configuration['url'];
    $parse = parse_url($url);
    $base_url = $parse['scheme'] . '://' . $parse['host'];
    $row->setSourceProperty('base_url', $base_url);
    $this->currentRowData = $row;
    foreach (['title', 'body', 'field_executing_agencies'] as $field) {
      $value = $row->getSourceProperty($field);
      if (empty($value)) {
        continue;
      }

      if (is_string($value)) {
        $value = Html::decodeEntities($value);
      }
      else {
        foreach ($value as &$array_value) {
          if (!is_string($array_value)) {
            continue;
          }

          $array_value = Html::decodeEntities($array_value);
        }
      }
      $row->setSourceProperty($field, $value);
    }

    if (!empty($row->getSourceProperty('field_date')) || is_string($row->getSourceProperty('field_date'))) {
      if (preg_match('/datetime="(\d{4}-\d{2}-\d{2})T/', $row->getSourceProperty('field_date'), $matches)) {
        $date = $matches[1];
        if ($date && $date !== '1970-01-01') $row->setSourceProperty('field_date', $date);
        else $row->setSourceProperty('field_date', NULL);
      } else {
        $this->log('Invalid date pattern');
      }
    }
    else $row->setSourceProperty('field_date', NULL);

    $modifiedUrl = 'https://www.thegef.org/projects-operations/projects/' . trim((string) $row->getSourceProperty('field_original_id'));
    $row->setSourceProperty('field_url', $modifiedUrl);
    foreach (['field_project_status', 'field_trust_fund', 'field_project_phase', 'field_project_type'] as $fieldName) {
      $value = $row->getSourceProperty($fieldName);
      if (empty($value)) {
        continue;
      }

      $normalizedValue = mb_strtolower(trim($value));
      $field = FieldConfig::loadByName('node', 'project', $fieldName);
      if ($field) {
        $allowed = $field->getSetting('allowed_values');
        $matchedKey = NULL;
        foreach ($allowed as $key => $label) {
          if (mb_strtolower($label) === $normalizedValue || mb_strtolower($key) === $normalizedValue) {
            $matchedKey = $key;
            break;
          }
        }

        if ($matchedKey !== NULL) {
          $row->setSourceProperty($fieldName, $matchedKey);
        }
        else {
          $this->log('Invalid value for ' . $fieldName . ': ' . $value);
        }
      }
    }
    foreach (['field_implementing_agencies', 'field_countries', 'field_topics'] as $fieldName) {
      $raw = $row->getSourceProperty($fieldName);
      $targets = [];

      if (is_string($raw) && $raw !== '') {
        if ($fieldName === 'field_countries') {
          $raw = Html::decodeEntities($raw);
        }

        $values = array_map('trim', explode(',', $raw));
        $values = array_filter($values);
        foreach ($values as $item) {
          switch ($fieldName) {
            case 'field_topics':
              if ($this->validateTopic($item)) {
                $targets[] = $item;
              } else {
                $this->log("Invalid topic: {$item}");
              }
              break;

            case 'field_countries':
              $targets[] = $item;
              break;

            case 'field_implementing_agencies':
              $field = FieldConfig::loadByName('node', 'project', 'field_implementing_agencies');
              $allowed = $field->getSetting('allowed_values');
              $agencies = array_search($item, $allowed, TRUE);
              if ($agencies === FALSE) {
                $this->log('Invalid value for field_implementing_agencies: ' . $item);
                break;
              }
              $targets[] = $agencies;
              break;
          }
        }
      }
      $row->setSourceProperty($fieldName, $targets);
    }

    return parent::prepareRow($row);
  }

  public function validateTopic($topics): bool {
    $map = [
      'Biodiversity',
      'Chemicals and Waste',
      'Climate Change',
      'International Waters',
      'Land Degradation',
      'Ozone Depleting Substances',
      'POPs',
      'Multi Focal Area',
    ];

    if (!in_array($topics, $map)) return FALSE;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    $total = 0;
    $page = 0;
    while(TRUE) {
      try {
        $response = \Drupal::httpClient()
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
