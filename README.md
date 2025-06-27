# EDW Project module

Enable the project module to provide content managers with the ability to manage projects within Drupal.

## Installation

1. Add the following snippet to the `repositories` section of your `composer.json` file:
```
{
    "type": "git",
    "url": "https://github.com/eaudeweb/edw_project.git"
}
```

2. Run
   ```composer require eaudeweb/edw_project:^1.0```

3. Enable the module:
   ``drush en edw_project``

### Fields

#### event

| Field label                | Field name                      | Description | Field type             | Cardinality | Required | Translatable | Widget         |
|----------------------------|---------------------------------|-------------|------------------------|-------------|----------|--------------|----------------|
| Title                      | title                           |             | Text                   | Single      | Yes      | Yes          | Text field     |
| Project type               | field_project_types             |             | TODO                   | Multiple    | No       | No           | TODO           |
| Project Date               | field_date_range                |             | Date                   | Single      | Yes      | No           | HTML5 calendar |
| Image                      | field_image                     |             | Media entity reference | Single      | Yes      | No           | Media library  |
| Related projects           | field_entities                  |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Project Status             | field_project_status            |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Short Title                | field_short_title               |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Expected outcome           | field_project_expected_outcome  |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Budget                     | field_project_budget            |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Date of approval           | field_project_approved_date     |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Original ID                | field_original_id               |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Implementing organisations | field_project_implementing_orgs |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Managing organisations     | field_project_managing_orgs     |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Donor organisations        | field_project_donor_orgs        |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Website                    |                                 |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Documents                  | field_documents                 |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Geographical coverage      |                                 |             | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Current phase              | field_current_phase             |             | TODO                   | TODO        | TODO     | TODO         | TODO           |

TODO

### Taxonomies

None

### Paragraphs

Use the [edw_paragraphs](https://github.com/eaudeweb/edw_paragraphs) module to enable different visual components that can be added to the meeting sections.
Use [countries_import](https://www.drupal.org/project/countries_import) module to import Geographical coverage.

## Functionalities

The following functionalities are provided out of the box:

1. Multilingual content.
2. View block for Featured project.

## Other EDW modules:
* [edw_blocks](https://github.com/eaudeweb/edw_blocks)
* [edw_decoupled](https://github.com/eaudeweb/edw_decoupled)
* [edw_demo_data](https://github.com/eaudeweb/edw_demo_data)
* [edw_document](https://github.com/eaudeweb/edw_document)
* [edw_event](https://github.com/eaudeweb/edw_event)
* [edw_group](https://github.com/eaudeweb/edw_group)
* [edw_media](https://github.com/eaudeweb/edw_media)
* [edw_paragraphs](https://github.com/eaudeweb/edw_paragraphs)
* [edw_person](https://github.com/eaudeweb/edw_person)
* [edw_themes](https://github.com/eaudeweb/edw_themes)
* [edw_utilities](https://github.com/eaudeweb/edw_utilities)