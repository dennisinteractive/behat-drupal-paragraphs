<?php
namespace DennisDigital\Behat\Drupal\Paragraphs\Driver;

/**
 * ParagraphsDriverInterface
 */
interface ParagraphsDriverInterface {
  /**
   * @param string $domain
   * @param bool $valid
   * @return bool
   */
  public function updateDomain($domain, $valid);
}
