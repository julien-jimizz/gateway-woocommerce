<?php
/**
 * Plugin Name: WooCommerce Jimizz Gateway
 * Plugin URI: https://www.jimizz.com/
 * Description: Jimizz Payment Gateway Woocommerce Integration
 * Author: Jimizz Team
 * Author URI: https://www.jimizz.com/
 * Version: 1.0.4
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
  define('WC_JIMIZZ_GATEWAY_VERSION', '1.0.4');
  define('WC_JIMIZZ_GATEWAY_DOMAIN_TEXT', 'woocommerce-jimizz');

  $autoload_filepath = __DIR__ . '/vendor/autoload.php';
  if (file_exists($autoload_filepath)) {
    require $autoload_filepath;
  }

  register_activation_hook(__FILE__, 'jimizz_gateway_installation');
  function jimizz_gateway_installation()
  {
    global $wpdb;
    $installed_version = get_option('WC_JIMIZZ_GATEWAY_VERSION');
    if ($installed_version !== WC_JIMIZZ_GATEWAY_VERSION) {
      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      $sql = <<<EOF
        CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wc_jimizz_transaction`
        (
          `id_jimizz_transaction` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_order` INT(11) UNSIGNED NOT NULL,
          `amount` FLOAT(12, 2) NOT NULL,
          `mode` ENUM ('production', 'testApproved', 'testRejected') NOT NULL,
          `status` ENUM ('pending', 'cancelled', 'failed', 'succeed') NOT NULL,
          `tx_hash` VARCHAR(255) NOT NULL,
          PRIMARY KEY (`id_jimizz_transaction`) USING BTREE,
          INDEX `id_order` (`id_order`) USING BTREE
        );
EOF;
      dbDelta($sql);
      update_option('WC_JIMIZZ_GATEWAY_VERSION', WC_JIMIZZ_GATEWAY_VERSION);
    }
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
      WC_JIMIZZ_GATEWAY_DOMAIN_TEXT,
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
        add_action('woocommerce_api_' . $this->id . '_return', [$this, 'return']);
      }

      /**
       * Gateway admin settings
       * @return void
       */
      public function init_form_fields()
      {
        $this->form_fields = apply_filters('wc_jimizz_form_fields', [
          'enabled' => [
            'title' => __('Enable/Disable', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'label' => __('Enable Jimizz Gateway', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'type' => 'checkbox',
            'default' => 'yes'
          ],

          'title' => [
            'title' => __('Title', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'description' => __('This controls the title which the user sees during checkout.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'type' => 'text',
            'default' => __('Jimizz', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
          ],

          'description' => [
            'title' => __('Description', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'type' => 'textarea',
            'description' => __('Payment method description that the customer will see on your checkout.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'default' => __('Pay with Jimizz Cryptocurrency', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
          ],

          'mode' => [
            'title' => __('Mode', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'description' => __('Place the Jimizz gateway in test mode using test private key.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'type' => 'select',
            'options' => [
              TransactionType::PRODUCTION => __('Production', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
              TransactionType::TEST_APPROVED => __('Test APPROVED', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
              TransactionType::TEST_REJECTED => __('Test REJECTED', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            ],
            'default' => 'no',
          ],

          'merchant_id' => [
            'title' => __('Merchant ID', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'type' => 'text',
          ],

          'test_private_key' => [
            'title' => __('Test Private Key', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
            'type' => 'password',
          ],

          'private_key' => [
            'title' => __('Production Private Key', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT),
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
        global $wpdb;

        $order = wc_get_order($order_id);
        $order->add_order_note(__('Order placed and user redirected to Jimizz Gateway.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT));

        // Create WooCommerce JimizzTransaction
        $wpdb->insert($wpdb->prefix . 'wc_jimizz_transaction', [
          'id_order' => $order->get_id(),
          'amount' => $order->get_total(),
          'status' => 'pending',
          'mode' => $this->mode
        ]);
        $transactionId = $wpdb->insert_id;

        $return = function ($action) use ($transactionId) {
          return add_query_arg([
            'wc-api' => 'jimizz_return',
            'tx' => $transactionId,
            'action' => $action
          ], home_url('/'));
        };

				$prefix = $this->mode === TransactionType::PRODUCTION ? 'WC_' : 'WC_TEST_';
        $fields = [
          'transactionId' => $prefix . $transactionId,
          'amount' => $order->get_total() * 100,
          'currency' => Currency::EUR,
          'successUrl' => $return('success'),
          'errorUrl' => $return('error'),
          'cancelUrl' => $return('cancel'),
          'callbackUrl' => add_query_arg(['wc-api' => 'jimizz_s2s'], home_url('/')),
          'merchantName' => get_bloginfo('name'),
          'merchantUrl' => preg_replace('~^https?://~i', '', get_bloginfo('url')),
        ];

        // Init Gateway transaction
        $gateway = new Gateway($this->merchant_id);
        $transaction = $gateway->transaction($this->mode, $fields);
        $transaction->sign($this->private_key);

        // Display redirecting message and render form
        echo '<p>' . __('Redirecting to payment provider.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT) . '</p>';
        $form_id = 'jimizz-gateway-form';
        $transaction->render($form_id);

        // Submit form
        wc_enqueue_js('document.getElementById("' . $form_id . '").submit();');
      }

      /**
       * Return from Jimizz Gateway
       * @return void
       */
      public function return()
      {
        global $wpdb;

        $action = filter_input(INPUT_GET, 'action', FILTER_UNSAFE_RAW);
        $tx = filter_input(INPUT_GET, 'tx', FILTER_UNSAFE_RAW);

        // Retrieve transaction
        $sql = 'SELECT * FROM ' . $wpdb->prefix . 'wc_jimizz_transaction where id_jimizz_transaction = %d';
        $stmt = $wpdb->prepare($sql, $tx);
        $jimizzTx = $wpdb->get_row($stmt);

        if ($jimizzTx) {
          if ($jimizzTx->status === 'pending') {
            // Update status
            $status = $action === 'cancel' ? 'cancelled' : ($action === 'success' ? 'succeed' : 'failed');
            $wpdb->update(
              $wpdb->prefix . 'wc_jimizz_transaction',
              ['status' => $status],
              ['id_jimizz_transaction' => $tx],
              ['%s'],
              ['%d'],
            );
          }

          // Retrieve order
          $order = wc_get_order($jimizzTx->id_order);

          if ($action === 'success') {
            wp_redirect($this->get_return_url($order));
          } else {
            wp_redirect($order->get_checkout_payment_url());
          }
        } else {
          wp_redirect('/');
        }
      }

      /**
       * Server-to-server callback notification
       * @return void
       */
      public function s2s()
      {
	      global $wpdb;

	      $data = json_decode(file_get_contents('php://input'));

	      try {
		      $gateway = new Gateway($this->merchant_id);
		      if (!$gateway->verifyCallback($data)) {
			      $this->exit500();
		      } else {
			      // Retrieve transaction
			      $sql = 'SELECT * FROM ' . $wpdb->prefix . 'wc_jimizz_transaction where id_jimizz_transaction = %d';
			      $stmt = $wpdb->prepare($sql, preg_replace('~WC_(?:DEV_|TEST_)?~', '', $data->transactionId));
			      $jimizzTx = $wpdb->get_row($stmt);

			      // Retrieve order
			      $order = wc_get_order($jimizzTx->id_order);

			      if ($data->status === 'APPROVED') {
				      // Payment succeed - validate order
				      $order->add_order_note(__('Jimizz Gateway accepted payment.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT));
				      $order->add_order_note(sprintf(__('Transaction Hash: %s.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT), $data->hash));
				      $order->payment_complete($data->hash ?? '');
				      update_option('webhook_debug', $data);

				      // Update status
				      $wpdb->update(
					      $wpdb->prefix . 'wc_jimizz_transaction',
					      ['status' => 'succeed'],
					      ['id_jimizz_transaction' => $jimizzTx->id_jimizz_transaction],
					      ['%s'],
					      ['%d'],
				      );
			      } else {
				      // Payment failed - Update status
				      $wpdb->update(
					      $wpdb->prefix . 'wc_jimizz_transaction',
					      ['status' => 'failed'],
					      ['id_jimizz_transaction' => $jimizzTx->id_jimizz_transaction],
					      ['%s'],
					      ['%d'],
				      );
			      }
		      }

		      echo $this->get_return_url($order);
	      } catch (Exception $e) {
		      error_log($e);

		      $order = wc_get_order($_GET['id']);
		      if ($order) {
			      $order->add_order_note(__('Jimizz Gateway notification error - Check logs.', WC_JIMIZZ_GATEWAY_DOMAIN_TEXT));
		      }

		      $this->exit500();
	      }
      }

	    private function exit500() {
		    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		    exit;
	    }
    }
  }
})();
