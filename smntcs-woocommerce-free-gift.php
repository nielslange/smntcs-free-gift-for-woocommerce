<?php
/**
 * Plugin Name:           SMNTCS Free Gift for WooCommerce
 * Plugin URI:            https://github.com/nielslange/smntcs-woocommerce-free-gift
 * Description:           Offers customers a free gift from a product category once their cart reaches a minimum value.
 * Author:                Niels Lange
 * Author URI:            https://nielslange.de
 * Text Domain:           smntcs-woocommerce-free-gift
 * Version:               2.1
 * Requires PHP:          7.4
 * Requires at least:     5.9
 * Requires Plugins:      woocommerce
 * WC requires at least:  3.0
 * WC tested up to:       11.1
 * License:               GPL v2 or later
 * License URI:           https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SMNTCS_Free_Gift_for_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a plugin setting, falling back to the default shown in the Customizer.
 *
 * Settings that were never saved in the Customizer do not exist in the
 * database, so every read goes through this function.
 *
 * @since 2.1
 * @param string $key The option name.
 * @return mixed The option value.
 */
function wfg_get_option( $key ) {
	$defaults = array(
		'wfg_enable_free_gift'   => true,
		'wfg_hide_gift_category' => false,
		'wfg_minimum_cart_value' => '10.00',
		'wfg_gift_category'      => '',
		/* translators: {amount} is replaced with the minimum cart value. */
		'wfg_message_value_low'  => __( 'Spend {amount} or more and you will receive a free gift.', 'smntcs-woocommerce-free-gift' ),
		'wfg_button_value_low'   => __( 'Continue shopping', 'smntcs-woocommerce-free-gift' ),
		/* translators: {amount} is replaced with the minimum cart value. */
		'wfg_message_value_ok'   => __( 'Your order is above {amount}. Would you like a free gift?', 'smntcs-woocommerce-free-gift' ),
		'wfg_button_value_ok'    => __( 'Yes, please!', 'smntcs-woocommerce-free-gift' ),
	);

	return get_option( $key, $defaults[ $key ] ?? '' );
}

/**
 * Check whether the free gift is enabled and has a gift category.
 *
 * @since 2.1
 * @return bool
 */
function wfg_is_enabled() {
	return class_exists( 'WooCommerce' )
		&& rest_sanitize_boolean( wfg_get_option( 'wfg_enable_free_gift' ) )
		&& '' !== (string) wfg_get_option( 'wfg_gift_category' );
}

/**
 * Show warning if WooCommerce is not active or WooCommerce version < 3.0
 *
 * @since 1.8
 */
add_action(
	'admin_notices',
	function () {
		if ( ! class_exists( 'WooCommerce' ) || version_compare( WC()->version, '3.0', '<' ) ) {
			$message = __( 'SMNTCS Free Gift for WooCommerce requires at least WooCommerce 3.0', 'smntcs-woocommerce-free-gift' );
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	},
	10,
	0
);

/**
 * Declare compatibility with WooCommerce features.
 *
 * @since 2.1
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	},
	10,
	0
);

/**
 * Enhance customizer
 *
 * @param WP_Customize_Manager $wp_customize The customizer object.
 * @return void
 */
function wfg_enhance_customizer( $wp_customize ) {
	// Return if WooCommerce hasn't been installed.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Fetch WooCommerce categories.
	$choices            = array( '' => __( '— Select a category —', 'smntcs-woocommerce-free-gift' ) );
	$product_categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);
	if ( is_array( $product_categories ) ) {
		foreach ( $product_categories as $product_category ) {
			$choices[ $product_category->slug ] = $product_category->name;
		}
	}

	// Create customizer section.
	$wp_customize->add_section(
		'wfg_section',
		array(
			'title'       => __( 'Free Gift', 'smntcs-woocommerce-free-gift' ),
			'description' => __( 'Use {amount} in a message to show the minimum cart value.', 'smntcs-woocommerce-free-gift' ),
			'priority'    => 50,
			'panel'       => 'woocommerce',
		)
	);

	$fields = array(
		'wfg_enable_free_gift'   => array( 'checkbox', __( 'Enable free gift', 'smntcs-woocommerce-free-gift' ) ),
		'wfg_hide_gift_category' => array( 'checkbox', __( 'Hide gift products and the gift category from the shop', 'smntcs-woocommerce-free-gift' ) ),
		/* translators: %s is the base currency code, e.g. USD */
		'wfg_minimum_cart_value' => array( 'text', sprintf( __( 'Minimum cart value in %s', 'smntcs-woocommerce-free-gift' ), get_woocommerce_currency() ) ),
		'wfg_gift_category'      => array( 'select', __( 'Gift category', 'smntcs-woocommerce-free-gift' ) ),
		'wfg_message_value_low'  => array( 'textarea', __( 'Message "Continue shopping"', 'smntcs-woocommerce-free-gift' ) ),
		'wfg_button_value_low'   => array( 'text', __( 'Button "Continue shopping"', 'smntcs-woocommerce-free-gift' ) ),
		'wfg_message_value_ok'   => array( 'textarea', __( 'Message "Add gift"', 'smntcs-woocommerce-free-gift' ) ),
		'wfg_button_value_ok'    => array( 'text', __( 'Button "Add gift"', 'smntcs-woocommerce-free-gift' ) ),
	);

	foreach ( $fields as $id => $field ) {
		list( $type, $label ) = $field;

		$default = wfg_get_option( $id );
		if ( 'checkbox' === $type ) {
			$sanitize = 'rest_sanitize_boolean';
		} elseif ( 'textarea' === $type ) {
			$sanitize = 'wp_kses_post';
		} else {
			$sanitize = 'sanitize_text_field';
		}

		$wp_customize->add_setting(
			$id,
			array(
				'default'           => is_bool( $default ) ? (string) (int) $default : (string) $default,
				'type'              => 'option',
				'sanitize_callback' => $sanitize,
			)
		);

		$control = array(
			'label'   => $label,
			'section' => 'wfg_section',
			'type'    => $type,
		);
		if ( 'select' === $type ) {
			$control['choices'] = $choices;
		}

		$wp_customize->add_control( $id, $control );
	}
}
add_action( 'customize_register', 'wfg_enhance_customizer' );

