<?php

namespace Jimizz\Gateway;

use Exception;

class WeiConverter
{
  const UNITS = [
    'wei' => '1',
    'gwei' => '1000000000',
    'ether' => '1000000000000000000'
  ];

  /**
   * @throws Exception
   */
  public function fromWei(
    string $amount,
    string $unit = 'ether'
  ): string
  {
    $this->_checkUnit($unit);
    if ($unit === 'wei') {
      return $amount;
    }

    $zeroes = substr_count(self::UNITS[$unit], 0);
    $decimals = strlen($amount) - strpos($amount, '.') - 1;

    $div = bcdiv($amount, self::UNITS[$unit], $zeroes + $decimals);
    return rtrim(rtrim($div,'0'),'.');
  }

  /**
   * @throws Exception
   */
  public function toWei(
    string $amount,
    string $unit = 'ether'
  ): string
  {
    $this->_checkUnit($unit);
    if ($unit === 'wei') {
      return $amount;
    }

    return bcmul($amount, self::UNITS[$unit]);
  }

  /**
   * @throws Exception
   */
  private function _checkUnit(string $unit) {
    if (!in_array($unit, array_keys(self::UNITS))) {
      throw new Exception('Not supported unit ' . $unit);
    }
  }
}