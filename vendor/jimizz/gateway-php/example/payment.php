<?php

require('vendor/autoload.php');

use Jimizz\Gateway\Currency;
use Jimizz\Gateway\Gateway;
use Jimizz\Gateway\TransactionType;

// Your Merchant ID - Will be provided by Jimizz Team
const MERCHANT_ID = 'MERCHANT_ID';

// Your private keys - Will be provided by Jimizz Team
// We strongly recommend that you store your private keys in an object store (AWS S3, Google Cloud Storage)
// or at least in your environment variables.
const PRIVATE_KEY_PROD = 'ABCDEF0123456789ABCDEF0213456789ABCDEF0123456789ABCDEF0123456789';
const PRIVATE_KEY_TEST = 'ABCDEF0123456789ABCDEF0213456789ABCDEF0123456789ABCDEF0123456789';

$gateway = new Gateway(MERCHANT_ID);
$transaction = $gateway->transaction(
// Type of transaction
// 'production' | 'testApproved' | 'testRejected'
  TransactionType::PRODUCTION,
  [
    // Transaction ID
    'transactionId' => 'ABC1',

    // A string representing an integer
    // When using a Fiat currency (USD / EUR), must be represented in cents (1.2$ = “120”);
    // When using a Crypto currency (JMZ / BNB), must be represented in wei (1.2BNB = ”1200000000000000000”)
    'amount' => '3000',

    // Currency used for the above amount
    'currency' => Currency::EUR,

    // Success URL
    // URL to which the user is redirected when the payment is accepted
    'successUrl' => 'https://www.example.com/success',

    // Error URL
    // URL to which the user is redirected when the payment is declined
    'errorUrl' => 'https://www.example.com/error',

    // Callback URL
    // URL for server to server notification
    'callbackUrl' => 'https://www.example.com/s2s',

    // (Optional) - Cancel URL
    // URL to which the user is redirected if they click on the cancel payment link
    // If not provided, errorUrl will be used
    'cancelUrl' => 'https://www.example.com/cancel',

    // (Optional) - Your merchant name
    // Used for display on the frontend
    // If not specified, merchantId will be displayed
    'merchantName' => 'test name',

    // (Optional) - Your merchant url
    // Used for display on the frontend
    'merchantUrl' => 'www.test-url.com',
  ]);

// Sign the data with your private key
// Use your production key for 'production' mode
// Use you test key for 'testApproved' and 'testRejected' modes
$transaction->sign(PRIVATE_KEY_PROD);

// Render the form and redirect user to the Jimizz Gateway
?>
<body onload="document.getElementById('jimizz-form').submit()">
<?php $transaction->render(); ?>
</body>