<?php

namespace Drupal\webform_sugarcrm;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class SugarCrmManager
 */
class SugarCrmManager {

  protected $session;
  protected $url;
  protected $user;
  protected $password;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $config = $configFactory->get('webform_sugarcrm.sugarcrm_configuration');

    $this->url = $config->get('url');
    $this->user = $config->get('user');
    $this->password = $config->get('password');
  }

  /**
   * Initialize Sugar CRM session.
   *
   * @throws \Exception
   *   Throws un exception in case of invalid login.
   */
  public function login() {
    $params = [
      "user_auth" => [
        "user_name" => $this->user,
        "password" => $this->password,
        "version" => "1",
      ],
      "name_value_list" => [],
    ];

    // Initialize Sugar CRM connection.
    $this->session = $this->call("login", $params);

    // Error handling.
    if (empty($this->session)) {
      throw new \Exception(t('Invalid login'));
    }
    else if (!isset($this->session->id) && $this->session->name === 'Invalid Login') {
      $message = !empty($this->session->description) ? $this->session->description : $this->session->name;
      throw new \Exception($message);
    }
  }
  /**
   * Getter for $session.
   *
   * @return object
   *  Public function getSession object.
   */
  public function getSession() {
    return $this->session;
  }

  /**
   * Call the service.
   */
  protected function call($method, $parameters) {
    // If the communication has failed once, don't try to establish a new
    // connection.
    $sugar_comm_failure = &drupal_static(__FUNCTION__ . '_failure');

    if ($sugar_comm_failure) {
      return FALSE;
    }

    ob_start();
    $curl_request = curl_init();

    curl_setopt($curl_request, CURLOPT_URL, $this->url);
    curl_setopt($curl_request, CURLOPT_POST, 1);
    curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($curl_request, CURLOPT_HEADER, 1);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

    $json_encoded_data = json_encode($parameters);

    $post = [
      "method" => $method,
      "input_type" => "JSON",
      "response_type" => "JSON",
      "rest_data" => $json_encoded_data,
    ];

    curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($curl_request);

    curl_close($curl_request);

    if (empty($result)) {
      return FALSE;
    }
    else {
      $result = explode("\r\n\r\n", $result, 2);
      $response = FALSE;

      if (strstr($result[0], '200 OK')) {
        $response = json_decode($result[1]);
      }
      else {
        drupal_set_message(t('An error has occurred whilst retrieving data from the system. Please try again later.'), 'error');
        \Drupal::logger('bcms_sugarcrm')->error('An error occurred while communicating with SugarCRM. The error was: @error', array('@error' => $result[0] . "\r\n\r\n" . $result[1]));
        $sugar_comm_failure = TRUE;
      }
      ob_end_flush();

      return $response;
    }
  }

  /**
   * Fetch a list of all available modules.
   */
  public function getModules() {
    $parameters = [
      'session' => $this->session->id,
    ];

    $result = $this->call('get_available_modules', $parameters);

    return $result;
  }

  /**
   * Fetch all fields data belonging to a module.
   */
  public function getModuleFields($module_name) {
    $parameters = [
      'session' => $this->session->id,
      'module_name' => $module_name,
    ];

    $result = $this->call('get_module_fields', $parameters);

    return $result;
  }

  /**
   * Sets record in SugarCRM
   *
   * @param $module
   *  SugerCRM module.
   *
   * @param $field_values
   *
   * @return mixed
   */
  public function setSugarCrmRecord($module, $field_values) {
    $parameters = [
      'session' => $this->session->id,
      'module_name' => $module,
      'name_value_list' => $field_values,
    ];

    $result = $this->call('set_entry', $parameters);

    return $result;
  }

}
