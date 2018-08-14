<?php
namespace DennisDigital\Behat\Drupal\Paragraphs\Driver\Drupal8;
use DennisDigital\Behat\Drupal\Paragraphs\Driver\ParagraphsDriverInterface;

/**
 * ParagraphsDriver
 */
class ParagraphsDriver implements ParagraphsDriverInterface {
  /**
   * @inheritDoc
   */
  public function updateDomain($domain, $valid) {
    return paragraphs_known_domains_update($domain, $valid);
  }
}
