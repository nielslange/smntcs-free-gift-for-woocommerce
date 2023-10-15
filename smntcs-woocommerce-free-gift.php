<?php
/**
 * Plugin Name:           SMNTCS Free Gift for WooCommerce
 * Plugin URI:            https://github.com/nielslange/smntcs-woocommerce-free-gift
 * Description:           Give free gifts to your WooCommerce customers.
 * Author:                Niels Lange
 * Author URI:            https://nielslange.de
 * Text Domain:           smntcs-woocommerce-free-gift
 * Version:               1.8
 * Requires PHP:          5.6
 * Requires at least:     3.4
 * WC requires at least:  3.0
 * WC tested up to:       7.1
 * License:               GPL v2 or later
 * License URI:           https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SMNTCS_Free_Gift_for_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Show warning if WooCommerce is not active or WooCommerce version < 3.0
 *
 * @since 1.8
 */
add_action(
	'admin_notices',
	function () {
		global $woocommerce;

		if ( ! class_exists( 'WooCommerce' ) || version_compare( $woocommerce->version, '3.0', '<' ) ) {
			$class   = 'notice notice-warning is-dismissible';
			$message = __( 'SMNTCS Free Gift for WooCommerce requires at least WooCommerce 3.0', 'smntcs-woocommerce-free-gift' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}
	}
);

/**
 * Enhance WordPress customizer
 *
 * @param WP_Customize_Manager $wp_customize The customizer object.
 * @return void
 */
function wfg_enhance_customizer( $wp_customize ) {
	global $woocommerce;

	// Return if WooCommerce hasn't been installed.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Fetch WooCommerce categories.
	$product_categories = get_terms( 'product_cat' );
	foreach ( $product_categories as $product_category ) {
		$choices[ $product_category->slug ] = $product_category->name;
	}

	// Create customizer section.
	$wp_customize->add_section(
		'wfg_section',
		array(
			'title'    => __( 'Free Gift', 'smntcs-woocommerce-free-gift' ),
			'priority' => 50,
			'panel'    => 'woocommerce',
		)
	);

	// Enable free gift.
	$wp_customize->add_setting(
		'wfg_enable_free_gift',
		array(
			'default' => true,
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_enable_free_gift',
		array(
			'label'   => __( 'Enable free gift', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'checkbox',
		)
	);

	// Hide gift category.
	$wp_customize->add_setting(
		'wfg_hide_gift_category',
		array(
			'default' => false,
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_hide_gift_category',
		array(
			'label'   => __( 'Hide gift category', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'checkbox',
		)
	);

	// Minimum cart value.
	$wp_customize->add_setting(
		'wfg_minimum_cart_value',
		array(
			'default' => '10.00',
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_minimum_cart_value',
		array(
			/* translators: %s is the base currency code, e.g. USD */
			'label'   => sprintf( esc_html__( 'Minimum cart value in %s', 'smntcs-woocommerce-free-gift' ), esc_html( get_woocommerce_currency() ) ),
			'section' => 'wfg_section',
			'type'    => 'text',
		)
	);

	// Gift category.
	$wp_customize->add_setting(
		'wfg_gift_category',
		array(
			'default' => '',
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_gift_category',
		array(
			'label'   => __( 'Gift category', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'select',
			'choices' => $choices,
		)
	);

	// Message "Proceed shopping".
	$wp_customize->add_setting(
		'wfg_message_value_low',
		array(
			'default' => __( 'From an order value of EUR 10.00 you will receive a free gift from me.', 'smntcs-woocommerce-free-gift' ),
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_message_value_low',
		array(
			'label'   => __( 'Message "Proceed shopping"', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'textarea',
		)
	);

	// Button "Proceed shopping".
	$wp_customize->add_setting(
		'wfg_button_value_low',
		array(
			'default' => __( 'Proceed shopping', 'smntcs-woocommerce-free-gift' ),
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_button_value_low',
		array(
			'label'   => __( 'Button "Proceed shopping"', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'text',
		)
	);

	// Message "Add gift".
	$wp_customize->add_setting(
		'wfg_message_value_ok',
		array(
			'default' => __( 'Hurrah, your order value is above EUR 10.00. May I give you a free gift?', 'smntcs-woocommerce-free-gift' ),
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_message_value_ok',
		array(
			'label'   => __( 'Message "Add gift"', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'textarea',
		)
	);

	// Button "Add gift".
	$wp_customize->add_setting(
		'wfg_button_value_ok',
		array(
			'default' => __( 'Yes, please!', 'smntcs-woocommerce-free-gift' ),
			'type'    => 'option',
		)
	);
	$wp_customize->add_control(
		'wfg_button_value_ok',
		array(
			'label'   => __( 'Button "Add gift"', 'smntcs-woocommerce-free-gift' ),
			'section' => 'wfg_section',
			'type'    => 'text',
		)
	);
}
add_action( 'customize_register', 'wfg_enhance_customizer' );

/**
 * Show gift status message in cart
 */
if ( get_option( 'wfg_enable_free_gift' ) && get_option( 'wfg_minimum_cart_value' ) && get_option( 'wfg_gift_category' ) && get_option( 'wfg_message_value_low' ) && get_option( 'wfg_button_value_low' ) && get_option( 'wfg_message_value_ok' ) && get_option( 'wfg_button_value_ok' ) ) {
	/**
	 * Print status message
	 *
	 * @return void
	 */
	function wfg_status_message() {
		// Return if WooCommerce hasn't been installed.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		global $woocommerce;

		if ( $woocommerce->cart->subtotal < get_option( 'wfg_minimum_cart_value' ) && ! wfg_has_gift() ) {
			wc_print_notice( get_option( 'wfg_message_value_low' ) . ' <a href="/shop">' . get_option( 'wfg_button_value_low' ) . '</a>', 'notice' );
		} else {
			if ( ! wfg_has_gift() ) {
				$args = array(
					'post_type'      => 'product',
					'product_cat'    => get_option( 'wfg_gift_category' ),
					'orderby'        => 'rand',
					'posts_per_page' => '1',
				);
				$gift = new WP_Query( $args );
				wc_print_notice( get_option( 'wfg_message_value_ok' ) . '  <a href="?add-to-cart=' . $gift->post->ID . '">' . get_option( 'wfg_button_value_ok' ) . '</a>', 'success' );
			}
		}
	}
	add_action( 'woocommerce_before_cart_table', 'wfg_status_message', 100 );
}

/**
 * Check if cart has any physical products
 *
 * @return bool True if product is virtual, else false.
 */
function wfg_has_physical_products() {
	// Return if WooCommerce hasn't been installed.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	global $woocommerce;

	foreach ( $woocommerce->cart->get_cart() as $product ) {
		if ( get_post_meta( $product['product_id'], '_virtual', true ) !== 'yes' ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if cart has any physical products
 *
 * @return int|bool Product ID if cart has physical products, else false.
 */
function wfg_has_gift() {
	// Return if WooCommerce hasn't been installed.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	global $woocommerce;

	foreach ( $woocommerce->cart->get_cart() as $product ) {
		foreach ( get_the_terms( $product['product_id'], 'product_cat' ) as $category ) {
			if ( get_option( 'wfg_gift_category' ) === $category->slug ) {
				return $product['product_id'];
			}
		}
	}

	return false;
}

/**
 * Hide gift category
 */
if ( ! get_option( 'wfg_button_value_ok' ) ) {
	/**
	 * Hide gift category
	 *
	 * @param array $args The original array with arguments.
	 * @return array $args The updated array with arguments.
	 */
	function wfg_hide_gift_category( $args ) {
		$product         = get_term_by( 'slug', get_option( 'wfg_gift_category' ), 'product_cat' );
		$args['exclude'] = array( $product->term_id );

		return $args;
	}
	add_filter( 'woocommerce_product_categories_widget_args', 'wfg_hide_gift_category' );
}
