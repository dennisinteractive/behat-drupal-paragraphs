<?php
namespace DennisDigital\Behat\Drupal\Paragraphs\Driver;
use Drupal\DrupalDriverManager;

/**
 * ParagraphsDriverManager
 */
class ParagraphsDriverManager {
  /**
   * @var \DennisDigital\Behat\Drupal\Paragraphs\Driver\ParagraphsDriverInterface
   */
  protected $paragraphsDriver;

  /**
   * @var \Drupal\DrupalDriverManager
   */
  protected $drupal;

  /**
   * @param \Drupal\DrupalDriverManager $drupal
   */
  public function __construct(DrupalDriverManager $drupal) {
    $this->drupal = $drupal;
  }

  /**
   * @return \DennisDigital\Behat\Drupal\Paragraphs\Driver\ParagraphsDriverInterface
   * @throws \Exception
   */
  public function getParagraphsDriver() {
    // Get the environment.
    if (!isset($this->paragraphsDriver)) {
      $version = $this->drupal->getDriver('drupal')->getDrupalVersion();
      $driver = '\DennisDigital\Behat\Drupal\Paragraphs\Driver\Drupal' . $version . '\ParagraphsDriver';
      if (!class_exists($driver)) {
        throw new \Exception('Paragraphs driver not implemented for Drupal ' . $version);
      }
      $this->paragraphsDriver = new $driver();
    }
    return $this->paragraphsDriver;
  }
}
