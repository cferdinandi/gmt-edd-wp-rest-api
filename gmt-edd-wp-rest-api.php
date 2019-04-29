<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 1.1.1
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: GPLv3
 */

	function gmt_edd_get_user_subscriptions($request) {

		// Get request parameters
		$params = $request->get_params();

		// if no email, throw an error
		if (empty($params['email']) || !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
			return new WP_Error( 'code', __( 'Not a valid email address', 'edd_for_courses' ) );
		}

		// If no product ID, throw an error
		if (empty($params['id'])) {
			return new WP_Error( 'code', __( 'Missing product ID', 'edd_for_courses' ) );
		}

		// Get subscription
		$subscriber = new EDD_Recurring_Subscriber(sanitize_email($params['email']));
		$subscription = $subscriber->get_subscriptions(wp_filter_nohtml_kses($params['id']));

		// If there's no subscription
		if (empty($subscription)) {
			return new WP_REST_Response(array(
				'status' => 'no_subscriptions'
			), 200);
		}

		// Get subscription details
		$subscription_payments = $subscription[0]->get_child_payments();
		$response = array(
			'status' => $subscription[0]->status,
			'amount' => $subscription[0]->recurring_amount,
			'created' => date('F j, Y', strtotime( $subscription[0]->created )),
			'expires' => date('F j, Y', strtotime( $subscription[0]->expiration )),
			'gateway' => $subscription[0]->gateway,
			'payments' => array(),
		);

		// Create payments array
		foreach ( $subscription_payments as $payment ) {
			$response['payments'][] = array(
				'id'     => $payment->ID,
				'amount' => $payment->total,
				'date'   => date('F j, Y', strtotime( $payment->date )),
				'status' => $payment->status_nicename,
			);
		}

		// Return success
		return new WP_REST_Response($response, 200);

	}

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
					} else if ( isset( $download['item_number']['options']['recurring'] ) ) {
						$subscriptions_list[] = $download['id'];
						$subscriber = new EDD_Recurring_Subscriber( $data['email'] );
						if ( !empty( $subscriber->has_active_product_subscription( $download['id'] ) ) ) {
							$purchase_list[] = $download['id'];
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

		register_rest_route('gmt-edd/v1', '/subscriptions', array(
			'methods' => 'GET',
			'callback' => 'gmt_edd_get_user_subscriptions',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		));

	}
	add_action('rest_api_init', 'gmt_edd_for_courses_register_routes');