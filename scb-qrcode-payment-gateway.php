<?php

/**
 * SCB QRCODE Payment Gateway
 *
 * @package       SCBQRCODEP
 * @author        Natdanai
 * @license       gplv2
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   SCB QRCODE Payment Gateway
 * Plugin URI:    https://rubdev.xyz
 * Description:   SCB QRCODE Payment Gateway
 * Version:       1.0.0
 * Author:        Natdanai
 * Author URI:    https://rubdev.xyz
 * Text Domain:   scb-qrcode-payment-gateway
 * Domain Path:   /languages
 * License:       GPLv2
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with SCB QRCODE Payment Gateway. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

define('SCB_PAYMENT_NAME', 'scb_qrcode_payment_gateway');

require_once "scb-api.php";

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'scb_qrcode_payment_gateway');
function scb_qrcode_payment_gateway($gateways)
{
	$gateways[] = 'WC_SCB_QRCODE_Payment_Gateway';
	return $gateways;
}

add_action('woocommerce_order_details_after_order_table', 'preview_scb_qrcode_order_details');
function preview_scb_qrcode_order_details($order)
{
	$is_scb_payment = $order->get_payment_method() == SCB_PAYMENT_NAME;
	if ($is_scb_payment && $order->is_paid() === false) {
		$qr_code = $order->get_meta('_scb_qrcode_image', true) ?? '';
?>
		<h2>QR Code</h2>
		<table class="woocommerce-table shop_table gift_info">
			<tbody>
				<tr>
					<th>Scan QRCode to pay this order</th>
				</tr>
				<tr>
					<td>
						<img style="width: 250px;margin: 0 auto;display: block;" src="data:image/png;base64,<?php echo esc_attr($qr_code); ?>" alt="QRCODE" />
					</td>
				</tr>
			</tbody>
		</table>
<?php
	}
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'misha_scb_qrcode_payment_gateway_class');
function misha_scb_qrcode_payment_gateway_class()
{

	class WC_SCB_QRCODE_Payment_Gateway extends WC_Payment_Gateway
	{

		/**
		 * Class constructor, more about it in Step 3
		 */
		public function __construct()
		{
			$this->id = SCB_PAYMENT_NAME; // payment gateway plugin ID
			$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'SCB QRCODE Payment Gateway';
			$this->method_description = 'SCB QRCODE Payment Gateway'; // will be displayed on the options page

			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);

			// Method with all the options fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->enabled = $this->get_option('enabled');
			$this->testmode = 'yes' === $this->get_option('testmode');

			$this->application_key = $this->testmode ? $this->get_option('test_application_key') : $this->get_option('live_application_key');
			$this->application_secret = $this->testmode ? $this->get_option('test_application_secret') : $this->get_option('live_application_secret');

			$this->request_u_id = $this->testmode ? $this->get_option('test_request_u_id') : $this->get_option('live_request_u_id');
			$this->resource_owner_id = $this->testmode ? $this->get_option('test_resource_owner_id') : $this->get_option('live_resource_owner_id');

			$this->reference3 = $this->testmode ? $this->get_option('test_reference3') : $this->get_option('live_reference3');
			$this->biller_id = $this->testmode ? $this->get_option('test_biller_id') : $this->get_option('live_biller_id');
			$this->pp_type = $this->get_option('pp_type');

			// This action hook saves the settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			// We need custom JavaScript to obtain a token
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

			// You can also register a webhook here
			add_action('woocommerce_api_callback-scb', array($this, 'webhook'));
		}

		/**
		 * Plugin options, we deal with it in Step 3 too
		 */
		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable ' . $this->get_method_title(),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'SCB Qrcode',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'webhook' => array(
					'title'       => 'Webhook URL Provide for SCB',
					'type'        => 'text',
					'disabled'	  => true,
					'default'	  => site_url('/wc-api/callback-scb/'),
					'description' => __('Copy this url to SCB Payment Confirmation Endpoint.'),
					'desc_tip'    => true,
				),


				'fee' => array(
					'title'       => 'Payment fee',
					'type'        => 'text',
				),
				'pp_type' => array(
					'title'       => 'Qr Type',
					'type'        => 'select',
					'options'     => [
						'PP' => 'QR 30',
						'QR 30' => 'QR CS',
						'PPCS' => 'QR 30 and QR CS'
					],
				),


				'test_application_key' => array(
					'title'       => 'Test Application Key',
					'type'        => 'text'
				),
				'test_application_secret' => array(
					'title'       => 'Test Application Secret',
					'type'        => 'password'
				),
				'test_request_u_id' => array(
					'title'       => 'Test RequestUId',
					'type'        => 'text'
				),
				'test_resource_owner_id' => array(
					'title'       => 'Test ResourceOwnerId',
					'type'        => 'text',
				),
				'test_reference3' => array(
					'title'       => 'Test Reference 3',
					'type'        => 'text',
				),
				'test_biller_id' => array(
					'title'       => 'Test Biller Id',
					'type'        => 'text',
				),


				'live_application_key' => array(
					'title'       => 'Live Application Key',
					'type'        => 'text'
				),
				'live_application_secret' => array(
					'title'       => 'Live Application Secret',
					'type'        => 'password'
				),
				'live_request_u_id' => array(
					'title'       => 'Live RequestUId',
					'type'        => 'text'
				),
				'live_resource_owner_id' => array(
					'title'       => 'Live ResourceOwnerId',
					'type'        => 'text'
				),
				'live_reference3' => array(
					'title'       => 'Live Reference3',
					'type'        => 'text',
				),
				'live_biller_id' => array(
					'title'       => 'Live Biller Id',
					'type'        => 'text',
				),
			);
		}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields()
		{
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
		public function payment_scripts()
		{
		}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields()
		{
			if (empty($this->request_u_id) || empty($this->resource_owner_id) || empty($this->application_key) || empty($this->application_secret)) {
				wc_add_notice("Can't use this payment method. Please contact Administrator (Wrong Setting.)", 'error');
				return false;
			}
			return true;
		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment($order_id)
		{
			global $woocommerce;

			$scb_api = new SCB_API($this->testmode, $this->request_u_id, $this->resource_owner_id);

			$access_token = $scb_api->gen_auth($this->application_key, $this->application_secret);
			$qr_code = [
				'status' => false,
				'data' => 'fail',
			];

			if ($access_token) {
				$order = wc_get_order($order_id);
				$qr_code = $scb_api->gen_qrcode($access_token, $this->pp_type, $this->biller_id, $this->reference3, $order);

				if ($qr_code['status'] && $qr_code['data']) {
					$order->add_meta_data('_scb_qrcode_image', $qr_code['data']);
					$order->save();

					// Empty cart
					$woocommerce->cart->empty_cart();

					// Redirect to the thank you page
					return [
						'result' => 'success',
						'redirect' => $this->get_return_url($order)
					];
				}
			}

			wc_add_notice("Can't generate qrcode [ " . $qr_code['data'] . " ] ", 'error');
			return false;
		}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook()
		{
			$body = file_get_contents('php://input');
			$request = json_decode($body, true);

			if (!empty($request)) {
				$order_id = intval(str_replace("ORDER", "", $request['billPaymentRef2']));
				if ($order_id) {
					$order = wc_get_order(intval($order_id));

					$order->add_order_note('Callback: ' . $body, true);
					$order->add_meta_data('_scb_callback_log_' . $request['transactionId'], $request);

					// we received the payment
					$order->payment_complete();
					$order->wc_reduce_stock_levels();

					$order->save();
				}
			}
		}
	}
}
