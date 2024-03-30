<?php

/**
 * Class Woocommerce_Barzahlen_Request
 *
 * API Base class for Barzahlen.de
 *
 * @author  awesome.ug <very@awesome.ug>, Sven Wagener <sven@awesome.ug>
 * @package WooCommerceBarzahlen/API
 * @version 1.0.0
 * @since   1.0.0
 * @license GPL 2
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
class Woocommerce_Barzahlen_Request {
	/**
	 * Barzahlen Shop ID
	 * @var
	 */
	private $shop_id;

	/**
	 * Barzahlen Payment Key
	 * @var
	 */
	private $payment_key;

	/**
	 * Barzahlen Notification Key
	 * @var
	 */
	private $notification_key;

	/**
	 * Barzahlen API URL
	 * @var
	 */
	private $api_url;


	public function __construct( $shop_id, $payment_key, $notification_key, $sandbox = false ) {
		$this->shop_id          = $shop_id;
		$this->payment_key      = $payment_key;
		$this->notification_key = $notification_key;

		$this->api_url = 'https://api.barzahlen.de/v1/transactions/';

		if ( true == $sandbox ) {
			$this->api_url = 'https://api-sandbox.barzahlen.de/v1/transactions/';
		}
	}

	/**
	 * set the cURL url: combining url-endpoint + action
	 *
	 * @param $action
	 *
	 * @return string
	 */
	private function get_endpoint( $action ) {
		$url = $this->api_url . $action;

		return $url;
	}

	/**
	 * Sends a request to the API
	 *
	 * @param string $action
	 * @param array $params
	 * @param string $method
	 *
	 * @return array $response_arr
	 */
	public function send_request( $action = '', $params = array() ) {
		$url = $this->get_endpoint( $action );

		$headers = array();

		$params = array_merge( array( 'shop_id' => $this->shop_id ), $params );
		$params = array_merge( $params, array( 'payment_key' => $this->payment_key ) );

		$hash_key = $this->get_hash( $params );
		$params   = array_merge( $params, array( 'hash' => $hash_key ) );

		$method = 'POST';

		switch ( $method ) {
			case "GET":

				$args     = array(
					'headers' => $headers
				);
				$response = wp_remote_get( $url, $args );

				break;

			case "POST":

				$args = array(
					'headers' => $headers,
					'body'    => $params
				);

				$response = wp_remote_post( $url, $args );

				break;

			case "PUT":

				$args     = array(
					'headers' => $headers,
					'method'  => 'PUT',
					'body'    => $params
				);
				$response = wp_remote_request( $url, $args );

				break;
		}

		if ( is_wp_error( $response ) ) {
			$response_arr = array(
				'status'   => 0,
				'response' => $response->get_error_message()
			);

			return $response_arr;
		}


		if ( 0 != (int) $response['result '] ) {
			$parsed_response = $this->parse_xml( wp_remote_retrieve_body( $response ) );

			$response_arr = array(
				'status'   => 500,
				'response' => $parsed_response['error-message']
			);

			return $response_arr;
		}

		$body   = wp_remote_retrieve_body( $response );
		$status = wp_remote_retrieve_response_code( $response );

		try {
			$response = $this->parse_xml( $body );
		} catch ( Exception $e ) {
			$response = $e->getMessage();
		}

		$response_arr = array(
			'status'   => $status,
			'response' => $response
		);

		return $response_arr;
	}

	/**
	 * Getting Hash
	 *
	 * @param $params
	 *
	 * @return string
	 */
	private function get_hash( $params ) {
		return hash( 'sha512', implode( ';', $params ) );
	}

	/**
	 * Parsing XML
	 *
	 * @param $xml
	 *
	 * @return bool|SimpleXMLElement
	 * @throws Exception
	 */
	public function parse_xml( $xml ) {
		try {
			$xml = new SimpleXMLElement( $xml );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		return (array) $xml;
	}
}