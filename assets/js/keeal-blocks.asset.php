<?php
/**
 * Dependency handles must match WooCommerce’s payment-method scripts or
 * Cart/Checkout blocks strip this script (see Payments\Api::verify_payment_methods_dependencies).
 */
return [
	'dependencies' => [
		'react-jsx-runtime',
		'wc-blocks-registry',
		'wc-sanitize',
		'wc-settings',
		'wp-element',
		'wp-html-entities',
		'wp-i18n',
		'wp-polyfill',
	],
];
