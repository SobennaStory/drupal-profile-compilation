<?php

namespace Drupal\profile_comp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\profile_comp\ParserInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\csv_importer\Plugin\ImporterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;

/**
 * Provides CSV importer form for profile creation.
 */
class ProfileCreationForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * The parser service.
   *
   * @var \Drupal\profile_comp\ParserInterface
   */
  protected $parser;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The importer plugin manager service.
   *
   * @var \Drupal\csv_importer\Plugin\ImporterManager
   */
  protected $importer;

  /**
   * ProfileCreationForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity bundle info service.
   * @param \Drupal\profile_comp\ParserInterface $parser
   *   The parser service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\csv_importer\Plugin\ImporterManager $importer
   *   The importer plugin manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    ParserInterface $parser,
    RendererInterface $renderer,
    ImporterManager $importer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->parser = $parser;
    $this->renderer = $renderer;
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('profile_comp.parser'),
      $container->get('renderer'),
      $container->get('plugin.manager.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_comp_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['importer'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'profile-importer',
      ],
    ];

    $form['importer']['delimiter'] = [
      '#type' => 'select',
      '#title' => $this->t('Select delimiter'),
      '#options' => [
        ',' => ',',
        '~' => '~',
        ';' => ';',
        ':' => ':',
      ],
      '#default_value' => ',',
      '#required' => TRUE,
      '#weight' => 10,
    ];

    $form['importer']['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select CSV of Profiles'),
      '#required' => TRUE,
      '#autoupload' => TRUE,
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#weight' => 10,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $csv = current($form_state->getValue('csv'));
    $delimiter = $form_state->getValue('delimiter');
    $csv_parse = $this->parser->getCsvById($csv, $delimiter);
    $new_usernames = [];
    $updated_usernames = [];
    $found_usernames = [];

    $all_users = $this->entityTypeManager->getStorage('user')->loadMultiple();

    foreach ($csv_parse as $csv_entry) {
      $csv_normalized_name = $this->normalizeString($csv_entry['First Name'] . ' ' . $csv_entry['Last Name']);

      $matching_user = $this->findMatchingUser($all_users, $csv_normalized_name);

      if ($matching_user) {
        $was_updated = $this->updateExistingUser($matching_user, $csv_entry);
        if ($was_updated) {
          $updated_usernames[] = $matching_user->getAccountName();
        } else {
          $found_usernames[] = $matching_user->getAccountName();
        }
      } else {
        $new_user = $this->createNewUser($csv_entry);
        $new_usernames[] = $new_user->getAccountName();
      }
    }

    $this->displayResults($new_usernames, $updated_usernames, $found_usernames);
  }

  /**
   * Normalizes a string for comparison.
   *
   * @param string $string
   *   The string to normalize.
   *
   * @return string
   *   The normalized string.
   */
  private function normalizeString($string) {
    // Remove any non-alphanumeric characters (except spaces)
    $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
    // Replace multiple spaces with a single space
    $string = preg_replace('/\s+/', ' ', $string);
    // Convert to lowercase and trim
    return strtolower(trim($string));
  }

  /**
   * Finds a matching user from the list of all users.
   *
   * @param array $all_users
   *   Array of all user entities.
   * @param string $normalized_csv_name
   *   Normalized name from CSV entry.
   *
   * @return \Drupal\user\Entity\User|null
   *   Matching user entity or null if not found.
   */
  private function findMatchingUser($all_users, $normalized_csv_name) {
    foreach ($all_users as $user) {
      $normalized_username = $this->normalizeString($user->getAccountName());

      // Check if the normalized CSV name is contained within the normalized username
      if (strpos($normalized_username, $normalized_csv_name) !== false) {
        return $user;
      }

      // Alternative: Check if all parts of the CSV name are in the username
      $csv_name_parts = explode(' ', $normalized_csv_name);
      if (count(array_intersect($csv_name_parts, explode(' ', $normalized_username))) == count($csv_name_parts)) {
        return $user;
      }
    }

    return null;
  }

  /**
   * Updates an existing user with CSV data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity to update.
   * @param array $csv_entry
   *   The CSV data for this user.
   */

  private function updateExistingUser(User $user, array $csv_entry) {
    $updated = false;

    if (empty($user->getEmail())) {
      $user->setEmail('cps-vo-test-' . $csv_entry['Email']);
      $updated = true;
    }

    if (empty($user->get('field_user_biography')->value)) {
      $biography = $csv_entry['URL'] . "\n\n" . $csv_entry['Bio'];
      $user->set('field_user_biography', [
        'value' => $biography,
        'format' => 'basic_html',
      ]);
      $updated = true;
    }

    if ($updated) {
      $user->save();
    }

    return $updated;
  }

  /**
   * Creates a new user from CSV data.
   *
   * @param array $csv_entry
   *   The CSV data for this user.
   *
   * @return \Drupal\user\Entity\User
   *   The newly created user entity.
   */
  private function createNewUser(array $csv_entry) {
    $new_user = User::create();
    $username = $csv_entry['First Name'] . ' ' . $csv_entry['Last Name'];
    $email = $csv_entry['Email'];
    $biography = $csv_entry['URL'] . "\n\n" . $csv_entry['Bio']; // PLACEHOLDER. WHERE TO PUT URL?

    $new_user->setUsername($username);
    $new_user->setEmail($email);
    $new_user->set('field_user_biography', [
      'value' => $biography,
      'format' => 'basic_html',
    ]);
    $new_user->setPassword($this->generateRandomPassword()); // PLACEHOLDER. NEED TO CHANGE PASSWORD
    $new_user->enforceIsNew();
    $new_user->activate();
    $new_user->save();

    return $new_user;
  }

  /**
   * Generates a random password.
   *
   * @return string
   *   A random password string.
   */
  private function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+{}[]';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
      $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
  }

  /**
   * Displays the results of the import process.
   *
   * @param array $new_usernames
   *   Array of newly created usernames.
   * @param array $updated_usernames
   *   Array of updated usernames.
   */
  private function displayResults(array $new_usernames, array $updated_usernames, array $found_usernames) {
    if (!empty($new_usernames)) {
      $this->messenger()->addMessage($this->t('The following new users were created: @usernames', [
        '@usernames' => implode(', ', $new_usernames)
      ]));
    }

    if (!empty($updated_usernames)) {
      $this->messenger()->addMessage($this->t('The following existing users were updated: @usernames', [
        '@usernames' => implode(', ', $updated_usernames)
      ]));
    }

    if (!empty($found_usernames)) {
      $this->messenger()->addMessage($this->t('The following users were found but not updated (no changes needed): @usernames', [
        '@usernames' => implode(', ', $found_usernames)
      ]));
    }

    if (empty($new_usernames) && empty($updated_usernames) && empty($found_usernames)) {
      $this->messenger()->addMessage($this->t('No users were created, updated, or found.'));
    }
  }
}
