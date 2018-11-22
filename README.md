# Behat Drupal Paragraphs Context

Provides Behat steps for Drupal Paragraphs module

## Installation
1. `composer require dennisdigital/behat-drupal-paragraphs:~1.0.0`

## Usage

### Add context to behat.yml:
Add the context under contexts: `DennisDigital\Behat\Drupal\Paragraphs\Context\ParagraphsContext`

### Step definitions

```gherkin
Given I add the following paragraph to field "fieldname":
Given I add the following paragraph to field "fieldname" on term:
Given I add the following paragraph to field "fieldname" on paragraph "name":
Given I add the following paragraphs to field "fieldname":
Given I add the following paragraphs to field "fieldname" on paragraph "name":
```

See examples on features folder.
