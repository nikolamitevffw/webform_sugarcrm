<?php

namespace Drupal\webform_sugarcrm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * Class WebformFieldsMapping
 *
 * @package Drupal\webform_sugarcrm\Form
 */
class WebformFieldsMapping extends FormBase{

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_fields_mapping';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $webform_name = \Drupal::request()->get('webform');
    $webform = \Drupal::entityTypeManager()->getStorage('webform')->load($webform_name);
    $webform_elements = $webform->get('elements');
    $elements = Yaml::decode($webform_elements);

    $storage = array('webform' => $webform, 'elements' => $elements);
    $form_state->setStorage($storage);

    $config = \Drupal::configFactory()->get('webform_sugarcrm.webform_field_mapping.' . $webform->id());
    $default_values = $config->getRawData();
    // Get SugarCRM modules list.
    $sugarcrm_modules = webform_sugarcrm_get_sugarcrm_modules();

    // Create component conteiner.
    $form['webform_container'] = array(
      '#prefix' => "<div id=form-ajax-wrapper>",
      '#suffix' => "</div>",
    );

    // Get webform fields and default values for them.
    foreach ($elements as $key => $element) {
      $selected_module = '_none';
      $selected_field = '_none';

      if (!empty($default_values[$key])) {
        $selected_module = $default_values[$key]['sugar_module'];
        $selected_field = $default_values[$key]['sugar_field'];
      }

      $selected_module = !empty($form_state->getValue($key . '_sugarcrm_module')) ?
        $form_state->getValue($key . '_sugarcrm_module') : $selected_module;

      // Create form elements for each Webform field.
      $form['webform_container'][$key] = array(
        '#type' => 'fieldset',
        '#title' => $element['#title'],
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      );
      $form['webform_container'][$key][$key . '_sugarcrm_module'] = array(
        '#type' => 'select',
        '#options' => $sugarcrm_modules,
        '#title' => t('Select SugarCRM module'),
        '#default_value' => $selected_module,
        '#ajax' => array(
          'callback' => 'Drupal\webform_sugarcrm\Form\WebformFieldsMapping::formAjaxCallback',
          'wrapper' => 'form-ajax-wrapper',
          'method' => 'replace',
          'event' => 'change',
        ),
      );
      $form['webform_container'][$key][$key . '_sugarcrm_field'] = array(
        '#type' => 'select',
        '#options' => webform_sugarcrm_get_sugarcrm_module_fields($selected_module),
        '#default_value' => $selected_field,
        '#title' => t('Select SugarCRM module field'),
      );
    }

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
    $storage = $form_state->getStorage();
    $webform = $storage['webform'];
    $elements = $storage['elements'];
    $config = \Drupal::configFactory()->getEditable('webform_sugarcrm.webform_field_mapping.' . $webform->id());

    $values = $form_state->getValues();

    $data = array();
    foreach ($elements as $key => $element) {
      $data[$key] = array(
        'sugar_module' => $values[$key . '_sugarcrm_module'],
        'sugar_field' => $values[$key . '_sugarcrm_field'],
      );
    }

    $config->setData($data);
    $config->save(TRUE);
    drupal_set_message('Fields mapping have been saved.');

  }

  /**
   * Ajax callback.
   */
  public function formAjaxCallback($form, $form_state) {
    return $form['webform_container'];
  }

}
