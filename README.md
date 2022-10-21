# Jimizz Gateway - Woocommerce Integration

## Description
Jimizz Gateway - Woocommerce Integration

This module adds a Jimizz Payment Gateway to your WooCommerce installation.<br>
Jimizz is a cryptocurrency designed by the French leader of the porn industry.

When the Jimizz payment is chosen by your users, they are redirected to the Jimizz Gateway.<br>
Once they paid with Jimizz cryptocurrency on the blockchain, the Jimizz Gateway sends a Payment Notification to your server.<br>
This plugin generates an url that can catch the notification call from the Jimizz Gateway's server.

If payment was successful, this plugin validates the order through WooCommerce.<br>
If payment was declined, the order will be cancelled.

This plugin also offers the possibility to use the Gateway in TEST mode, which allows you to simulate approved and failed payments during your integration tests.

## Requirements
* PHP >= 7.2
* [WordPress](https://wordpress.com/)
* [WooCommerce](https://woocommerce.com/)

## Installation
1. Upload the entire folder `woocommerce-gateway-jimizz` to the `/wp-content/plugins/` directory
   or download as a .zip file and upload through WordPress's plugin install mechanism.

2. Activate the plugin through the 'Plugins' menu in WordPress

## License
Please refer to [LICENSE](https://github.com/julien-jimizz/gateway-woocommerce/blob/master/LICENSE).