/**
 * Get the minimum cart value as a number.
 *
 * @since 2.1
 * @return float
 */
function wfg_get_minimum_cart_value() {
	return (float) str_replace( ',', '.', (string) wfg_get_option( 'wfg_minimum_cart_value' ) );
}

/**
 * Get the text of a message with the {amount} placeholder filled in.
 *
 * @since 2.1
 * @param string $key The option name of the message.
 * @return string The message HTML.
 */
function wfg_get_message( $key ) {
	$amount = wp_strip_all_tags( wc_price( wfg_get_minimum_cart_value() ) );

	return wp_kses_post( str_replace( '{amount}', $amount, (string) wfg_get_option( $key ) ) );
}

/**
 * Pick a random gift product from the gift category.
 *
 * @since 2.1
 * @return WC_Product|null
 */
function wfg_get_random_gift() {
	$gifts = wc_get_products(
		array(
			'category'     => array( (string) wfg_get_option( 'wfg_gift_category' ) ),
			'status'       => 'publish',
			'stock_status' => 'instock',
			'orderby'      => 'rand',
			'limit'        => 1,
		)
	);

	return ( is_array( $gifts ) && isset( $gifts[0] ) && $gifts[0] instanceof WC_Product ) ? $gifts[0] : null;
}

/**
 * Build the cart notice for the current cart.
 *
 * @since 2.1
 * @return string The notice HTML, or an empty string when there is nothing to say.
 */
function wfg_get_status_message() {
	if ( ! wfg_is_enabled() || ! WC()->cart || WC()->cart->is_empty() || wfg_has_gift() ) {
		return '';
	}

	if ( (float) WC()->cart->get_subtotal() < wfg_get_minimum_cart_value() ) {
		$link = sprintf(
			'<a href="%s" class="button wc-forward">%s</a>',
			esc_url( wc_get_page_permalink( 'shop' ) ),
			esc_html( (string) wfg_get_option( 'wfg_button_value_low' ) )
		);

		return wc_print_notice( wfg_get_message( 'wfg_message_value_low' ) . ' ' . $link, 'notice', array(), true );
	}

	$gift = wfg_get_random_gift();
	if ( ! $gift ) {
		return '';
	}

	$link = sprintf(
		'<a href="%s" class="button wc-forward">%s</a>',
		esc_url( add_query_arg( 'add-to-cart', $gift->get_id(), wc_get_cart_url() ) ),
		esc_html( (string) wfg_get_option( 'wfg_button_value_ok' ) )
	);

	return wc_print_notice( wfg_get_message( 'wfg_message_value_ok' ) . ' ' . $link, 'success', array(), true );
}

/**
 * Show the free gift notice above the classic cart.
 *
 * @return void
 */
