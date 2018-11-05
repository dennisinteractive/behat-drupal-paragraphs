<?php
namespace DennisDigital\Behat\Drupal\Paragraphs\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use DennisDigital\Behat\Drupal\Paragraphs\Driver\ParagraphsDriverManager;
use Drupal\paragraphs\Entity\Paragraph;
use Behat\Gherkin\Node\TableNode;
use Drupal\field\Entity\FieldConfig;
use Drupal\DrupalExtension\Hook\Scope\EntityScope;
use Exception;

/**
 * ParagraphsContext
 */
class ParagraphsContext extends RawDrupalContext {
  /**
   * @var \DennisDigital\Behat\Drupal\Paragraphs\Driver\ParagraphsDriverInterface
   */
  protected $paragraphsDriver;

  /**
   * @var array of created paragraphs.
   */
  protected $paragraphs;

  /**
   * @var array of created nodes.
   */
  protected $created_nodes = [];

  /**
   * @afterNodeCreate
   */
  public function storeNode(EntityScope $scope) {
    $this->created_nodes[] = $scope->getEntity();
  }

  /**
   * Returns the last created node.
   *
   * @return \stdClass
   */
  protected function getCurrentNode() {
    if ($node = end($this->created_nodes)) {
      return $node;
    }
    throw new Exception('Node has not been created.');
  }

  /**
   * Returns node with provided title.
   *
   * @return \stdClass
   */
  protected function getNodeByTitle($title) {
    foreach ($this->created_nodes as $node) {
      if ($node->title == $title) {
        return $node;
      }
    }
    throw new Exception('Could not find node with title: ' . $title);
  }

  /**
   * @return \DennisDigital\Behat\Drupal\Paragraphs\Driver\ParagraphsDriverInterface
   * @throws \Exception
   */
  public function getParagraphsDriver() {
    if (!isset($this->paragraphsDriver)) {
      $manager = new ParagraphsDriverManager($this->getDrupal());
      $this->paragraphsDriver = $manager->getParagraphsDriver();
    }
    return $this->paragraphsDriver;
  }

  /**
   * Helper method to add given paragraph to field on entity.
   *
   * @param $entity_type
   * @param $entity_id
   * @param $field_name
   * @param $paragraph
   * @throws Exception
   */
  public function appendParagraphToEntityField($entity_type, $entity_id, $field_name, $paragraph) {

    if (empty($paragraph)) {
      throw new Exception("Unable to append empty Paragraph.");
    }

    // Load up the entity for this paragraph
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    if (empty($entity)) {
      throw new Exception(printf("Entity not found: %s/%s", $entity_type, $entity_id));
    }

    // Check if it has the required field
    if (!$entity->hasField($field_name)) {
      throw new Exception(printf("Field not found: %s", $field_name));
    }

    $entity->{$field_name}->appendItem($paragraph);
    $entity->save();

    // Clear static cache / cache tags after re-saving the entity.
    $this->clearStaticCaches();
  }

  /**
   * @Transform table:paragraph_field,value
   *
   * Casts a table with the following format to a Paragraph entity
   * | paragraph_field   | value                 |
   * | paragraph_id      | first_offer           |
   * | type              | offer                 |
   * | field_title       | something             |
   * | ...               | ...                   |
   */
  public function castParagraphTable(TableNode $table)
  {
    $field_data = array();
    foreach ($table->getHash() as $hash) {
      $this->transformParagraphFieldValue($hash['paragraph_field'], $hash['value'], $field_data);
    }

    if (isset($field_data['type'])) {
      foreach ($field_data as $field_name => &$field_value) {
        $this->referenceParagraphMedia($field_name, $field_value, $field_data['type']);
      }
    }

    $paragraph = Paragraph::create($field_data);
    if (!empty($paragraph)) {
      $paragraph->save();

      // Record this paragraph for reference else where
      $field_data['entity:id'] = $paragraph->id();
      $field_data['entity:revision'] = $paragraph->getRevisionId();
      $this->paragraphs[] = $field_data;
    }

    return $paragraph;
  }

