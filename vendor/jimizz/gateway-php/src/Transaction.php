<?php

namespace Jimizz\Gateway;

use Exception;

class Transaction
{
  private $_payload;
  private $_signature;

  public function __construct($fields)
  {
    $this->_payload = new Payload($fields);
  }

  public function sign(string $private_key)
  {
    $this->_signature = $this->_payload->sign($private_key);
  }

  /**
   * @param string $form_id Form ID
   * @param bool $echo Whether to echo the form or return it as a string
   * @return string | void
   * @throws Exception
   */
  public function render(string $form_id = 'jimizz-form', bool $echo = true)
  {
    if (empty($this->_signature)) {
      throw new Exception('You must sign first');
    }

    $form = '<form action="' . Gateway::API_BASE . '" method="post" id="' . $form_id . '">';
    foreach ($this->_payload->getRawFields() as $key => $value) {
      $form .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
    }
    $form .= '<input type="hidden" name="signature" value="' . htmlspecialchars($this->_signature) . '">';
    $form .= '<button type="submit">Send</button>';
    $form .= '</form>';

    if ($echo) {
      echo $form;
    } else {
      return $form;
    }
  }
}
