<?php
/*
 Plugin Name: SMNTCS WooCommerce Free Gift
 Description: Give free gifts to your WooCommerce customers 
 Author: Niels Lange
 Author URI: https://nielslange.com
 Version: 1.0
 Tested up to: 4.5.3
 Requires at least: 3.4
 License: GPLv2 or later
 Text Domain: smntcs-woocommerce-free-gift
 License: GPLv2
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 Copyright 2005-2016 Niels Lange
 */

// Avoid direct plugin access
if ( !defined( 'ABSPATH' ) ) exit;

// Load text domain
add_action('plugins_loaded', 'wfg_load_textdomain');
function wfg_load_textdomain() {
	load_plugin_textdomain( 'smntcs-woocommerce-free-gift', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/'  );
}

// Enhance WordPress customizer
add_action( 'customize_register', 'wfg_enhance_customizer' );
function wfg_enhance_customizer($wp_customize) {
	$product_cats = get_terms( 'product_cat');
	foreach ($product_cats as $product_cat) {
		$choices[$product_cat->slug] = $product_cat->name;
	}
	
	$wp_customize->add_section( 'wfg_section', array(
		'title' 	=> __('WooCommerce Free Gift', 'smntcs-woocommerce-free-gift'),
		'priority' 	=> 150,
	));
	
	// Enable free gift 
	$wp_customize->add_setting( 'wfg_enable_free_gift', array( 'default' => false) );
	$wp_customize->add_control( 'wfg_enable_free_gift', array( 'label' => __('Enable free gift', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'checkbox' ));
	
	// Disable for virtual products
	$wp_customize->add_setting( 'wfg_disable_virtual_products', array( 'default' => false) );
	$wp_customize->add_control( 'wfg_disable_virtual_products', array( 'label' => __('Disable for virtual products', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'checkbox' ));
	
	// Hide gift category
	$wp_customize->add_setting( 'wfg_hide_gift_category', array( 'default' => false) );
	$wp_customize->add_control( 'wfg_hide_gift_category', array( 'label' => __('Hide gift category', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'checkbox' ));
	
	// Minimum cart value
	$wp_customize->add_setting( 'wfg_minimum_cart_value', array( 'default' => '') );
	$wp_customize->add_control( 'wfg_minimum_cart_value', array( 'label' => __('Minimum cart value', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'text' ));
	
	// Gift category
	$wp_customize->add_setting( 'wfg_gift_category', array( 'default' => '') );
	$wp_customize->add_control( 'wfg_gift_category', array( 'label' => __('Gift category', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'select', 'choices' => $choices));
	
	// Message "Proceed shopping"
	$wp_customize->add_setting( 'wfg_message_value_low', array( 'default' => '') );
	$wp_customize->add_control( 'wfg_message_value_low', array( 'label' => __('Message "Proceed shopping"', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'textarea' ));
	
	// Button "Proceed shopping"
	$wp_customize->add_setting( 'wfg_button_value_low', array( 'default' => '') );
	$wp_customize->add_control( 'wfg_button_value_low', array( 'label' => __('Button "Proceed shopping"', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'text' ));
	
	// Message "Add gift"
	$wp_customize->add_setting( 'wfg_message_value_ok', array( 'default' => '') );
	$wp_customize->add_control( 'wfg_message_value_ok', array( 'label' => __('Message "Add gift"', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'textarea' ));
	
	// Button "Add gift"
	$wp_customize->add_setting( 'wfg_button_value_ok', array( 'default' => '') );
	$wp_customize->add_control( 'wfg_button_value_ok', array( 'label' => __('Button "Add gift"', 'smntcs-woocommerce-free-gift'), 'section' => 'wfg_section', 'type' => 'text' ));
}

// Show gift status message in cart
if ( get_theme_mod('wfg_enable_free_gift') && get_theme_mod('wfg_minimum_cart_value') && get_theme_mod('wfg_gift_category') && get_theme_mod('wfg_message_value_low') && get_theme_mod('wfg_button_value_low') && get_theme_mod('wfg_message_value_ok') && get_theme_mod('wfg_button_value_ok')) {
	add_action( 'woocommerce_before_cart_table', 'wfg_status_message', 9 );
	function wfg_status_message() {
		global $woocommerce;
	
		if ( wfg_has_physical_products() ) {
			if ( $woocommerce->cart->subtotal < 10 && !wfg_has_gift()) {
				wc_print_notice( get_theme_mod('wfg_message_value_low') . ' <a href="/shop">' . get_theme_mod('wfg_button_value_low') . '</a>', 'notice' );
			} else {
				if ( !wfg_has_gift() ) {
					$args = array(
						'post_type' 		=> 'product',
						'product_cat'		=> get_theme_mod('wfg_gift_category'),
						'orderby'        	=> 'rand',
						'posts_per_page' 	=> '1',
					);
					$gift = new WP_Query( $args );
					wc_print_notice( get_theme_mod('wfg_message_value_ok') . '  <a href="?add-to-cart=' . $gift->post->ID . '">' . get_theme_mod('wfg_button_value_ok') . '</a>', 'notice' );
				}
			}
		}
	}
}

// Check if cart has any physical products
if ( !get_theme_mod('wfg_disable_virtual_products') ) {	
	function wfg_has_physical_products() {
		global $woocommerce;
	
		foreach ($woocommerce->cart->get_cart() as $product) {
			if ( get_post_meta($product['product_id'], '_virtual', true) != 'yes') {
				return true;
			}
		}
		
		return false;
	}
}

// Check if cart has any physical products
function wfg_has_gift() {
	global $woocommerce;

	foreach ($woocommerce->cart->get_cart() as $product) {
		foreach (get_the_terms($product['product_id'], 'product_cat') as $category) {
			if ( $category->slug == get_theme_mod('wfg_gift_category') ) return $product['product_id'];
		}
	}

	return false;
}

// Hide gift category
if ( !get_theme_mod('wfg_button_value_ok') ) {
	add_filter( 'woocommerce_product_categories_widget_args', 'wfg_hide_gift_category' );
	function wfg_hide_gift_category($args) {
		$product 		 = get_term_by( 'slug', get_theme_mod('wfg_gift_category'), 'product_cat' );
		$args['exclude'] = array($product->term_id);
	
		return $args;
	}
}