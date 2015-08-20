@_performance_testplan_generator
Feature: As a student visit all activities in test course
  In order visit all activities in test course
  As a student
  I need to visit course and it's activities.

    And I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on

  @javascript
  Scenario: Student visit all activities
    Given I log in as "s1"
    And I should see "Test Course"
    And I follow "Test Course"
    And I follow "Test assignment name 1"
    And I follow "TC"
    And I follow "Test book name 1"
    And I follow "TC"
    And I follow "Test chat name 1"
    And I follow "TC"
    And I follow "Test choice name 1"
    And I follow "TC"
    And I follow "Test database name 1"
    And I follow "TC"
    And I follow "Test folder name 1"
    And I follow "TC"
    And I follow "Test forum name 1"
    And I follow "TC"
    And I follow "Test glossary name 1"
    And I follow "TC"
    And I log out