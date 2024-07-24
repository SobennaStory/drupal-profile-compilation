<?php
namespace Drupal\profile_comp\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying the view page.
 */
class ProfileUploadController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_upload_form';
  }

  public function access(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'administer profile import');
  }

  public function build() {
    $form_builder = $this->formBuilder();
    return [
      'profile_comp_form' => $form_builder->getForm('Drupal\profile_comp\Form\ProfileCreationForm'),
      'profile_image_form' => $form_builder->getForm('Drupal\profile_comp\Form\ImageCreationForm'),
    ];
  }
}
