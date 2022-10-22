<?php

namespace Jimizz\Gateway;

use Exception;

class Gateway
{
  public const API_BASE = 'https://gateway.jimizz.com/api';

  private $_merchantId;

  public function __construct(
    string $merchantId
  )
  {
    $this->_merchantId = $merchantId;
  }

  /**
   * @throws Exception
   */
  public function transaction(
    string $transaction_type,
    array  $fields
  ): Transaction
  {
    return new Transaction(
      array_merge([
        'merchantId' => $this->_merchantId,
        'type' => $transaction_type,
      ], $fields)
    );
  }

  public function verifyCallback(object $payload): bool
  {
    if (!property_exists($payload, 'transactionId') ||
      !property_exists($payload, 'status') ||
      !property_exists($payload, 'keyId') ||
      !property_exists($payload, 'signature')) {
      return false;
    }

    $public_key = $this->retrieveKey($payload->keyId);
    if (!$public_key) {
      return false;
    }

    $payload = new Payload($payload);
    return $payload->verify($public_key);
  }

  public function retrieveKey(string $id): ?string
  {
    $key = $this->_doCall('/keys/' . $id, 'GET');
    if (is_object($key) && property_exists($key, 'public')) {
      return $key->public;
    } else {
      return null;
    }
  }

  private function _doCall(string $url, string $method, $data = null): ?object
  {
    $curl = curl_init();

    switch ($method) {
      case 'POST':
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        break;

      case 'PUT':
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        if ($data) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        break;

      default:
        if ($data) {
          $url = sprintf("%s?%s", $url, http_build_query($data));
        }
    }

    curl_setopt($curl, CURLOPT_URL, self::API_BASE . $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      'Accept: application/json',
      'Content-Type: application/json',
    ]);

    $result = curl_exec($curl);
    if ($result) {
      $result = json_decode($result);
    }
    curl_close($curl);

    return $result;
  }
}
