<?php

/**
 * Plugin Name: Barzahlen for WooCommerce
 * Plugin URI:  https://marketpress.de/Product/woocommerce-barzahlen
 * Description: Integrates Barzahlen into your WooCommerce store.
 * Author:      Awesome UG & MarketPress
 * Version:     1.3.0
 * Author URI:  http://awesome.ug
 * Text Domain: woocommerce-barzahlen
 * Domain Path: /languages/
 * WC requires at least: 2.0.0
 * WC tested up to: 4.9.2
 *
 * This script is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// No direct access is allowed
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php'; 

if ( ! class_exists( 'WooCommerce_BarzahlenGateway' ) ) {
	/**
	 * Main class
	 *
	 * @since   1.0.0
	 */
	class WooCommerce_BarzahlenGateway {
		/**
		 * The plugin version
		 */
		const VERSION = '1.2.0';

		/**
		 * Minimum required WP version
		 */
		const MIN_WP = '4.0.0';

		/**
		 * Minimum required Woocommerce version
		 */
		const MIN_WOO = '2.0.0';

		/**
		 * Minimum required PHP version
		 */
		const MIN_PHP = '5.6.38';

		/**
		 * Name of the plugin folder
		 */
		static private $plugin_name;

		/**
		 * Can the plugin be executed
		 */
		static private $active = false;

		/**
		 * Supported currencies
		 */
		public static $supported_currencies;

		/**
		 * PHP5 constructor
		 *
		 * @since   1.0.0
		 * @access  public
		 * @uses    plugin_basename()
		 * @uses    register_activation_hook()
		 * @uses    register_uninstall_hook()
		 * @uses    add_action()
		 */
		public function __construct() {
			self::$plugin_name          = plugin_basename( __FILE__ );
			self::$supported_currencies = 'EUR';

			add_action( 'plugins_loaded', array( &$this, 'constants' ), 0 );
			add_action( 'plugins_loaded', array( &$this, 'translate' ), 0 );
			add_action( 'plugins_loaded', array( &$this, 'check_requirements' ), 0 );
			add_action( 'plugins_loaded', array( &$this, 'load' ), 1 );

			add_filter( 'plugin_row_meta', array( &$this, 'add_links' ), 10, 2 );

			if ( is_admin() ) {
				// require Auto Updater

				if ( ! class_exists( 'MarketPress_Auto_Update' ) ) {
					require_once untrailingslashit( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'marketpress-autoupdater' . DIRECTORY_SEPARATOR . 'class-MarketPress_Auto_Update.php';
				}

				$plugindata_import             = get_file_data(
					__FILE__,
					array(
						'plugin_uri'  => 'Plugin URI',
						'plugin_name' => 'Plugin Name',
						'version'     => 'Version'
					)
				);
				$plugin_data                   = new \stdClass();
				$plugin_data->plugin_slug      = 'woocommerce-barzahlen';
				$plugin_data->shortcode        = 'wcbarzahlen';
				$plugin_data->plugin_name      = $plugindata_import['plugin_name'];
				$plugin_data->plugin_base_name = plugin_basename( __FILE__ );
				$plugin_data->plugin_url       = $plugindata_import['plugin_uri'];
				$plugin_data->version          = $plugindata_import['version'];
				$autoupdate                    = new \MarketPress_Auto_Update();
				$autoupdate->setup( $plugin_data );
			}
		}

		/**
		 * Load the core files
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function load() {
			if ( self::$active === false ) {
				return false;
			}

			// core files
			require( BAZAHLENGATE_ABSPATH . 'includes/barzahlen/barzahlen.php' );
			require( BAZAHLENGATE_ABSPATH . 'includes/barzahlen/create.php' );
			require( BAZAHLENGATE_ABSPATH . 'core/barzahlengate-core.php' );
		}

		/**
		 * Declare all constants
		 *
		 * @since   1.0.0
		 * @access  public
		 * @uses    plugin_basename()
		 * @uses    trailingslashit()
		 * @uses    plugins_url()
		 */
		public function constants() {
			define( 'BAZAHLENGATE_PLUGIN', self::$plugin_name );
			define( 'BAZAHLENGATE_VERSION', self::VERSION );
			define( 'BAZAHLENGATE_ABSPATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
			define( 'BAZAHLENGATE_URLPATH', trailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		}

		/**
		 * Load the languages
		 *
		 * @since   1.0.0
		 * @access  public
		 * @uses    load_plugin_textdomain()
		 */
		public function translate() {
			load_plugin_textdomain( 'woocommerce-barzahlen', false, dirname( self::$plugin_name ) . '/languages/' );
		}

		/**
		 * Check for required versions
		 *
		 * Checks for WP, PHP and Woocommerce versions
		 *
		 * @since   1.0.0
		 * @access  private
		 * @global  string $wp_version Current WordPress version
		 */
		public function check_requirements() {
			global $wp_version;

			$error = false;

			// Woocommerce checks
			if ( ! defined( 'WOOCOMMERCE_VERSION' ) ) {
				add_action( 'admin_notices', function () {
					printf( WooCommerce_BarzahlenGateway::messages( "no_woo" ), admin_url( "plugin-install.php" ) );
				} );
				$error = true;
			} elseif ( version_compare( WOOCOMMERCE_VERSION, self::MIN_WOO, '>=' ) == false ) {
				add_action( 'admin_notices', function () {
					printf( WooCommerce_BarzahlenGateway::messages( "min_woo" ), WooCommerce_BarzahlenGateway::MIN_WOO, admin_url( "update-core.php" ) );
				} );
				$error = true;
			}

			// WordPress check
			if ( version_compare( $wp_version, self::MIN_WP, '>=' ) == false ) {
				add_action( 'admin_notices', function () {
					printf( WooCommerce_BarzahlenGateway::messages( "min_wp" ), WooCommerce_BarzahlenGateway::MIN_WP, admin_url( "update-core.php" ) );
				} );
				$error = true;
			}

			// PHP check
			if ( version_compare( PHP_VERSION, self::MIN_PHP, '>=' ) == false ) {
				add_action( 'admin_notices', function () {
					printf( WooCommerce_BarzahlenGateway::messages( "min_php" ), WooCommerce_BarzahlenGateway::MIN_PHP );
				} );
				$error = true;
			}

			// Currency check
			if ( function_exists( 'get_woocommerce_currency' ) && self::check_currencies() !== true ) {
				add_action( 'admin_notices', function () {
					printf( WooCommerce_BarzahlenGateway::messages( "cur_fail" ), implode( ", ", WooCommerce_BarzahlenGateway::check_currencies() ) );
				} );
			}

			self::$active = ( ! $error ) ? true : false;
		}

		public static function check_currencies() {

			// Currencies Check
			$currencies = apply_filters( 'wc_aelia_cs_enabled_currencies', array( get_woocommerce_currency() ) );

			$page = '';
			if ( array_key_exists( 'page', $_GET ) ) {
				$page = $_GET['page'];
			}

			$tab = '';
			if ( array_key_exists( 'tab', $_GET ) ) {
				$tab = $_GET['tab'];
			}

			$section = '';
			if ( array_key_exists( 'section', $_GET ) ) {
				$section = $_GET['section'];
			}

			// If page should noz show a message, interrupt the check and gibe back true
			if ( ( $page != 'wc-settings' || $tab != 'checkout' || $section != 'woocommerce_barzahlen' ) && $page != 'aelia_cs_options_page' ) {
				return true;
			}

			$supported_currencies = explode( ',', self::$supported_currencies );
			$failed_currencies    = array();

			if ( is_array( $currencies ) ) {
				foreach ( $currencies AS $currency ) {
					if ( ! in_array( $currency, $supported_currencies ) ) {
						$failed_currencies[] = $currency;
					}
				}
			}

			if ( count( $failed_currencies ) === 0 ) {
				return true;
			} else {
				return $failed_currencies;
			}
		}

		/**
		 * Hold all error messages
		 *
		 * @since   1.0.0
		 * @access  public
		 *
		 * @param   $key    string  Error/success key
		 * @param   $type   string  Either 'error' or 'updated'
		 *
		 * @return  string  Error/success message
		 */
		public static function messages( $key = 'undefined', $type = 'error' ) {
			$messages = array(
				'no_woo'   => __( 'WooCommerce Barzahlen Gateway requires WooCommerce to be installed. <a href="%s">Download it now</a>!', 'woocommerce-barzahlen' ),
				'min_woo'  => __( 'WooCommerce Barzahlen Gateway requires WooCommerce %s or higher. <a href="%s">Upgrade now</a>!', 'woocommerce-barzahlen' ),
				'min_wp'   => __( 'WooCommerce Barzahlen Gateway requires WordPress %s or higher. <a href="%s">Upgrade now</a>!', 'woocommerce-barzahlen' ),
				'min_php'  => __( 'WooCommerce Barzahlen Gateway requires PHP %s or higher. Please ask your hosting company for support.', 'woocommerce-barzahlen' ),
				'cur_fail' => __( 'WooCommerce Barzahlen Gateway does not support the currency/currencies: %s.', 'woocommerce-barzahlen' ),
			);

			return '<div id="message" class="' . $type . '"><p>' . $messages[ $key ] . '</p></div>';
		}

		/**
		 * Add links to the plugin screen
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function add_links( $links, $file ) {
			if ( $file == self::$plugin_name ) {
				$links[] = '<a href="' . admin_url( '/admin.php?page=wc-settings&tab=checkout&section=woocommerce_barzahlen' ) . '">' . __( 'Options', 'woocommerce-barzahlen' ) . '</a>';
			}

			return $links;
		}
	}

	new WooCommerce_BarzahlenGateway();
}