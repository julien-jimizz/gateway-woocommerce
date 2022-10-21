<?php
/**
 * Plugin Name: WooCommerce Jimizz Gateway
 * Plugin URI: https://www.jimizz.com/
 * Description: Jimizz Payment Gateway Woocommerce Integration
 * Author: Jimizz Team
 * Author URI: https://www.jimizz.com/
 * Version: 1.0.0
 * Text Domain: wc-gateway-jimizz
 *
 * Copyright: (c) 2022 Jimizz
 *
 * @author    Jimizz Team
 * @copyright Copyright: (c) 2022 Jimizz
 */

use Jimizz\Gateway\Currency;
use Jimizz\Gateway\Gateway;
use Jimizz\Gateway\TransactionType;

if (!defined('ABSPATH')) {
  exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
  return;
}

(function () {
  define('JIMIZZ_DOMAIN_TEXT', 'woocommerce-jimizz');

  $autoload_filepath = __DIR__ . '/vendor/autoload.php';
  if (file_exists($autoload_filepath)) {
    require $autoload_filepath;
  }

  /**
   * Add the Jimizz Gateway to WS available gateways
   *
   * @param array $gateways All available WC Gateways
   * @return array $gateways All available WC Gateways + Jimizz Gateway
   */
  add_filter('woocommerce_payment_gateways', 'jimizz_add_gateway_class');
  function jimizz_add_gateway_class($gateways)
  {
    $gateways[] = 'WC_Jimizz_Gateway';
    return $gateways;
  }

  add_action('plugins_loaded', 'jimizz_init_gateway_class', 11);
  function jimizz_init_gateway_class()
  {
    load_plugin_textdomain(
      JIMIZZ_DOMAIN_TEXT,
      false,
      dirname(plugin_basename(__FILE__)) . '/lang/'
    );


    /**
     * Jimizz Payment Gateway
     *
     * @class WC_jimizz_Gateway
     * @extends WC_Payment_Gateway
     * @author Jimizz Team
     */
    class WC_Jimizz_Gateway extends WC_Payment_Gateway
    {
      public function __construct()
      {
        $this->id = 'jimizz';
        $this->icon = plugins_url('img/icon.svg', __FILE__);
        $this->has_fields = false;
        $this->method_title = 'Jimizz Gateway';
        $this->method_description = 'Jimizz Gateway';
        $this->supports = ['products'];

        // Plugin options
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->mode = $this->get_option('mode');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->private_key = $this->mode === TransactionType::PRODUCTION ? $this->get_option('private_key') : $this->get_option('test_private_key');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);

        // below is the hook you need for that purpose
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);

        add_action('woocommerce_api_' . $this->id . '_s2s', [$this, 's2s']);
      }

      /**
       * Gateway admin settings
       * @return void
       */
      public function init_form_fields()
      {
        $this->form_fields = apply_filters('wc_jimizz_form_fields', [
          'enabled' => [
            'title' => __('Enable/Disable', JIMIZZ_DOMAIN_TEXT),
            'label' => __('Enable Jimizz Gateway', JIMIZZ_DOMAIN_TEXT),
            'type' => 'checkbox',
            'default' => 'yes'
          ],

          'title' => [
            'title' => __('Title', JIMIZZ_DOMAIN_TEXT),
            'description' => __('This controls the title which the user sees during checkout.', JIMIZZ_DOMAIN_TEXT),
            'type' => 'text',
            'default' => __('Jimizz', JIMIZZ_DOMAIN_TEXT),
          ],

          'description' => [
            'title' => __('Description', JIMIZZ_DOMAIN_TEXT),
            'type' => 'textarea',
            'description' => __('Payment method description that the customer will see on your checkout.', JIMIZZ_DOMAIN_TEXT),
            'default' => __('Pay with Jimizz Cryptocurrency', JIMIZZ_DOMAIN_TEXT),
          ],

          'mode' => [
            'title' => __('Mode', JIMIZZ_DOMAIN_TEXT),
            'description' => __('Place the Jimizz gateway in test mode using test private key.', JIMIZZ_DOMAIN_TEXT),
            'type' => 'select',
            'options' => [
              TransactionType::PRODUCTION => __('Production', JIMIZZ_DOMAIN_TEXT),
              TransactionType::TEST_APPROVED => __('Test APPROVED', JIMIZZ_DOMAIN_TEXT),
              TransactionType::TEST_REJECTED => __('Test REJECTED', JIMIZZ_DOMAIN_TEXT),
            ],
            'default' => 'no',
          ],

          'merchant_id' => [
            'title' => __('Merchant ID', JIMIZZ_DOMAIN_TEXT),
            'type' => 'text',
          ],

          'test_private_key' => [
            'title' => __('Test Private Key', JIMIZZ_DOMAIN_TEXT),
            'type' => 'password',
          ],

          'private_key' => [
            'title' => __('Production Private Key', JIMIZZ_DOMAIN_TEXT),
            'type' => 'password'
          ]
        ]);
      }

      /**
       * Process payment - Redirect to the receipt page where the form will be rendered
       * @param $order_id
       * @return array
       */
      public function process_payment($order_id)
      {
        $order = wc_get_order($order_id);

        return [
          'result' => 'success',
          'redirect' => $order->get_checkout_payment_url(true)
        ];
      }

      /**
       * Receipt page - Render the transaction form and redirect user to Jimizz Gateway
       * @param $order_id
       * @return void
       */
      public function receipt_page($order_id)
      {
        $order = wc_get_order($order_id);

        $order->add_order_note(__('Order placed and user redirected to Jimizz Gateway.', JIMIZZ_DOMAIN_TEXT));

        $gateway = new Gateway($this->merchant_id);

        $fields = [
          'transactionId' => $order->get_id(),
          'amount' => $order->get_total() * 100,
          'currency' => Currency::EUR,
          'successUrl' => $this->get_return_url($order),
          'errorUrl' => $order->get_checkout_payment_url(),
          'callbackUrl' => add_query_arg([
            'wc-api' => 'jimizz_s2s',
            'id' => $order->get_id()
          ], home_url('/')),
          'cancelUrl' => $order->get_checkout_payment_url(),
          'merchantName' => get_bloginfo('name'),
          'merchantUrl' => preg_replace('~^https?://~i', '', get_bloginfo('url')),
        ];
        $transaction = $gateway->transaction($this->mode, $fields);

        $transaction->sign($this->private_key);

        // Display redirecting message and render form
        $form_id = 'jimizz-gateway-form';
        echo '<p>' . __('Redirecting to payment provider.', JIMIZZ_DOMAIN_TEXT) . '</p>';
        $transaction->render($form_id);

        // Validate form
        wc_enqueue_js('document.getElementById("' . $form_id . '").submit();');
      }

      /**
       * Server-to-server callback notification
       */
      public function s2s()
      {
        $data = json_decode(file_get_contents('php://input'));

        try {
          header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK', true, 200);
          $order = wc_get_order($_GET['id']);

          $gateway = new Gateway('MERCHANT_ID');
          if ($gateway->verifyCallback($data)) {
            // Payment succeed - validate order
            $order->add_order_note(__('Jimizz Gateway accepted payment.', JIMIZZ_DOMAIN_TEXT));
            $order->add_order_note(sprintf(__('Transaction Hash: %s.', JIMIZZ_DOMAIN_TEXT), $data->hash));
            $order->payment_complete($data->hash ?? '');
            update_option('webhook_debug', $data);

            echo $this->get_return_url($order);
          } else {
            // Payment failed - cancel order
            $order->update_status(
              'cancelled',
              __('Jimizz Gateway declined payment.', JIMIZZ_DOMAIN_TEXT)
            );
          }
        } catch (Exception $e) {
          header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
          error_log($e);

          $order = wc_get_order($_GET['id']);
          if ($order) {
            $order->add_order_note(__('Jimizz Gateway notification error - Check logs.', JIMIZZ_DOMAIN_TEXT));
          }
        }
      }
    }
  }
})();
