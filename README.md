# GMT EDD WP Rest API
Add WP Rest API hooks into Easy Digital Downloads to get product data.

## How to use it

### The Endpoints

```bash
# Get purchase details for a user
/wp-json/gmt-edd/v1/users/<user_email_address>

# Get sales data for a category of products
/wp-json/gmt-edd/v1/sales?category=<category_id>&start=<start_date>&end=<end_date>&<key>=<secret>
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

### Environment variables

The `/sales` endpoint can be configured and customized using environment variables.

| Variable         | Definition                                               |
|------------------|----------------------------------------------------------|
| `EDD_ORIGINS`    | Restrict API calls to specific domains (comma-separated) |
| `EDD_CATEGORIES` | Restrict data to specific categories (comma-separated)   |
| `EDD_KEY`        | A key you can use for added authentication [optional]    |
| `EDD_SECRET`     | A secret you can use for added authentication [optional] |