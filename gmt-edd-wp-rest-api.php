<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 1.2.1
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: GPLv3
 */

	function gmt_edd_get_user_purchases($data) {

		// if no email, throw an error
		if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return new WP_Error( 'code', __( 'Not a valid email address', 'edd_for_courses' ) );
		}

		// Get user purchases
		$purchases = edd_get_users_purchases(sanitize_email($data['email']));

		// Set up list of purchases
		$purchase_list = array();
		$invoice_list = array();
		$subscriptions_list = array();

		foreach ($purchases as $purchase) {
			$payment = new EDD_Payment( $purchase->ID );
			$purchased_files = $payment->cart_details;

			if ( is_array( $purchased_files ) ) {

				$products = array();

				foreach ( $purchased_files as $download ) {

					// Get product IDs
					if ( edd_is_bundled_product( $download['id'] ) ) {
						$price_id = isset( $download['item_number']['options']['price_id'] ) ? $download['item_number']['options']['price_id'] : null;
						foreach ( edd_get_bundled_products( $download['id'], $price_id ) as $bundle ) {
							$purchase_list[] = $bundle;
						}
					} else {
						$variable_prices = edd_has_variable_prices( $download['id'] );
						if ( $variable_prices && isset( $download['item_number']['options']['price_id'] ) ) {
							$purchase_list[] = $download['id'] . '_' . $download['item_number']['options']['price_id'];
						} else {
							$purchase_list[] = $download['id'];
						}
					}

					// Get products
					$products[] = array(
						'name' => $download['name'],
						'price' => $download['item_price'],
						'discount' => $download['discount'],
						'total' => $download['price']
					);

				}

				$invoice_list[] = array(
					'id' => $purchase->ID,
					'date' => get_the_date('F j, Y', $purchase->ID),
					'total' => $payment->total,
					'products' => $products
				);

			}
		}

		// Return success
		return new WP_REST_Response(array(
			'purchases' => array_unique($purchase_list),
			'invoices' => $invoice_list,
			'subscriptions' => array_unique($subscriptions_list),
		), 200);

	}


	function gmt_edd_for_courses_register_routes () {

		register_rest_route('gmt-edd/v1', '/users/(?P<email>\S+)', array(
			'methods' => 'GET',
			'callback' => 'gmt_edd_get_user_purchases',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
			'args' => array(
				'email' => array(
					'type' => 'string',
				),
			),
		));

	}
	add_action('rest_api_init', 'gmt_edd_for_courses_register_routes');