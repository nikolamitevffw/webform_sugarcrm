<?php

/**
 * Class SugarCrmClient
 */
class SugarCrmClient {

  protected $session;
  protected $url;
  protected $user;
  protected $password;

  /**
   * {@inheritdoc}
   */
  public function __construct($params) {
    if (isset($params['url'])) {
      $this->url = $params['url'];
    }
    else {
      return NULL;
    }

    if (isset($params['user'])) {
      $this->user = $params['user'];
    }
    else {
      return NULL;
    }

    if (isset($params['password'])) {
      $this->password = $params['password'];
    }
    else {
      return NULL;
    }

    // Open a new connection to the service.
    // This call will also save the session.
    $this->init();
  }

  /**
   * Initialize a new connection by getteing a new session.
   */
  protected function init() {
    $login_parameters = array(
      "user_auth" => array(
        "user_name" => $this->user,
        "password" => $this->password,
        "version" => "1",
      ),
      "name_value_list" => array(),
    );

    $this->session = $this->call("login", $login_parameters);

    // Error handling.
    if (!isset($this->session->id)) {
      if ($this->session->name == 'Invalid Login') {
        throw new Exception('Invalid login');
      }
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
      return;
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

    $post = array(
      "method" => $method,
      "input_type" => "JSON",
      "response_type" => "JSON",
      "rest_data" => $json_encoded_data,
    );

    curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($curl_request);

    curl_close($curl_request);

    $result = explode("\r\n\r\n", $result, 2);

    if (strstr($result[0], '200 OK')) {
      $response = json_decode($result[1]);
    }
    else {
      drupal_set_message(t('An error has occurred whilst retrieving data from the system. Please try again later.'), 'error');
      watchdog('bcms_sugarcrm', 'An error occurred while communicating with SugarCRM. The error was: @error', array('@error' => $result[0] . "\r\n\r\n" . $result[1]), WATCHDOG_ERROR);
      $sugar_comm_failure = TRUE;
    }
    ob_end_flush();

    // Override converts special HTML entities back to characters.
    return $response;
  }

  /**
   * Fetch a list of all available modules.
   */
  public function getModules() {
    $parameters = array(
      'session' => $this->session->id,
    );

    $result = $this->call('get_available_modules', $parameters);

    return $result;
  }

  /**
   * Fetch all fields data belonging to a module.
   */
  public function getModuleFields($module_name) {
    $parameters = array(
      'session' => $this->session->id,
      'module_name' => $module_name,
    );

    $result = $this->call('get_module_fields', $parameters);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function setSugarCrmRecord($module, $field_values) {
    $parameters = array(
      'session' => $this->session->id,
      'module_name' => $module,
      'name_value_list' => $field_values,
    );

    $result = $this->call('set_entry', $parameters);

    return $result;
  }

}
