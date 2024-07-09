<?php

namespace Drupal\profile_comp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ImageCreationForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new ImageCreationForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_image_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload a zip file'),
      '#description' => $this->t('Upload a zip file containing images named First_Last.jpg'),
      '#upload_location' => 'public://user_pictures/',
      '#upload_validators' => [
        'file_validate_extensions' => ['zip'],
      ],
      '#required' => TRUE,
    ];

    $form['overwrite_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overwrite existing profile pictures'),
      '#default_value' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = File::load($form_state->getValue('upload')[0]);
    $file->setPermanent();
    $file->save();

    $overwrite = $form_state->getValue('overwrite_existing');

    // Extract the zip file.
    $zip = new \ZipArchive;
    $file_path = $file->getFileUri();
    if ($zip->open($this->fileSystem->realpath($file_path)) === TRUE) {
      $zip->extractTo('public://user_pictures/unzipped/');
      $zip->close();
      $this->messenger()->addMessage($this->t('Zip file extracted successfully.'));

      // Process the extracted files.
      $this->processExtractedFiles('public://user_pictures/unzipped/', $overwrite);
    } else {
      $this->messenger()->addError($this->t('Failed to open the zip file.'));
    }
  }

  /**
   * Process the extracted files.
   *
   * @param string $directory
   *   The directory where files were extracted.
   * @param bool $overwrite
   *   Whether to overwrite existing profile pictures.
   */
  protected function processExtractedFiles($directory, $overwrite) {
    $files = $this->fileSystem->scanDirectory($directory, '/.*\.(jpg|jpeg|png|gif)$/i');
    foreach ($files as $file) {
      $filename = pathinfo($file->name, PATHINFO_FILENAME);
      list($first_name, $last_name) = explode('_', $filename);

      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $first_name . ' ' . $last_name]);
      if ($users) {
        $user = reset($users);
        $this->setUserPicture($user, $file->uri, $overwrite);
      } else {
        $this->messenger()->addWarning($this->t('No user found for @name.', ['@name' => $first_name . ' ' . $last_name]));
      }
    }
  }

  /**
   * Set the user's picture.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param string $file_uri
   *   The URI of the file.
   * @param bool $overwrite
   *   Whether to overwrite existing profile pictures.
   */
  protected function setUserPicture(User $user, $file_uri, $overwrite) {
    if ($user->get('user_picture')->isEmpty() || $overwrite) {
      $file = File::create(['uri' => $file_uri]);
      $file->save();
      $user->set('user_picture', $file->id());
      $user->save();
      $this->messenger()->addMessage($this->t('Set picture for @user.', ['@user' => $user->getDisplayName()]));
    } else {
      $this->messenger()->addMessage($this->t('Skipped setting picture for @user as they already have one.', ['@user' => $user->getDisplayName()]));
    }
  }
}
