<?php

namespace Jimizz\Gateway;

use Elliptic\EC;
use Exception;
use Throwable;

class Payload
{
  private $_fields;

  public function __construct($fields)
  {
    if (is_object($fields)) {
      $fields = (array)$fields;
    }
    $this->_fields = $fields;
  }

  public function getRawFields(): array
  {
    return $this->_fields;
  }

  public function sign(string $private_key): string
  {
    $flat = $this->_flatPayload();
    $hash = $this->_hash($flat);

    $ec = new EC('secp256k1');
    $ecPrivateKey = $ec->keyFromPrivate($private_key, 'hex');
    $signature = $ecPrivateKey->sign($hash, ['canonical' => true]);
    return $signature->toDER('hex');
  }

  /**
   * @throws Exception
   */
  public function verify(string $public_key): bool
  {
    if (!isset($this->_fields['signature'])) {
      throw new Exception('Cannot verify - Payload does not contain signature');
    }

    try {
      $flat = $this->_flatPayload();
      $hash = $this->_hash($flat);

      $ec = new EC('secp256k1');
      return @$ec->verify($hash, $this->_fields['signature'], $public_key, 'hex');
    } catch (Throwable $t) {
      return false;
    }
  }

  private function _flatPayload(array $fields = null): string
  {
    if (!$fields) {
      $fields = $this->_fields;
      unset($fields['signature']);
    }

    ksort($fields);
    return array_reduce(
      array_keys($fields),
      function ($acc, $key) use ($fields) {
        return $acc
          . $key
          . '+'
          . (
          is_object($fields[$key])
            ? preg_replace('~\+$~', '', $this->_flatPayload((array)$fields[$key]))
            : $fields[$key])
          . '+';
      }, '');
  }

  private function _hash(string $flat): string
  {
    return hash('sha256', $flat);
  }
}
