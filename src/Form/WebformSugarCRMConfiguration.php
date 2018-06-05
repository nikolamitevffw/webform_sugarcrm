<?php

namespace Drupal\webform_sugarcrm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class WebformSugarCRMConfiguration
 *
 * @package Drupal\webform_sugarcrm\Form
 */
class WebformSugarCRMConfiguration extends FormBase{

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_sugarcrm_configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('webform_sugarcrm.sugarcrm_configuration');

    $form['sugarcrm_restful_url'] = array(
      '#title' => t('Restful URL'),
      '#description' => t('Restful URL to connect to custom created functions.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('url'),
    );

    $form['sugarcrm_service_user'] = array(
      '#title' => t('Service username'),
      '#type' => 'textfield',
      '#default_value' => $config->get('user'),
    );

    $form['sugarcrm_service_password'] = array(
      '#title' => t('Service password'),
      '#type' => 'password',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('webform_sugarcrm.sugarcrm_configuration');

    $url = $form_state->getValue('sugarcrm_restful_url');
    $user = $form_state->getValue('sugarcrm_service_user');
    $pass = $form_state->getValue('sugarcrm_service_password');
    if (!empty($pass)) {
      $pass = md5($form_state->getValue('sugarcrm_service_password'));
    }

    $config->set('url', $url);
    $config->set('user', $user);
    $config->set('password', $pass);

    $config->save(TRUE);

    try {
      // Initialize Sugar CRM manager and try to login in the CRM.
      $manager = \Drupal::service('webform_sugarcrm.sugarcrm_manager');
      $manager->login();

      \Drupal::messenger()->addMessage(t('A connection to the SugarCRM instance could be established.'));
    }
    catch (\Exception $e) {
      \Drupal::logger('webform_sugarcrm')->error($e->getMessage());

      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

}
