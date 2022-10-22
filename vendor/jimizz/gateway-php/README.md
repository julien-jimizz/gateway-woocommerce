Jimizz Gateway - PHP Integration Kit
============

[![Latest Stable Version](http://poser.pugx.org/jimizz/gateway-php/v)](https://packagist.org/packages/jimizz/gateway-php)
[![License](https://poser.pugx.org/gino-pane/composer-package-template/license)](https://packagist.org/packages/gino-pane/composer-package-template)

Jimizz Gateway - PHP Integration Kit

Requirements
------------

* PHP >= 7.2;
* composer.

Installation
============

    composer require jimizz/gateway-php

Example
=======
You can find a complete example of integration in the
[example folder](https://github.com/julien-jimizz/gateway-php/tree/main/example).

Create a new transaction
=======

    $gateway = new Gateway(MERCHANT_ID);
    $transaction = $gateway->transaction($mode, $fields);
    $transaction->sign(PRIVATE_KEY);
    $transaction->render();

[Full example](https://github.com/julien-jimizz/gateway-php/blob/main/example/callback.php)

Verify callback notification
=======

    $gateway = new Gateway(MERCHANT_ID);
    if ($gateway->verifyCallback($data)) {
        // Callback data is valid
    }

[Full example](https://github.com/julien-jimizz/gateway-php/blob/main/example/payment.php)

License
=======
Please refer to [LICENSE](https://github.com/julien-jimizz/gateway-php/blob/master/LICENSE).
