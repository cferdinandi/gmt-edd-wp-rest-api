# GMT EDD WP Rest API
Add WP Rest API hooks into Easy Digital Downloads to get products and subscription data.

## How to use it

### The Endpoints

```bash
# Get purchase details for a user
/wp-json/gmt-edd/v1/users/<user_email_address>

# Get subscription details for a user and product
/wp-json/gmt-edd/v1/subscriptions?email=<user_email_address>&id=<product_id>
```

### Making a request with WordPress

You'll need to configure an options menu to get the domain, username, and password for authorization. I recommend using the [Application Passwords](https://wordpress.org/plugins/application-passwords/) plugin with this.

```php
// Get all user purchases
wp_remote_request(
	rtrim($options['wp_api_url'], '/') . '/wp-json/gmt-edd/v1/users/' . $email,
	array(
		'method'    => 'GET',
		'headers'   => array(
			'Authorization' => 'Basic ' . base64_encode($options['wp_api_username'] . ':' . $options['wp_api_password']),
		),
	)
);

// Get user subscription data
wp_remote_request(
	rtrim($options['wp_api_url'], '/') . '/wp-json/gmt-edd/v1/subscriptions?email=' . $email . '&id=' . $product_id,
	array(
		'method'    => 'GET',
		'headers'   => array(
			'Authorization' => 'Basic ' . base64_encode($options['wp_api_username'] . ':' . $options['wp_api_password']),
		),
	)
);
```