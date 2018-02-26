<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 0.2.0
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
		$purchases = edd_get_users_purchases($data['email']);

		// Set up list of purchases
		$purchase_list = array();

		foreach ($purchases as $purchase) {
			$payment = new EDD_Payment( $purchase->ID );
			$purchased_files = $payment->cart_details;

			if ( is_array( $purchased_files ) ) {
				foreach ( $purchased_files as $download ) {
					if ( edd_is_bundled_product( $download['id'] ) ) {
						foreach ( edd_get_bundled_products( $download['id'] ) as $bundle ) {
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


				}
			}
		}

		// Return success
		return new WP_REST_Response(array_unique($purchase_list), 200);

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