<?php

/**
 * @file
 * Administrative operations.
 */

/**
 * SugarCRM integration configuration form.
 *
 * Path: admin/config/services/sugarcrm.
 */
function webform_sugarcrm_integration_sugarcrm_config() {
  $form = array();

  $restful_url = variable_get('webform_sugarcrm_restful_url', '');
  $user = variable_get('webform_sugarcrm_service_user', '');

  $form['sugarcrm_restful_url'] = array(
    '#title' => t('Restful URL'),
    '#description' => t('Restful URL to connect to custom created functions.'),
    '#type' => 'textfield',
    '#default_value' => $restful_url,
  );

  $form['sugarcrm_service_user'] = array(
    '#title' => t('Service username'),
    '#type' => 'textfield',
    '#default_value' => $user,
  );

  $form['sugarcrm_service_password'] = array(
    '#title' => t('Service password'),
    '#type' => 'password',
    '#description' => t('If you want to keep your old password, leave this field blank.'),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );

  return $form;
}

/**
 * Validate handler for SugarCRM integration form.
 */
function webform_sugarcrm_integration_sugarcrm_config_validate($form, &$form_state) {
  if (empty($form_state['values']['sugarcrm_service_password'])) {
    form_set_value($form['sugarcrm_service_password'], variable_get('webform_sugarcrm_service_password'), $form_state);
  }
  else {
    form_set_value($form['sugarcrm_service_password'], md5($form_state['values']['sugarcrm_service_password']), $form_state);
  }

  // Test the connection settings.
  $url = $form_state['values']['sugarcrm_restful_url'];
  $user = $form_state['values']['sugarcrm_service_user'];
  $password = $form_state['values']['sugarcrm_service_password'];

  $params = array(
    'url' => $url,
    'user' => $user,
    'password' => $password,
  );

  try {
    $client = new SugarCrmClient($params);
    if (!empty($client->getSession()->id)) {
      drupal_set_message(t('A connection to the SugarCRM instance could be established.'));
    }
    else {
      drupal_set_message(t('Unable to establish a session to the SugarCRM instance. Please check you credentials.'));
    }
  }
  catch (Exception $exc) {
    watchdog('sugarcrm', $exc->getMessage(), NULL, WATCHDOG_ERROR);
    drupal_set_message(t("It seems like your credentials isn't valid, or the SugarCRM webservice instance is down."), 'error');
  }
}

/**
 * Submit handler for SugarCRM integration form.
 */
function webform_sugarcrm_integration_sugarcrm_config_submit($form, &$form_state) {
  if (!empty($form_state['values']['sugarcrm_restful_url'])) {
    variable_set('webform_sugarcrm_restful_url', $form_state['values']['sugarcrm_restful_url']);
  }
  if (!empty($form_state['values']['sugarcrm_service_user'])) {
    variable_set('webform_sugarcrm_service_user', $form_state['values']['sugarcrm_service_user']);
  }
  if (!empty($form_state['values']['sugarcrm_service_password'])) {
    variable_set('webform_sugarcrm_service_password', $form_state['values']['sugarcrm_service_password']);
  }
}

/**
 * SugarCRM field mapping page.
 */
