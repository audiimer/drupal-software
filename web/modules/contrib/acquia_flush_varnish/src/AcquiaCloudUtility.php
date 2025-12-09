<?php

namespace Drupal\acquia_flush_varnish;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service to use Acquia Cloud API.
 */
class AcquiaCloudUtility {

  /**
   * The Messenger service.
   */
  protected MessengerInterface $messenger;

  /**
   * Protected requestStack.
   */
  protected RequestStack $requestStack;

  /**
   * Protected configFactory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The current request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   */
  public function __construct(RequestStack $requestStack, MessengerInterface $messenger, ConfigFactoryInterface $configFactory) {
    $this->requestStack = $requestStack;
    $this->messenger = $messenger;
    $this->configFactory = $configFactory;
  }

  /**
   * Fetch access token using API Key and token URL.
   *
   * @return accesstoken[]
   *   Response access token API.
   */
  public function getaccesstoken(): ?array {
    $response = [];
    $config = $this->configFactory->get('acquia_flush_varnish.settings');
    $apikey = $config->get('acquiacloud_apikey');
    $secret = $config->get('acquiacloud_secret');
    $token_url = "https://accounts.acquia.com/api/auth/oauth/token";
    if (isset($apikey) && isset($secret)) {
      $params =
        "grant_type=client_credentials&client_id=" . (string) $apikey
        . "&client_secret=" . (string) $secret;
      $curl = curl_init($token_url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
      $json_response = curl_exec($curl);
      $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if ($status != 200) {
        $response = json_decode($json_response, TRUE);
        return $response;
      }
      curl_close($curl);
      $response = json_decode($json_response, TRUE);
      return $response;
    }
    return $response;
  }

  /**
   * Fetch JSON data.
   *
   * @param string $api_url
   *   Acquia API url.
   * @param string $access_token
   *   Acquia API access token.
   * @param string $method
   *   Acquia API call method.
   * @param string[] $data
   *   Acquia API call post parameters.
   *
   * @return response[]
   *   Acquia cloud API response.
   */
  public function getAcquiaApi(string $api_url, string $access_token, ?string $method = NULL, ?array $data = []): array {
    $response = [];
    if (isset($access_token) && isset($api_url)) {
      $curl = curl_init($api_url);
      curl_setopt($curl, CURLOPT_HEADER, FALSE);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
      if ($method == 'POST') {
        curl_setopt($curl, CURLOPT_POST, TRUE);
        if (is_array($data)) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
      }
      curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
      $json_response = curl_exec($curl);
      $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if ($status != 200) {
        $response = json_decode($json_response, TRUE);
        return $response;
      }
      curl_close($curl);
      $response = json_decode($json_response, TRUE);
    }
    return $response;
  }

  /**
   * Function to clear acquia varnish cache.
   */
  public function acquiaFlushVarnishCache(): RedirectResponse {
    $mydomainenv = $_ENV['AH_SITE_ENVIRONMENT'];
    $mydomain = $_ENV['HTTP_HOST'];
    $myappuuid = $_ENV['AH_APPLICATION_UUID'];
    // Get access token API call.
    $get_access_token = self::getaccesstoken();
    $referer = $this->requestStack->getCurrentRequest()->server->get('HTTP_REFERER');
    if (isset($get_access_token['access_token'])) {
      $access_token = (string) $get_access_token['access_token'];
      $apibaseurl = "https://cloud.acquia.com/api/";
      $apiurl = $apibaseurl . 'applications/' . $myappuuid . '/environments';
      $response = self::getAcquiaApi($apiurl, $access_token);
      if (isset($response['_embedded']['items']) && is_array($response['_embedded']['items'])) {
        $envs = $response['_embedded']['items'];
        $envIds = [];
        foreach ($envs as $env) {
          $envIds[$env['name']] = $env['id'];
        }
      }
      if (isset($envIds[$mydomainenv])) {
        $cache_clear_url = $apibaseurl . 'environments/' . $envIds[$mydomainenv] . '/domains/' . $mydomain . '/actions/clear-caches';
        $response = self::getAcquiaApi($cache_clear_url, $access_token, 'POST');
        $this->messenger->addStatus($response['message']);
      }
    }
    else {
      $this->messenger->addError('Please configure the Acquia API configurations in secret.settings.php outside project repo. Please refer Readme.txt for more information to configure.');
    }
    return new RedirectResponse($referer);
  }

}
