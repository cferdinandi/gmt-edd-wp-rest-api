<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 2.1.1
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: GPLv3
 */

	/**
	 * Get a user's subscription data
	 * @param  String $email The user email address
	 * @return Object        The subscription data
	 */
	function gmt_edd_get_user_subscriptions ($data) {

		// if no email, throw an error
		if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return new WP_Error( 400, __( 'Not a valid email address', 'edd_for_courses' ) );
		}

		// Make sure there's a subscriber class
		if (!class_exists('EDD_Recurring_Subscriber')) {
			return new WP_Error( 500, __( 'Subscriptions not enabled.', 'edd_for_courses' ) );
		}

		// Get the subscriber
		$email = sanitize_email($data['email']);
		$subscriber = new EDD_Recurring_Subscriber($email);
		if (empty($subscriber)) {
			return new WP_Error( 400, __( 'Subscriber not found.', 'edd_for_courses' ) );
		}

		// Get subscriptions
		$subscriptions = $subscriber->get_subscriptions();

		// Add missing data to subscriptions
		foreach ($subscriptions as $index => $subscription) {
			$subscription_data = new EDD_Subscription($subscription->id);
			$product_data = new EDD_Download($subscription->product_id);
			$subscription->times_billed = $subscription_data->get_times_billed();
			$subscription->product = $product_data->post_title;
		}

		// Return success
		return new WP_REST_Response($subscriptions, 200);

	}


	/**
	 * Get user purchase data
	 * @param  Object $request The request object
	 * @return JSON            The REST API Response
	 */
	function gmt_edd_get_user_purchases ($data) {

		// if no email, throw an error
		if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return new WP_Error( 400, __( 'Not a valid email address', 'edd_for_courses' ) );
		}

		// Get user purchases
		// @deprecated with EDD v3.x - breaking error for email updates
		// $email = sanitize_email($data['email']);
		// $purchases = edd_get_users_purchases($email);

		// Get user purchases
		$email = sanitize_email($data['email']);
		$customer = new EDD_Customer( $email );
		$purchases = $customer->get_orders(array('publish', 'complete', 'completed', 'partially_refunded', 'edd_subscription'));

		// Set up list of purchases
		$purchase_list = array();
		$invoice_list = array();

		// Loop through purchases
		foreach ($purchases as $purchase) {

			// Only get completed purchases
			// @required after edd_get_users_purchases() stopped working
			// if ($purchase->status !== 'complete') continue;

			// Get the user's purchases
			$payment = new EDD_Payment( $purchase->ID );
			$purchased_files = $payment->cart_details;

			if ( is_array( $purchased_files ) ) {

				$products = array();

				foreach ( $purchased_files as $download ) {

					// Get price_id
					$price_id = isset($download['item_number']['options']['price_id']) ? strval($download['item_number']['options']['price_id']) : null;

					// Add ID to list (with price ID if they exist)
					if ( edd_has_variable_prices( $download['id'] ) && !empty($price_id) ) {
						$purchase_list[] = $download['id'] . '_' . $price_id;
					} else {
						$purchase_list[] = strval($download['id']);
					}

					// If contains bundled products, add them
					if ( edd_is_bundled_product( $download['id'] ) ) {
						foreach ( edd_get_bundled_products( $download['id'], $price_id ) as $key => $bundle ) {
							$purchase_list[] = $bundle;
						}
					}

					// Get products
					$products[] = array(
						'name' => str_replace(' - _', '', str_replace(' — _', '', $download['name'])),
						'price' => floatval($download['item_price']),
						'discount' => floatval($download['discount']),
						'total' => floatval($download['price']),
					);

				}

				$invoice_list[] = array(
					'id' => $purchase->ID,
					'date' => date_format(date_create($purchase->date), 'F j, Y'),
					'total' => edd_format_amount($payment->total),
					'products' => $products,
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


	/**
	 * Get the number of sales for a product
	 * @param  Object $request The request object
	 * @return JSON            The REST API Response
	 */
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

		// Check if category specified
		$has_category = array_key_exists('category', $params);

		// Check allowed categories
		if ($has_category && !empty($categories)) {
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

		// If no category, get all customers
		if (!$has_category) {
			$customers = edd_get_customers(array(
				'number' => 9999999
			));
			$sales = 0;
			foreach ($customers as $customer) {
				if ($customer->purchase_count < 1) continue;
				$sales++;
			}
			return new WP_REST_Response(array(
				'code' => 200,
				'status' => 'success',
				'message' => empty($params['round']) ? edd_format_amount( $sales, false ) : edd_format_amount( gmt_edd_round($sales, $params['round']), false ),
			), 200);
		}

		// Create download args
		$args = array(
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
		);

		// // If there's a category, add it
		// if ($has_category) {
		// 	$args['tax_query'] = array(
		// 		array(
		// 			'taxonomy' => 'download_category',
		// 			'field'    => 'slug',
		// 			'terms'    => $params['category'],
		// 		),
		// 	);
		// }

		// Get downloads from the category
		$downloads = get_posts($args);

		// Count total number of sales
		// $sales = 0;
		$emails = array();
		foreach ( $downloads as $download ) {
			// $sales += EDD()->payment_stats->get_sales( $download, !empty($params['start']) ? $params['start'] : 'this_month', !empty($params['end']) ? $params['end'] : 'this_month' );
			$orders = edd_get_orders(array(
				'product_id' => $download,
				'number'     => 99999999,
				'status'     => array('complete', 'renewal'),
			));
			foreach ($orders as $order) {
				$emails[] = $order->email;
			}
		}
		$sales = count(array_unique($emails));

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

		register_rest_route('gmt-edd/v1', '/subscriptions/(?P<email>\S+)', array(
			'methods' => 'GET',
			'callback' => 'gmt_edd_get_user_subscriptions',
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