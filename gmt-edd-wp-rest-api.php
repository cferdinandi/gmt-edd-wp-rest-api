<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 1.4.0
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

		foreach ($purchases as $purchase) {
			$payment = new EDD_Payment( $purchase->ID );
			$purchased_files = $payment->cart_details;

			if ( is_array( $purchased_files ) ) {

				$products = array();

				foreach ( $purchased_files as $download ) {

					// Get product IDs
					if ( edd_is_bundled_product( $download['id'] ) ) {
						$price_id = isset( $download['item_number']['options']['price_id'] ) ? strval($download['item_number']['options']['price_id']) : null;
						foreach ( edd_get_bundled_products( $download['id'], $price_id ) as $bundle ) {
							$purchase_list[] = $bundle;
						}
					} else {
						$variable_prices = edd_has_variable_prices( $download['id'] );
						if ( $variable_prices && isset( $download['item_number']['options']['price_id'] ) ) {
							$purchase_list[] = $download['id'] . '_' . $download['item_number']['options']['price_id'];
						} else {
							$purchase_list[] = strval($download['id']);
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
		), 200);

	}


	/**
	 * Round a number down to nearest value (10, 100, etc)
	 * @param  Integer $num       The number to round
	 * @param  Integer $precision The precision to round by
	 * @return Integer            The rounded number
	 */
	function gmt_edd_round($num, $precision) {
		$num = intval($num);
		if (empty($precision)) return $num;
		$precision = intval($precision);
		return number_format(floor($num / $precision) * $precision);
	}


	function gmt_edd_get_sales ($request) {

		// Get request parameters
		$params = $request->get_params();
		$origins = getenv('EDD_ORIGINS');
		$categories = getenv('EDD_CATEGORIES');
		$key = getenv('EDD_KEY');
		$secret = getenv('EDD_SECRET');

		// Check domain whitelist
		if (!empty($origins)) {
			$origin = $request->get_header('origin');
			if (empty($origin) || !in_array($origin, explode(',', $origins))) {
				return new WP_REST_Response(array(
					'code' => 400,
					'status' => 'disallowed_domain',
					'message' => 'This domain is not whitelisted.'
				), 400);
			}
		}

		// Check allowed categories
		if (!empty($categories)) {
			if (empty($params['category']) || !in_array($params['category'], explode(',', $categories))) {
				return new WP_REST_Response(array(
					'code' => 400,
					'status' => 'disallowed_category',
					'message' => 'This category is not allowed.'
				), 400);
			}
		}

		// Check key/secret
		if ( !empty($key) && !empty($secret) && (!isset($params[$key]) || empty($params[$key]) || $params[$key] !== $secret) ) {
			return new WP_REST_Response(array(
				'code' => 400,
				'status' => 'failed',
				'message' => 'Unable to get data. Please try again.'
			), 400);
		}

		// Get downloads from the category
		$downloads = get_posts(array(
			'post_type'      => 'download',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy' => 'download_category',
					'field'    => 'slug',
					'terms'    => $params['category'],
				),
			),
		));

		// Count total number of sales
		$sales = 0;
		foreach ( $downloads as $download ) {
			$sales += EDD()->payment_stats->get_sales( $download, $params['start'] ? $params['start'] : 'this_month', $params['end'] ? $params['end'] : 'this_month' );
		}

		return new WP_REST_Response(array(
			'code' => 200,
			'status' => 'success',
			'message' => empty($params['round']) ? edd_format_amount( $sales, false ) : edd_format_amount( gmt_edd_round($sales, $params['round']), false ),
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

		register_rest_route('gmt-edd/v1', '/sales', array(
			'methods' => 'GET',
			'callback' => 'gmt_edd_get_sales'
		));

	}
	add_action('rest_api_init', 'gmt_edd_for_courses_register_routes');