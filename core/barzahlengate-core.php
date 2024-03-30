<?php

/**
 * Barzahlen Gateway
 *
 * @package     WordPress
 * @subpackage  Woocommmerce
 * @author      Sven Wagener & MarketPress
 * @copyright   2021, Awesome UG
 * @link        http://awesome.ug
 * @license     http://www.opensource.org/licenses/gpl-2.0.php GPL License
 *
 * Copyright 2021 (very@awesome.ug)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// No direct access is allowed
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Barzahlen\Client;
use Barzahlen\Request\CreateRequest;
use Barzahlen\Webhook;
use Barzahlen\Exception\ApiException;

if ( ! class_exists( 'WooCommerce_Barzahlen' ) ) {
	/**
	 * Handles the actual gateway (Barzahlen)
	 *
	 * Adds admin options, frontend fields
	 * and handles payment processing
	 *
	 * @since   1.0.0
	 */

	/**
	 * Class WooCommerce_Barzahlende
	 */
	class WooCommerce_Barzahlen extends \WC_Payment_Gateway {
		/**
		 * Barzahlen Shop ID
		 *
		 * @var int
		 * @since   1.0.0
		 */
		private $shop_id;

		/**
		 * Barzahlen.de Payment Key
		 *
		 * @var string
		 * @since   1.0.0
		 */
		private $payment_key;

		/**
		 * Checkout token
		 *
		 * @var string
		 * @since 1.1.0
		 */
		private $checkout_token;

		/**
		 * Barzahlen.de Notification Key
		 *
		 * @var boolean
		 * @since   1.0.0
		 */
		private $sandbox = false;

		/**
		 * Callback URL
		 *
		 * @var string
		 * @since 1.0.2
		 */
		private $callback_url;

		/**
		 * Debugger switch
		 *
		 * @var string 'yes' or 'no'
		 * @since 1.0.2
		 */
		private $debug = 'no';

		/**
		 * WooCommerce Logger
		 *
		 * @var WC_Logger
		 * @since   1.0.2
		 */
		private $logger;

		/**
		 * Initialize the gateway
		 *
		 * @uses    apply_filters()
		 * @since   1.0.0
		 */
		public function __construct() {
			$this->id = 'barzahlen';

			/**
			 * Filters Barzahlen icon
			 *
			 * @since 1.1.0
			 *
			 * @param string the url to the barzahlen logo
			 */
			$this->icon         = apply_filters( 'woogate_barzahlendegateway_icon', BAZAHLENGATE_URLPATH . 'images/barzahlen-viacash-logo.png' );
			$this->has_fields   = false;
			$this->callback_url = WC()->api_request_url( strtolower( get_class( $this ) ) );

			$this->supports = array(
				'refunds'
			);

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->debug       = $this->settings['debug'];
			$this->title       = $this->settings['title'];
			$this->description = $this->settings['description'];

			$this->method_title       = __( 'Barzahlen', 'woocommerce-barzahlen' );
			$this->method_description = __( 'Integrates Barzahlen into your WooCommerce store.', 'woocommerce-barzahlen' );

			$this->shop_id          = $this->settings['shop_id'];
			$this->payment_key      = $this->settings['payment_key'];

			if ( 'yes' == $this->settings['sandbox'] ) {
				$this->sandbox = true;
			}

			// Payment listener/API hooks
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( &$this, 'payment_listener' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
			add_action( 'woocommerce_thankyou', array( $this, 'receipt_page' ) );

			// Checking if Cart is over 1.000 € - If yes, do not show Barzahlen
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateway_on_checkout' ) );
		}

		/**
		 * Add an options panel to the Woocommerce settings
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {
			?>
          <h3><?php _e( 'Barzahlen', 'woocommerce-barzahlen' ); ?></h3>
          <p><?php _e( 'Integrates Barzahlen into your WooCommerce store.', 'woocommerce-barzahlen' ); ?></p>
			<?php
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		/**
		 * Add all form fields
		 *
		 * @since 1.0.0
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-barzahlen' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Barzahlen', 'woocommerce-barzahlen' ),
					'default' => 'yes'
				),
				'title'       => array(
					'title'       => __( 'Title', 'woocommerce-barzahlen' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-barzahlen' ),
					'default'     => __( 'Barzahlen', 'woocommerce-barzahlen' )
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-barzahlen' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-barzahlen' ),
					'default'     => __( 'You can pay with Barzahlen after checkout.', 'woocommerce-barzahlen' )
				),
				'shop_id'     => array(
					'title'       => __( 'Division ID', 'woocommerce-barzahlen' ),
					'type'        => 'text',
					'description' => sprintf( __( 'Enter your <a href="%s" target="_blank">Division ID</a>.', 'woocommerce-barzahlen' ), 'https://controlcenter.barzahlen.de/#/settings/divisions' ),
					'default'     => ''
				),
				'payment_key' => array(
					'title'       => __( 'Payment Key', 'woocommerce-barzahlen' ),
					'type'        => 'text',
					'description' => sprintf( __( 'Enter your <a href="%s" target="_blank">Payment Key</a>.', 'woocommerce-barzahlen' ), 'https://controlcenter.barzahlen.de/#/settings/divisions' ),
					'default'     => ''
				),
				'sandbox'     => array(
					'title'   => __( 'Sandbox', 'woocommerce-barzahlen' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Sandbox test API of Barzahlen.', 'woocommerce-barzahlen' ),
					'default' => 'no'
				),
				'debug'       => array(
					'title'   => __( 'Debug', 'woocommerce-barzahlen' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable logging.', 'woocommerce-barzahlen' ),
					'default' => 'no'
				),
				'notice_url'      => array(
					'title'       => __( 'Notice URL', 'woocommerce-barzahlen' ),
					'type'        => 'text',
					'disabled'    => false,
					'description' => sprintf( __( 'Copy that url to your <a href="%s" target="_blank">barzahlen division settings</a>.', 'woocommerce-barzahlen' ), 'https://controlcenter.barzahlen.de/#/settings/divisions' ),
					'default'     => "$this->callback_url"
				)
			);
		}

		/**
		 * Check the response from Barzahlen
		 *
		 * @since   1.0.0
		 */
		public function payment_listener() {
			$header = $_SERVER;
			$body   = file_get_contents( 'php://input' );

			$webhook = new Webhook( $this->payment_key );

			$payment       = json_decode( $body );
			$slip_id       = $payment->slip->id;
			$reference_key = $payment->slip->reference_key;
			$order_id      = $payment->slip->metadata->order_id;

			$log_prefix = sprintf( 'Order ID #%s: ', $order_id );

			if ( $webhook->verify( $header, $body ) ) {
				$this->log( $log_prefix . sprintf( 'Verified webhook request successful for slip id %s and reference key %s.', $slip_id, $reference_key ) );
			} else {
				$this->log( $log_prefix . sprintf( 'Could not veryify request for slip id %s and reference key %s.', $slip_id, $reference_key ) );

				return;
			}

			if ( empty( $order_id ) ) {
				$this->log( sprintf( 'Missing order id in request.' ) );

				return;
			}

			$order = new \WC_Order( $order_id );

			switch ( $payment->event ) {
				case 'paid':

					$order->add_order_note( __( 'Payment via Barzahlen completed.', 'woocommerce-barzahlen' ) );
					$order->payment_complete();
                    $this->log( $log_prefix . 'Payment completed.' );
                    update_post_meta( $order_id, '_barzahlen_is_paid', true );

					http_response_code(200 );

					break;

				case 'expired':

					$order->add_order_note( __( 'Payment via Barzahlen expired.', 'woocommerce-barzahlen' ) );
					$order->update_status( 'failed', __( 'Payment via Barzahlen expired.', 'woocommerce-barzahlen' ) );

					$this->log( $log_prefix . 'Payment expired.' );

                    http_response_code(200 );

					break;

				case 'canceled':

					$order->add_order_note( __( 'Payment via Barzahlen canceled.', 'woocommerce-barzahlen' ) );
					$order->update_status( 'failed', __( 'Payment via Barzahlen canceled.', 'woocommerce-barzahlen' ) );

					$this->log( $log_prefix . 'Payment canceled.' );

                    http_response_code(200 );

					break;

				default:

					if ( empty( $payment->event ) ) {
						$order->add_order_note( __( 'Payment status not submitted by Barzahlen.', 'woocommerce-barzahlen' ) );
						$this->log( $log_prefix . sprintf( 'Unknown payment status: %s.', $payment->event ) );

						http_response_code(406 );
					} else {
						$order->add_order_note( sprintf( __( 'Unknown payment status: %s.', 'woocommerce-barzahlen' ), $payment->event ) );
						$this->log( $log_prefix . sprintf( 'Unknown payment status: %s.', $payment->event ) );

						http_response_code(404 );
					}
					break;
			}
		}

		/**
		 * Even though we don't really have any fields
		 * we still need to output something, otherwise
		 * we get a fatal error
		 *
		 * @uses    wpautop()
		 * @uses    wptexturize()
		 *
		 * @since   1.0.0
		 */
		public function payment_fields() {
			if ( ! empty( $this->description ) ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}

		/**
		 * Process the payment
		 *
		 * Does not really do anything, except redirect the user.
		 * Order processing is done in self::payment_listener()
		 *
		 * @param $order_id int Internal WP post ID
		 *
		 * @return array
		 * @since   1.0.0
		 */
		public function process_payment( $order_id ) {
			$order = new \WC_Order( $order_id );

			if ( $this->send_request( $order_id ) ) {
				wc_reduce_stock_levels( $order_id );
                WC()->cart->empty_cart();

                /**
                 * Filters payment order status
                 *
                 * @since 1.3.0
                 *
                 * @param \DateTime|string .
                 * @param \WC_Order WooCommerce order object.
                 */
				$payment_order_status = apply_filters( 'woogate_barzahlendegateway_process_payment_order_status', 'on-hold', $order );
                
                $order->update_status( $payment_order_status, sprintf( __( 'Awaiting Barzahlen payment.', 'woocommerce-barzahlen' ), $order->get_id() ) );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}

			wc_add_notice( __( 'Barzahlen returned an Error. Please choose annother payment method.', 'woocommerce-barzahlen' ), 'error' );

			return array(
				'result'   => 'error',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}

		/**
		 * Ending Page with payment information for customer
		 *
		 * @param $order_id
		 *
		 * @since   1.0.0
		 */
		public function receipt_page( $order_id ) {
            $this->checkout_token = get_post_meta( $order_id, '_barzahlen_checkout_token', true );

            if ( empty ( $this->checkout_token ) ) {
                return;
            }

            $order = new \WC_Order( $order_id );       
            $is_paid = get_post_meta( $order_id, '_barzahlen_is_paid', true );

            if ( $is_paid ) {
                return;
            }

			add_action( 'wp_footer', array( $this, 'checkout_scripts' ) );

			echo '<button class="bz-checkout-btn button">' . __( 'Pay with Barzahlen', 'woocommerce-barzahlen' ) . '</button>';
			echo ' <a href="' . $order->get_checkout_order_received_url()  . '" class="bz-checkout-btn button">' . __( 'Show order summary', 'woocommerce-barzahlen' ) . '</a>';
		}

		/**
		 * Printing footer checkout scripts
		 *
		 * @since   1.1.0
		 */
		public function checkout_scripts() {
			$script_url = 'https://cdn.barzahlen.de/js/v2/checkout.js';

			if ( $this->sandbox ) {
				$script_url = 'https://cdn.barzahlen.de/js/v2/checkout-sandbox.js';
			}

			echo '<script src="' . $script_url . '" class="bz-checkout" data-token="' . $this->checkout_token . '">';
		}

		/**
		 * Add an options panel to the Woocommerce settings
		 *
		 * @param   $order_id   int     Internal WP post ID
		 *
		 * @return bool
		 * @since   1.0.0
		 */
		public function send_request( $order_id ) {
			$order      = new \WC_Order( $order_id );
			$log_prefix = sprintf( 'Order ID #%s: ', $order_id );

			// Only Creating Transaction if not done before
			if ( '' === get_post_meta( $order_id, '_barzahlen_transaction_id', true ) ) {
				$amount              = (string) number_format( $order->get_total(), 2 );
				$order_reference_key = sprintf( __( 'Order-%s-%s' ), $order->get_id(), time() );

                /**
                 * Filters expiration
                 *
                 * @since 1.2.0
                 *
                 * @param \DateTime|string Expiring date (https://www.php.net/manual/de/class.datetime.php).
                 * @param \WC_Order WooCommerce order object.
                 */
				$expires_at = apply_filters( 'woogate_barzahlendegateway_expires_at', false, $order );

				$request = new CreateRequest();
				$request->setSlipType( 'payment' );
				$request->setTransaction( $amount, get_woocommerce_currency() );
				$request->setReferenceKey( $order_reference_key );
				$request->setHookUrl( $this->callback_url );
				$request->setCustomerKey( $order->get_billing_email() );
				$request->setCustomerEmail( $order->get_billing_email() );
				$request->setCustomerLanguage( 'de-DE' );

				$request->setAddress( array(
					'street_and_no' => $order->get_billing_address_1(),
					'zipcode'       => $order->get_billing_postcode(),
					'city'          => $order->get_billing_city(),
					'country'       => $order->get_billing_country()
				) );

				if( ! empty( $expires_at ) ) {
				    $request->setExpiresAt( $expires_at );
                }

				$request->addMetadata( 'order_id', (string) $order_id );

				try {
					$client   = new Client( $this->shop_id, $this->payment_key, $this->sandbox );
					$response = $client->handle( $request );

					$response_data = json_decode( $response );
				} catch ( ApiException $e ) {
					$this->log( $log_prefix . sprintf( 'Request Error: %s', $e->getMessage() ) );

					return false;
                }
                
                try{
			        $order->set_transaction_id( $response_data->transactions[0]->id );
                } catch ( WC_Data_Exception $e ) {
					$this->log( 'Could not set transaction ID for order #' . $order->get_id() . '. Error: ' . $e->getMessage() );
                }

                $order->save();
                
                $this->payment_initialized( $order );

				update_post_meta( $order_id, '_barzahlen_slip_id', $response_data->id );
				update_post_meta( $order_id, '_barzahlen_transaction_id', $response_data->transactions[0]->id );
                update_post_meta( $order_id, '_barzahlen_checkout_token', $response_data->checkout_token );
                update_post_meta( $order_id, '_barzahlen_is_paid', false );

				// $this->log( $log_prefix . sprintf( 'Response: %s', print_r( $response_data, true ) ) );
				$this->log( $log_prefix . sprintf( 'Successful Request: Order got response id %s with transaction id %s and order reference key %s.', $response_data->id, $response_data->transactions[0]->id, $order_reference_key ) );
			}

			return true;
		}

		/**
		 * Processing Refund.
		 *
		 * @param int $order_id Order ID.
		 * @param null $amount Amount to refund.
		 * @param string $reason Reason of refunding.
		 *
		 * @return bool|WP_Error
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$slip_id    = get_post_meta( $order_id, '_barzahlen_slip_id', true );
			$log_prefix = sprintf( 'Order ID #%s: ', $order_id );

			if ( empty( $slip_id ) ) {
				return false;
			}

			$request_amount = number_format( $amount * - 1, 2 ); // Bazahlen needs a negative value
			$request        = new CreateRequest();
			$request->setSlipType( 'refund' );
			$request->setForSlipId( $slip_id );
			$request->setTransaction( (string) $request_amount, get_woocommerce_currency() );

			try {
				$client        = new Client( $this->shop_id, $this->payment_key, $this->sandbox );
				$response      = $client->handle( $request );
				$response_data = json_decode( $response );
			} catch ( ApiException $e ) {
				$this->log( $log_prefix . sprintf( 'Refunding failed: %s', $e->getMessage() ) );

				return false;
			}

			$order = new \WC_Order( $order_id );

			$this->log( $log_prefix . sprintf( 'Refunding of %s was successful for slip id %s.', $amount, $slip_id ) );

			if ( empty( $reason ) ) {
				$order->add_order_note( sprintf( __( 'Refunded %s to transaction %s.', 'woocommerce-barzahlen' ), $amount, $order->get_transaction_id() ) );
			} else {
				$order->add_order_note( sprintf( __( 'Refunded %s to transaction %s with the reason: %s.', 'woocommerce-barzahlen' ), $amount, $order->get_transaction_id(), $reason ) );
			}

			return true;
        }
        
        /**
		 * Sets the payment to pending and adds further actions
		 *
		 * @since 1.3.0
		 *
		 * @param WC_Order $order WooCommerce Order id
		 */
		private function payment_initialized( &$order ) {
			$order->add_order_note( sprintf( __( 'Payment process with Barzahlen was initialized (Transaction ID: %s).', 'woocommerce-barzahlen' ), $order->get_transaction_id() ) );

			/**
			 * Action after payment is pending
			 *
			 * @since 1.3.0
			 *
			 * @param int $order_id WooCommerce Order id
			 */
			do_action( 'woogate_barzahlendegateway_payment_initialized', $order->get_id() );
		}

		/**
		 * Do not show Barzahlen if Cart is over 1.000 €
		 *
		 * @param $available_gateways
		 *
		 * @return mixed
		 * @since   1.0.0
		 */
		public function filter_gateway_on_checkout( $available_gateways ) {
			if ( is_checkout() ) {
				if ( WC()->cart->total > 1000 ) {
					unset( $available_gateways['barzahlen'] );
				}
			}

			return $available_gateways;
		}

		/**
		 * Logging Wrapper
		 *
		 * @param string $message
		 *
		 * @return bool
		 *
		 * @since 1.0.2
		 */
		private function log( $message ) {
			if ( $this->debug === 'no' ) {
				return false;
			}

			if ( empty( $this->logger ) ) {
				$this->logger = new \WC_Logger();
			}

			$this->logger->add( 'barzahlende', $message );

			return true;
		}
	}
}

/**
 * Add to the gateways array
 *
 * Can't be part of the class as this function basically
 * registers the gateway
 *
 * @param array $methods Holds all registered gateway options
 *
 * @return array $methods Filtered gateway options
 * @since   1.0.0
 */
function barzahlende_add_payment_gateway( $methods ) {
	$methods[] = 'WooCommerce_Barzahlen';

	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'barzahlende_add_payment_gateway' );