<?php

/**
 * Class Woocommerce_Barzahlen_Request
 *
 * API Create class for Barzahlen.de
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
class Woocommerce_Barzahlen_Create extends Woocommerce_Barzahlen_Request
{

	/**
	 * Sends a request to the API
	 *
	 * @param string $action
	 * @param array  $params
	 *
	 * @return array $response_arr
	 */
	public function request( $params = array() ){
		$defaults = array(
			'customer_email'     => '',
			'amount'             => 0,
			'currency'           => '',
			'language'           => '',
			'order_id'           => '',
			'customer_street_nr' => '',
			'customer_zipcode'   => '',
			'customer_city'      => '',
			'customer_country'   => '',
			'customer_var_0'     => '',
			'customer_var_1'     => '',
			'customer_var_2'     => '',
		);

		$params = wp_parse_args( $params, $defaults );

		return parent::send_request( 'create', $params );
	}
}