  /**
   * Casts a table with the following format to an array of Paragraph entities.
   *
   * | paragraph_id  | paragraph_type  | field_title     | ... |
   * | first_offer   | offer           | something       | ... |
   * | ...           | ...             | ...             | ... |
   */
  public function castParagraphsTable(TableNode $table)
  {
    $paragraphs = array();

    // Iterate through each of the provided rows and create a paragraph of the required type
    foreach ($table->getHash() as $row) {
      $field_data = array();
      foreach ($row as $field => $value) {
        $this->transformParagraphFieldValue($field, $value, $field_data);
      }

      $paragraph = Paragraph::create($field_data);
      if (!empty($paragraph)) {
        $paragraph->save();

        // Record this paragraph for reference else where
        $field_data['entity:id'] = $paragraph->id();
        $field_data['entity:revision'] = $paragraph->getRevisionId();
        $this->paragraphs[] = $field_data;

        // Add to return array
        $paragraphs[] = $paragraph;
      }
    }
    return $paragraphs;
  }

  /**
   * Helper method to transform table field values into the correct field array.
   *
   * @param $field_name
   * @param $field_value
   * @param $field_data
   * @throws Exception
   */
  public function transformParagraphFieldValue($field_name, $field_value, &$field_data) {
    // Set the default field key
    $field_key = 'value';

    // Check for sub-keys
    if (strpos($field_name, ':') !== FALSE) {
      $keys = explode(':', $field_name);
      $field_name = $keys[0];
      $field_key = $keys[1];
    }

    if ($field_name === 'type') {
      $field_data['type'] = $field_value;
    }
    else if ($field_name === 'tag') {
      $field_data['tag'] = $field_value;
    }
    else{
      if ($field_key === 'term') {
        // If value is a taxonomy term then load/create the term and set target_id
        $term = $this->assertTerm($field_value);
        if (empty($term)) {
          throw new Exception(printf('Unable to create/load taxonomy term: ', $field_value));
        }
        $field_data[$field_name]['target_id'] = $term;
      }
      if ($field_key === 'node') {
        $field_data[$field_name]['target_id'] = $this->getNodeByTitle($field_value)->nid;
      }
      else if ($field_key === 'link') {
        // If this is a link field, then split out the uri and title values and add both
        if (strpos($field_value, ',') !== FALSE) {
          // Look for the last occurrence of a comma
          $p = strrpos($field_value, ',');
          $field_data[$field_name]['title'] = trim(substr($field_value, 0, $p));
          $field_data[$field_name]['uri'] = trim(substr($field_value, $p+1));
        }
        else {
          $field_data[$field_name]['uri'] = $field_value;
        }
      }
      else {
        $field_data[$field_name][$field_key] = $field_value;
      }
    }
  }

  /**
   * Reference Media entities for paragraph media fields.
   *
   * @param $field_name
   * @param $field_value
   * @param $type
   */
  protected function referenceParagraphMedia($field_name, &$field_value, $type) {
    if (!$field_info = FieldConfig::loadByName('paragraph', $type, $field_name)) {
      return;
    }
    // Check target type.
    $entity_type = $field_info->getSetting('target_type');
    if (!empty($entity_type) && $entity_type == 'media') {
      $entity_info = \Drupal::entityTypeManager()->getDefinition($entity_type);

      $database = \Drupal::database();
      $target_id = $database->select($entity_info->getDataTable(), 't')
        ->fields('t', array($entity_info->getKey('id')))
        ->condition('t.' . $entity_info->getKey('label'), $field_value)
        ->execute()->fetchField();
      if ($target_id) {
        $field_value = array('target_id' => $target_id);
      }
    }
  }

  /**
   * @Given /^I add the following paragraph to field "([^"]+)":$/
   */
  public function addParagraphToField($field_name, Paragraph $paragraph)
  {

    if (empty($this->getCurrentNode())) {
      throw new Exception("There are no nodes to add paragraphs to.");
    }

    if (empty($paragraph)) {
      throw new Exception("Unable to create Paragraph.");
    }

    $this->appendParagraphToEntityField('node', $this->getCurrentNode()->nid, $field_name, $paragraph);
  }

