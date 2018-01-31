<?php

/**
 * Plugin Name: GMT EDD WP Rest API
 * Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-edd-wp-rest-api/
 * Description: Add WP Rest API hooks into Easy Digital Downloads.
 * Version: 0.0.1
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: GPLv3
 */

	function gmt_edd_for_courses_process_user( $data ) {

		// if no email, throw an error
		if (empty($data['email']) || !is_email($data['email'])) {
			return new WP_Error( 'code', __( 'Not a valid email address', 'edd_for_courses' ) );
		}

		// Flush the transient for this user
		$purchases = gmt_edd_for_courses_get_purchases( $data['email'], true );

		// If flush fails, error
		if (empty($purchases)) {
			return new WP_Error( 'code', __( 'Flush failed', 'edd_for_courses' ) );
		}

		// Return success
		return new WP_REST_Response( 'success', 200 );

	}


	function gmt_edd_for_courses_process_products() {

		// Flush the transient for this user
		$products = gmt_edd_for_courses_get_products( true );

		// If flush fails, error
		if (empty($products)) {
			return new WP_Error( 'code', __( 'Flush failed', 'edd_for_courses' ) );
		}

		// Return success
		return new WP_REST_Response( 'success', 200 );

	}


	function gmt_edd_for_courses_register_routes () {
		register_rest_route('gmt-edd-for-courses/v1', '/users/(?P<email>\S+)', array(
			'methods' => 'PUT',
			'callback' => 'gmt_edd_for_courses_process_user',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
			'args' => array(
				'email' => array(
					'type' => 'string',
				),
			),
		));

		register_rest_route('gmt-edd-for-courses/v1', '/products', array(
			'methods' => 'PUT',
			'callback' => 'gmt_edd_for_courses_process_products',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		));
	}
	add_action( 'rest_api_init', 'gmt_edd_for_courses_register_routes' );