function wfg_status_message() {
	echo wfg_get_status_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
}
add_action( 'woocommerce_before_cart_table', 'wfg_status_message', 100, 0 );

/**
 * Show the free gift notice above the Cart block.
 *
 * @since 2.1
 * @param string $content The rendered Cart block.
 * @return string
 */
function wfg_status_message_cart_block( $content ) {
	$message = wfg_get_status_message();

	return $message ? '<div class="woocommerce wfg-notice">' . $message . '</div>' . $content : $content;
}
add_filter( 'render_block_woocommerce/cart', 'wfg_status_message_cart_block' );

/**
 * Check if cart has any physical products
 *
 * @return bool True if the cart has at least one physical product, else false.
 */
function wfg_has_physical_products() {
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $item ) {
		if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product && ! $item['data']->is_virtual() ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if the cart already contains a gift
 *
 * @return int|false The product ID of the gift in the cart, else false.
 */
function wfg_has_gift() {
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return false;
	}

	$category = (string) wfg_get_option( 'wfg_gift_category' );
	if ( '' === $category ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $item ) {
		if ( has_term( $category, 'product_cat', (int) $item['product_id'] ) ) {
			return (int) $item['product_id'];
		}
	}

	return false;
}

/**
 * Check whether gift products should be hidden from the shop.
 *
 * @since 2.1
 * @return bool
 */
function wfg_hide_gifts() {
	return wfg_is_enabled() && rest_sanitize_boolean( wfg_get_option( 'wfg_hide_gift_category' ) );
}

/**
 * Hide gift category
 *
 * @param array $args The original array with arguments.
 * @return array $args The updated array with arguments.
 */
function wfg_hide_gift_category( $args ) {
	if ( ! wfg_hide_gifts() ) {
		return $args;
	}

	$term = get_term_by( 'slug', (string) wfg_get_option( 'wfg_gift_category' ), 'product_cat' );
	if ( $term instanceof WP_Term ) {
		$exclude         = isset( $args['exclude'] ) ? wp_parse_id_list( $args['exclude'] ) : array();
		$args['exclude'] = array_merge( $exclude, array( $term->term_id ) );
	}

	return $args;
}
add_filter( 'woocommerce_product_categories_widget_args', 'wfg_hide_gift_category' );
add_filter( 'woocommerce_product_subcategories_args', 'wfg_hide_gift_category' );

/**
 * Get the tax query clause that leaves out gift products.
 *
 * @since 2.1
 * @return array
 */
function wfg_get_exclude_tax_query() {
	return array(
		'taxonomy' => 'product_cat',
		'field'    => 'slug',
		'terms'    => array( (string) wfg_get_option( 'wfg_gift_category' ) ),
		'operator' => 'NOT IN',
	);
}

/**
 * Leave gift products out of the shop, category, tag and search pages.
 *
 * @since 2.1
 * @param WP_Query $query The product query.
 * @return void
 */
function wfg_hide_gifts_from_product_query( $query ) {
	if ( ! wfg_hide_gifts() || is_admin() ) {
		return;
	}

	$tax_query   = (array) $query->get( 'tax_query' );
	$tax_query[] = wfg_get_exclude_tax_query();
	$query->set( 'tax_query', $tax_query );
}
add_action( 'woocommerce_product_query', 'wfg_hide_gifts_from_product_query' );

/**
 * Leave gift products out of WordPress search results.
 *
 * @since 2.1
 * @param WP_Query $query The query.
 * @return void
 */
function wfg_hide_gifts_from_search( $query ) {
	if ( ! wfg_hide_gifts() || is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$tax_query   = (array) $query->get( 'tax_query' );
	$tax_query[] = wfg_get_exclude_tax_query();
	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'wfg_hide_gifts_from_search' );

/**
 * Leave gift products out of the [products] shortcode and product blocks.
 *
 * @since 2.1
 * @param array $args The query arguments.
 * @return array
 */
function wfg_hide_gifts_from_query_args( $args ) {
	if ( ! wfg_hide_gifts() ) {
		return $args;
	}

	$post_type = $args['post_type'] ?? '';
	if ( 'product' !== $post_type && ! ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
		return $args;
	}

	$args['tax_query']   = isset( $args['tax_query'] ) ? (array) $args['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	$args['tax_query'][] = wfg_get_exclude_tax_query();

	return $args;
}
add_filter( 'woocommerce_shortcode_products_query', 'wfg_hide_gifts_from_query_args' );
add_filter( 'query_loop_block_query_vars', 'wfg_hide_gifts_from_query_args' );
