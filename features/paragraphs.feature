@api @content
Feature: Reviews
  In order to manage reviews
  as a user,
  I want to create articles that will look like reviews

  Scenario: Create an article and add paragraphs
    Given "article" content:
      | title | status |
      | Test  | 1      |

    Given I add the following paragraph to field "field_paragraphs":
      | paragraph_field | value     |
      | type            | text      |
      | field_text      | Some text |

    Given I am on "test"
    Then I should see "Some text"
