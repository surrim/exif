<?php

namespace Drupal\Tests\exif\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the default admin settings functionality.
 *
 * @group scanner
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'exif'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create user with the appropriate admin role.
    $user = $this->createUser(['administer image metadata']);
    $this->drupalLogin($user);
  }

  /**
   * Tests that the base admin settings form is functional.
   */
  public function testSettingsForm() {
    // Confirm the settings form loads.
    $this->drupalGet('admin/config/media/exif');

    $session_assert = $this->assertSession();
    $session_assert->statusCodeEquals(200);

    // Check for all base options.
    $session_assert->fieldExists('granularity');
    $session_assert->fieldExists('update_metadata');
    $session_assert->fieldExists('extraction_solution');
    $session_assert->fieldExists('exiftool_location');
    $session_assert->fieldExists('write_empty_values');
    // @todo Update the configuration so that Taxonomy isn't installed.
    $session_assert->fieldExists('vocabulary');

    // Fields that won't exist yet.
    $session_assert->fieldNotExists('nodetypes');
    $session_assert->fieldNotExists('filetypes');
    $session_assert->fieldNotExists('mediatypes');

    // Verify the form can submit as-is and nothing breaks.
    $this->submitForm([], 'Save configuration');
    $session_assert = $this->assertSession();
    $session_assert->statusCodeEquals(200);
    $session_assert->pageTextContains('The configuration options have been saved');

    // @todo Create a content type, reload the form and confirm that the
    // content type is now available.
    // @todo Create a file type, reload the form and confirm that the
    // content type is now available.
    // @todo Create a media type, reload the form and confirm that the
    // content type is now available.
  }

}