function webform_sugarcrm_integration_mapping_config($form, $form_state, $node) {

  // Get SugarCRM modules list.
  $sugarcrm_modules = webform_sugarcrm_integration_mapping_get_sugarcrm_modules();

  // Create component conteiner.
  $form['webform_container'] = array(
    '#prefix' => "<div id=form-ajax-wrapper>",
    '#suffix' => "</div>",
  );
  $form['webform_container']['webformform_id'] = array(
    '#type' => 'hidden',
    '#value' => $node->uuid,
  );

  // Get webform fields and default values for them.
  $fields = $node->webform['components'];
  $default_values = variable_get('webform_' . $node->uuid);

  foreach ($fields as $field) {
    $selected_module = '_none';
    $selected_field = '_none';

    if (!empty($default_values[$field['form_key']])) {
      $selected_module = $default_values[$field['form_key']]['sugar_module'];
      $selected_field = $default_values[$field['form_key']]['sugar_field'];
    }

    $selected_module = isset($form_state['values'][$field['form_key'] . '_sugarcrm_module']) ?
      $form_state['values'][$field['form_key'] . '_sugarcrm_module'] : $selected_module;

    // Create form elements for each Webform field.
    $form['webform_container'][$field['form_key']] = array(
      '#type' => 'fieldset',
      '#title' => $field['name'],
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['webform_container'][$field['form_key']][$field['form_key'] . '_sugarcrm_module'] = array(
      '#type' => 'select',
      '#options' => $sugarcrm_modules,
      '#title' => t('Select SugarCRM module'),
      '#default_value' => $selected_module,
      '#ajax' => array(
        'callback' => 'webform_sugarcrm_mapping_integration_sugarcrm_module_fields',
        'wrapper' => 'form-ajax-wrapper',
        'method' => 'replace',
        'event' => 'change',
      ),
    );
    $form['webform_container'][$field['form_key']][$field['form_key'] . '_sugarcrm_field'] = array(
      '#type' => 'select',
      '#options' => webform_sugarcrm_integration_mapping_get_sugarcrm_module_fields($selected_module),
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
 * Ajax callback.
 */
function webform_sugarcrm_mapping_integration_sugarcrm_module_fields($form, $form_state) {
  return $form['webform_container'];
}

/**
 * Helper function which gets SugarCRM modules.
 */
function webform_sugarcrm_integration_mapping_get_sugarcrm_modules() {
  $sugarcrm_mudule_options = array('_none' => 'None');

  // Call SugarCRM and get available modules.
  $sugarcrm_client = webform_sugarcrm_integration_client_init();
  $sugarcrm_mudules = $sugarcrm_client->getModules();

  // Build modules list.
  foreach ($sugarcrm_mudules->modules as $module) {
    $sugarcrm_mudule_options[$module->module_key] = $module->module_key;
  }

  return $sugarcrm_mudule_options;
}

/**
 * Helper function which gets SugarCRM module fields.
 */
function webform_sugarcrm_integration_mapping_get_sugarcrm_module_fields($module = NULL) {
  $sugarcrm_mudule_field_options = array('_none' => 'None');

  if (isset($module) && $module !== '_none') {
    // Call SugarCRM and get module's fields.
    $sugarcrm_client = webform_sugarcrm_integration_client_init();
    $sugarcrm_mudule_fields = $sugarcrm_client->getModuleFields($module);

    // Build module fields list.
    if (!empty($sugarcrm_mudule_fields)) {
      foreach ($sugarcrm_mudule_fields->module_fields as $field) {
        $sugarcrm_mudule_field_options[$field->name] = $field->required ? $field->name . ' *' : $field->name;
      }
    }
  }

  return $sugarcrm_mudule_field_options;
}

/**
 * SugarCRM mapping form submit handler.
 */
function webform_sugarcrm_integration_mapping_config_submit($form, &$form_state) {
  if ($form['#form_id'] === 'webform_sugarcrm_integration_mapping_config'){
    $field_groups = $form_state['groups'];
    $webform_uuid = $form_state['values']['webformform_id'];

    // Build fields mapping array.
    $data = array();
    if (!empty($field_groups)) {
      foreach ($field_groups as $group_key => $group_value) {
        $data[$group_key] = array(
          'sugar_module' => $form_state['values'][$group_key . '_sugarcrm_module'],
          'sugar_field' => $form_state['values'][$group_key . '_sugarcrm_field'],
        );
      }

      // Save fields mapping.
      variable_set('webform_' . $webform_uuid, $data);
      drupal_set_message('Fields mapping have been saved.');
    }
  }
}
