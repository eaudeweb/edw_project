<?php

use Drupal\taxonomy\TermInterface;

/**
 * Create GEF data source term.
 */
function gef_projects_deploy_9001() {
  $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $properties = [
    'name' => 'GEF',
    'vid' => 'data_sources',
    'uuid' => '01efd2e0-bc20-45d3-831c-dfd21db5c83e',
  ];
  $term = $termStorage->loadByProperties($properties);
  $term = reset($term);
  if ($term instanceof TermInterface) {
    \Drupal::logger('gef_projects')->notice('"GEF" term already exists.');
    return;
  }
  $term = $termStorage->create($properties);
  $validations = $term->validate();
  if ($validations->count() == 0) {
    $term->save();
    \Drupal::logger('gef_projects')->notice('New data source term "GEF" was created.');
    return;
  }
  \Drupal::logger('gef_projects')->notice('Something went wrong during validation.');
}
