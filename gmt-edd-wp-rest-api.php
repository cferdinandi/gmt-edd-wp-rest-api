<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 0.0.2
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: GPLv3
 */

	function gmt_edd_get_user_purchases($data) {

		// if no email, throw an error
		if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return new WP_Error( 'code', __( 'Not a valid email address', 'edd_for_courses' ) );
		}

		// Get purchases
		$purchases = edd_get_users_purchased_products($data['email']);
		$purchase_list = array();
		foreach ($purchases as $purchase) {
			if (edd_is_bundled_product($purchase->ID)) {
				foreach (edd_get_bundled_products($purchase->ID) as $bundle) {
					$purchase_list[] = array_shift(explode('_', $bundle));
				}
			} else {
				$purchase_list[] = $purchase->ID;
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