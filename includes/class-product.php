<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_Composite' ) && class_exists( 'WC_Product' ) ) {
	class WC_Product_Composite extends WC_Product {
		public function __construct( $product = 0 ) {
			parent::__construct( $product );
		}

		public function get_type() {
			return 'composite';
		}

		public function add_to_cart_url() {
			$product_id = $this->id;

			return apply_filters( 'wooco_product_add_to_cart_url', apply_filters( 'woocommerce_product_add_to_cart_url', get_permalink( $product_id ), $this ), $this );
		}

		public function add_to_cart_text() {
			if ( $this->is_purchasable() && $this->is_in_stock() ) {
				$text = WPCleverWooco::localization( 'button_select', esc_html__( 'Select options', 'wpc-composite-products' ) );
			} else {
				$text = WPCleverWooco::localization( 'button_read', esc_html__( 'Read more', 'wpc-composite-products' ) );
			}

			return apply_filters( 'wooco_product_add_to_cart_text', $text, $this );
		}

		public function single_add_to_cart_text() {
			$text = WPCleverWooco::localization( 'button_single', esc_html__( 'Add to cart', 'wpc-composite-products' ) );

			return apply_filters( 'wooco_product_single_add_to_cart_text', $text, $this );
		}

		public function get_price( $context = 'view' ) {
			if ( ( $context === 'view' ) && ( (float) $this->get_regular_price() == 0 ) ) {
				return apply_filters( 'woocommerce_product_get_price', 0, $this );
			}

			if ( ( $context === 'view' ) && ( (float) parent::get_price( $context ) == 0 ) ) {
				return apply_filters( 'woocommerce_product_get_price', 0, $this );
			}

			return parent::get_price( $context );
		}

		// extra functions

		public function get_pricing() {
			$product_id = $this->id;

			return apply_filters( 'wooco_product_get_pricing', get_post_meta( $product_id, 'wooco_pricing', true ), $this );
		}

		public function get_discount() {
			$product_id = $this->id;
			$discount   = 0;

			if ( ( $this->get_pricing() !== 'only' ) && ( $_discount = get_post_meta( $product_id, 'wooco_discount_percent', true ) ) && is_numeric( $_discount ) && ( (float) $_discount < 100 ) && ( (float) $_discount > 0 ) ) {
				$discount = (float) $_discount;
			}

			return apply_filters( 'wooco_product_get_discount', $discount, $this );
		}

		public function get_components() {
			$product_id = $this->id;
			$components = get_post_meta( $product_id, 'wooco_components', true );

			return apply_filters( 'wooco_product_get_components', $components, $this );
		}

		public function get_composite_price() {
			// FB for WC
			return $this->get_price();
		}

		public function get_composite_price_including_tax() {
			// FB for WC
			return $this->get_price();
		}
	}
}