  /**
   * @Given /^I add the following paragraph to field "([^"]+)" on paragraph "([^"]+)":$/
   */
  public function addParagraphToFieldOnParagraph($field_name, $paragraph_tag, Paragraph $paragraph)
  {

    if (empty($this->paragraphs)) {
      throw new Exception("There are no paragraphs to add paragraphs to.");
    }

    if (empty($paragraph)) {
      throw new Exception("Unable to create Paragraph.");
    }

    foreach ($this->paragraphs as $parent) {
      print $parent['tag'] . ' - ' . $paragraph_tag . "\n";
      if ($parent['tag'] === $paragraph_tag) {
        $this->appendParagraphToEntityField('paragraph', $parent['entity:id'], $field_name, $paragraph);
        break;
      }
    }
  }

  /**
   * @Given /^I add the following paragraphs to field "([^"]*)":/
   */
  public function addTheFollowingParagraphs($field_name, TableNode $table) {

    if (empty($this->getCurrentNode())) {
      throw new Exception("There are no nodes to add paragraphs to.");
    }

    $paragraphs = $this->castParagraphsTable($table);
    if (empty($paragraphs)) {
      throw new Exception("Unable to create Paragraphs.");
    }

    // Load up the node for this paragraph
    $entity = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($this->getCurrentNode()->nid);

    if (empty($entity)) {
      throw new Exception(printf("Entity not found: node/%s", $this->getCurrentNode()->nid));
    }

    // Check if it has the required field
    if (!$entity->hasField($field_name)) {
      throw new Exception(printf("Field not found on entity node/%s : %s", $this->getCurrentNode()->nid, $field_name));
    }

    // Add all the paragraphs
    foreach ($paragraphs as $paragraph) {
      $entity->{$field_name}->appendItem($paragraph);
    }

    // Save the node
    $entity->save();
  }

  /**
   * @Given /^I add the following paragraphs to field "([^"]*)" on paragraph "([^"]*)":/
   */
  public function addTheFollowingParagraphsToFieldOnParagraph($field_name, $paragraph_tag, TableNode $table)
  {
    $paragraphs = $this->castParagraphsTable($table);

    if (empty($paragraphs)) {
      throw new Exception("Unable to create Paragraphs.");
    }

    if (empty($this->paragraphs)) {
      throw new Exception("There are no paragraphs to add paragraphs to.");
    }

    foreach ($this->paragraphs as $parent) {
      if ($parent['tag'] === $paragraph_tag) {

        // Load up the entity for this paragraph
        $entity = \Drupal::entityTypeManager()->getStorage('paragraph')->load($parent['entity:id']);
        if (empty($entity)) {
          throw new Exception(printf("Entity not found: paragraph/%s", $parent['entity:id']));
        }

        // Check if it has the required field
        if (!$entity->hasField($field_name)) {
          throw new Exception(printf("Field not found on entity paragraph/%s : %s", $parent['entity:id'], $field_name));
        }

        // Add all the paragraphs
        foreach ($paragraphs as $paragraph) {
          $entity->{$field_name}->appendItem($paragraph);
        }

        // Save parent paragraph
        $entity->save();
        break;
      }
    }
  }

  /**
   * Load/Create taxonomy term from string (vocabulary:term_name)
   * @param $value
   * @return bool|int|mixed
   * @throws Exception
   */
  public function assertTerm($value) {
    $term = FALSE;
    if (strpos($value, ':') === FALSE) {
      throw new Exception(printf('Unknown vocabulary in value: %s', $value));
    }

    $values = explode(':', $value);
    $name = trim($values[1]);
    $vocab = trim($values[0]);
    if ($terms = taxonomy_term_load_multiple_by_name($name, $vocab)) {
      $term = reset($terms);
    }

    // Create term if not found
    if (empty($term)) {
      // Sadly DrupalContext->createTerm doesn't return the newly created term, so do it ourselves
      $term = (object) array(
        'name' => $name,
        'vocabulary_machine_name' => $vocab,
        'description' => ''
      );
      $term_object = $this->termCreate($term);
      return $term_object->tid;
    }

    return $term->id();
  }
}
