<?php

namespace Drupal\webform_sugarcrm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\webform_sugarcrm\WebformSugarCrmManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WebformSugarCrmFieldsMapping
 *
 * @package Drupal\webform_sugarcrm\Form
 */
class WebformSugarCrmFieldsMapping extends FormBase{

  /**
   * Stores Sugar CRM manager.
   *
   * @var \Drupal\webform_sugarcrm\SugarCrmManager
   */
  private $sugarCrm;

  public function __construct(WebformSugarCrmManager $sugarCrm) {
    $this->sugarCrm = $sugarCrm;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static ($container->get('webform_sugarcrm.sugarcrm_manager'));
  }

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
    try {
      $this->sugarCrm->login();

      $webform = \Drupal::entityTypeManager()->getStorage('webform')
        ->load(\Drupal::request()->get('webform'));
      $elements = $elements = Yaml::decode($webform->get('elements'));

      $form_state->setStorage(['webformId' => $webform->id(), 'elements' => $elements]);
      // Create component container.
      $form['webform_container'] = array(
        '#prefix' => "<div id=form-ajax-wrapper>",
        '#suffix' => "</div>",
      );

      // Get webform fields and default values for them.
      $default_values = $this->config('webform_sugarcrm.webform_field_mapping.' . $webform->id())->getRawData();
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
          '#title' => isset($element['#title']) ? $element['#title'] : $element['#type'],
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        );
        $form['webform_container'][$key][$key . '_sugarcrm_module'] = array(
          '#type' => 'select',
          '#options' => $this->getModules(),
          '#title' => t('Select SugarCRM module'),
          '#default_value' => $selected_module,
          '#ajax' => array(
            'callback' => 'Drupal\webform_sugarcrm\Form\WebformSugarCrmFieldsMapping::formAjaxCallback',
            'wrapper' => 'form-ajax-wrapper',
            'method' => 'replace',
            'event' => 'change',
          ),
        );
        $form['webform_container'][$key][$key . '_sugarcrm_field'] = array(
          '#type' => 'select',
          '#options' => $this->getModuleFields($selected_module),
          '#default_value' => $selected_field,
          '#title' => t('Select SugarCRM module field'),
        );
      }

      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save'),
      );

    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
      return [];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $storage = $form_state->getStorage();

    $data = [];
    foreach ($storage['elements'] as $key => $element) {
      $data[$key] = array(
        'sugar_module' => $values[$key . '_sugarcrm_module'],
        'sugar_field' => $values[$key . '_sugarcrm_field'],
      );
    }

    $config = \Drupal::configFactory()->getEditable('webform_sugarcrm.webform_field_mapping.' . $storage['webformId']);
    $config->setData($data);
    $config->save(TRUE);

    \Drupal::messenger()->addMessage(t('Fields mapping have been saved.'));
  }

  /**
   * Ajax callback.
   */
  public function formAjaxCallback($form, $form_state) {
    return $form['webform_container'];
  }

  /**
   * Get prepared list of CRM modules.
   *
   *
   * @return mixed
   *   Returns a list of CRM modules.
   */
  private function getModules() {
    $modules = ['_none' => 'None'];

    $crmModules = $this->sugarCrm->getModules();
    if (isset($crmModules->modules)) {
      foreach ($crmModules->modules as $module) {
        $modules[$module->module_key] = $module->module_key;
      }
    }

    return $modules;
  }
  /**
   * Get prepared list of module fields.
   *
   * @param $module
   *   Module name.
   *
   * @return mixed
   *   Returns a list of module fields.
   */
  private function getModuleFields($module) {
    $fields = array('_none' => 'None');

    $crmFields = $this->sugarCrm->getModuleFields($module);

    // Build module fields list.
    if (!empty($crmFields->module_fields)) {
      foreach ($crmFields->module_fields as $field) {
        $fields[$field->name] = $field->required ? $field->name . ' *' : $field->name;
      }
    }

    return $fields;
  }

}
