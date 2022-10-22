<?php

use Jimizz\Gateway\Gateway;

require('vendor/autoload.php');

// Your Merchant ID - Will be provided by Jimizz Team
const MERCHANT_ID = 'MERCHANT_ID';

// Retrieve JSON data
$data = json_decode(file_get_contents('php://input'));

// Check data and signature
$gateway = new Gateway(MERCHANT_ID);
if ($gateway->verifyCallback($data)) {
  // Callback data is valid
}
