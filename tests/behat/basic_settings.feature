@enrol @enrol_lticourseshell
Feature: Check that settings are adhered to when creating an enrolment plugin
  In order to create an lticourseshell enrolment instance
  As an admin
  I need to ensure the site-wide settings are used

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Publish as lticourseshell tool" "table_row"
    And I navigate to "Plugins > Enrolments > Publish as lticourseshell tool" in site administration
    And I set the following fields to these values:
      | Email visibility    | Visible to everyone |
      | City/town           | Perth                                  |
      | Select a country    | Australia                              |
      | Timezone            | Australia/Perth                        |
      | Institution         | Moodle Pty Ltd                         |
    And I press "Save changes"
    And I log out

  Scenario: As an admin set site-wide settings for the enrolment plugin and ensure they are used
    Given I log in as "teacher1"
    And I am on the "Course 1" "enrolment methods" page
    And I select "Publish as lticourseshell tool" from the "Add method" singleselect
    When I expand all fieldsets
    Then the field "Email visibility" matches value "Visible to everyone"
    And the field "City/town" matches value "Perth"
    And the field "Select a country" matches value "Australia"
    And the field "Timezone" matches value "Australia/Perth"
    And the field "Institution" matches value "Moodle Pty Ltd"
    And I set the following fields to these values:
      | Email visibility    | Hidden |
      | City/town           | Whistler                                        |
      | Select a country    | Canada                                          |
      | Timezone            | America/Vancouver                               |
      | Institution         | Moodle Pty Ltd - remote                         |
    And I press "Add method"
    And I click on "Edit" "link" in the "Publish as lticourseshell tool" "table_row"
    And the field "Email visibility" matches value "Hidden"
    And the field "City/town" matches value "Whistler"
    And the field "Select a country" matches value "Canada"
    And the field "Timezone" matches value "America/Vancouver"
    And the field "Institution" matches value "Moodle Pty Ltd - remote"
