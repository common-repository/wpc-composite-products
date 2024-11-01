<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWooco' ) && class_exists( 'WC_Product' ) ) {
	class WPCleverWooco {
		protected static $settings = [];
		public static $localization = [];
		protected static $image_size = 'woocommerce_thumbnail';
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			self::$settings     = (array) get_option( 'wooco_settings', [] );
			self::$localization = (array) get_option( 'wooco_localization', [] );

			// Init
			add_action( 'init', [ $this, 'init' ] );

			// Settings
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );

			// Enqueue frontend scripts
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Enqueue backend scripts
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

			// AJAX
			add_action( 'wp_ajax_wooco_add_component', [ $this, 'ajax_add_component' ] );
			add_action( 'wp_ajax_wooco_save_components', [ $this, 'ajax_save_components' ] );
			add_action( 'wp_ajax_wooco_export_components', [ $this, 'ajax_export_components' ] );
			add_action( 'wp_ajax_wooco_search_term', [ $this, 'ajax_search_term' ] );
			add_action( 'wp_ajax_wooco_search_product', [ $this, 'ajax_search_product' ] );

			// AJAX gallery
			add_action( 'wc_ajax_wooco_load_gallery', [ $this, 'ajax_load_gallery' ] );

			// Add to selector
			add_filter( 'product_type_selector', [ $this, 'product_type_selector' ] );

			// Product data tabs
			add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );

			// Product data panels
			add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
			add_action( 'woocommerce_process_product_meta_composite', [ $this, 'process_meta_composite' ] );

			// Add to cart form & button
			add_action( 'woocommerce_composite_add_to_cart', [ $this, 'add_to_cart_form' ] );
			add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_to_cart_button' ] );

			// Add to cart
			add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', [ $this, 'found_in_cart' ], 10, 2 );
			add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'add_to_cart_validation' ], 10, 2 );
			add_action( 'woocommerce_add_to_cart', [ $this, 'add_to_cart' ], 10, 6 );
			add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 2 );
			add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'get_cart_item_from_session' ], 10, 2 );

			// Undo remove
			add_action( 'woocommerce_restore_cart_item', [ $this, 'restore_cart_item' ] );

			// Admin
			add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );

			// Cart item
			add_filter( 'woocommerce_cart_item_name', [ $this, 'cart_item_name' ], 10, 2 );
			add_filter( 'woocommerce_cart_item_quantity', [ $this, 'cart_item_quantity' ], 10, 3 );
			add_filter( 'woocommerce_cart_item_remove_link', [ $this, 'cart_item_remove_link' ], 10, 2 );
			add_filter( 'woocommerce_cart_contents_count', [ $this, 'cart_contents_count' ] );
			add_action( 'woocommerce_cart_item_removed', [ $this, 'cart_item_removed' ], 10, 2 );
			add_filter( 'woocommerce_cart_item_price', [ $this, 'cart_item_price' ], 10, 2 );
			add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'cart_item_subtotal' ], 10, 2 );

			// Hide on cart & checkout page
			if ( self::get_setting( 'hide_component', 'no' ) !== 'no' ) {
				add_filter( 'woocommerce_cart_item_visible', [ $this, 'cart_item_visible' ], 10, 2 );
				add_filter( 'woocommerce_checkout_cart_item_visible', [ $this, 'cart_item_visible' ], 10, 2 );
			}

			// Hide on mini-cart
			if ( self::get_setting( 'hide_component_mini_cart', 'no' ) === 'yes' ) {
				add_filter( 'woocommerce_widget_cart_item_visible', [ $this, 'cart_item_visible' ], 10, 2 );
			}

			// Hide on order details
			if ( self::get_setting( 'hide_component_order', 'no' ) !== 'no' ) {
				add_filter( 'woocommerce_order_item_visible', [ $this, 'order_item_visible' ], 10, 2 );
			}

			// Item class
			if ( self::get_setting( 'hide_component', 'no' ) !== 'yes' ) {
				add_filter( 'woocommerce_cart_item_class', [ $this, 'cart_item_class' ], 10, 2 );
				add_filter( 'woocommerce_mini_cart_item_class', [ $this, 'cart_item_class' ], 10, 2 );
				add_filter( 'woocommerce_order_item_class', [ $this, 'cart_item_class' ], 10, 2 );
			}

			// Get item data
			if ( self::get_setting( 'hide_component', 'no' ) === 'yes_text' || self::get_setting( 'hide_component', 'no' ) === 'yes_list' ) {
				add_filter( 'woocommerce_get_item_data', [ $this, 'cart_item_meta' ], 10, 2 );
			}

			// Hide item meta
			add_filter( 'woocommerce_order_item_get_formatted_meta_data', [
				$this,
				'order_item_get_formatted_meta_data'
			] );

			// Order item
			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 3 );
			add_filter( 'woocommerce_order_item_name', [ $this, 'cart_item_name' ], 10, 2 );
			add_filter( 'woocommerce_order_formatted_line_subtotal', [ $this, 'formatted_line_subtotal' ], 10, 2 );

			if ( self::get_setting( 'hide_component_order', 'no' ) === 'yes_text' || self::get_setting( 'hide_component_order', 'no' ) === 'yes_list' ) {
				add_action( 'woocommerce_order_item_meta_start', [ $this, 'order_item_meta_start' ], 10, 2 );
			}

			// Admin order
			add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
			add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_itemmeta' ], 10, 2 );

			// Add settings link
			add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
			add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

			// Loop add-to-cart
			add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'loop_add_to_cart_link' ], 10, 2 );

			// Calculate price
			add_action( 'woocommerce_before_mini_cart_contents', [ $this, 'before_mini_cart_contents' ], 9999 );
			add_action( 'woocommerce_before_calculate_totals', [ $this, 'before_calculate_totals' ], 9999 );

			// Shipping
			add_filter( 'woocommerce_cart_shipping_packages', [ $this, 'cart_shipping_packages' ] );

			// Price html
			add_filter( 'woocommerce_get_price_html', [ $this, 'get_price_html' ], 99, 2 );

			// Price class
			add_filter( 'woocommerce_product_price_class', [ $this, 'product_price_class' ] );

			// Order again
			add_filter( 'woocommerce_order_again_cart_item_data', [ $this, 'order_again_cart_item_data' ], 10, 2 );
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'cart_loaded_from_session' ] );

			// Coupons
			add_filter( 'woocommerce_coupon_is_valid_for_product', [ $this, 'coupon_is_valid_for_product' ], 10, 4 );

			// Export
			add_filter( 'woocommerce_product_export_meta_value', [ $this, 'export_process' ], 10, 3 );

			// Import
			add_filter( 'woocommerce_product_import_pre_insert_product_object', [ $this, 'import_process' ], 10, 2 );

			// WPC Smart Messages
			add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );
		}

		function init() {
			// image size
			self::$image_size = apply_filters( 'wooco_image_size', self::$image_size );
		}

		public static function get_settings() {
			return apply_filters( 'wooco_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( '_wooco_' . $name, $default );
			}

			return apply_filters( 'wooco_get_setting', $setting, $name, $default );
		}

		function component( $active = false, $component = [], $key = null ) {
			if ( ! $key ) {
				$key = self::generate_key();
			}

			$component_default = [
				'name'       => '',
				'desc'       => '',
				'type'       => '',
				'products'   => [],
				'other'      => [],
				'orderby'    => 'default',
				'order'      => 'default',
				'exclude'    => [],
				'default'    => '',
				'optional'   => 'yes',
				'multiple'   => 'no',
				'qty'        => 1,
				'custom_qty' => 'no',
				'price'      => '',
				'min'        => 0,
				'max'        => 1000,
				'm_min'      => 0,
				'm_max'      => 1000,
				'selector'   => 'default',
			];

			if ( ! empty( $component ) ) {
				$component = array_merge( $component_default, $component );
			} else {
				$component = $component_default;
			}

			if ( class_exists( 'WPCleverWoopq' ) && ( get_option( '_woopq_decimal', 'no' ) === 'yes' ) ) {
				$step = '0.000001';
			} else {
				$step             = '1';
				$component['qty'] = (int) $component['qty'];
				$component['min'] = (int) $component['min'];
				$component['max'] = (int) $component['max'];
			}
			?>
            <tr class="wooco_component">
                <td>
                    <div class="wooco_component_inner <?php echo esc_attr( $active ? 'active' : '' ); ?>">
                        <div class="wooco_component_heading">
                            <span class="wooco_move_component"></span>
                            <span class="wooco_component_name"><?php echo esc_html( wp_strip_all_tags( $component['name'] ) ); ?></span>
                            <a class="wooco_duplicate_component" href="#"><?php esc_html_e( 'duplicate', 'wpc-composite-products' ); ?></a>
                            <a class="wooco_remove_component" href="#"><?php esc_html_e( 'remove', 'wpc-composite-products' ); ?></a>
                        </div>
                        <div class="wooco_component_content">
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Name', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][name]' ); ?>" type="text" class="wooco_component_name_val" value="<?php echo esc_attr( $component['name'] ); ?>" placeholder="<?php esc_attr_e( 'Name', 'wpc-composite-products' ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Description', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <textarea class="wooco_component_desc_val" name="<?php echo esc_attr( 'wooco_components[' . $key . '][desc]' ); ?>" placeholder="<?php esc_attr_e( 'Description', 'wpc-composite-products' ); ?>"><?php echo esc_textarea( $component['desc'] ); ?></textarea>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Source', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][type]' ); ?>" class="wooco_component_type wooco_component_type_val">
                                            <option value=""><?php esc_html_e( 'Select source', 'wpc-composite-products' ); ?></option>
                                            <option value="products" <?php selected( $component['type'], 'products' ); ?>><?php esc_html_e( 'Products', 'wpc-composite-products' ); ?></option>
											<?php
											$taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

											foreach ( $taxonomies as $taxonomy ) {
												echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $component['type'], $taxonomy->name, false ) . ' disabled>' . esc_html( $taxonomy->label ) . '</option>';
											}
											?>
                                        </select> </label>
                                    <span><?php esc_html_e( 'Order by', 'wpc-composite-products' ); ?> <label>
<select name="<?php echo esc_attr( 'wooco_components[' . $key . '][orderby]' ); ?>" class="wooco_component_orderby_val">
                    <option value="default" <?php selected( $component['orderby'], 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-composite-products' ); ?></option>
                    <option value="none" <?php selected( $component['orderby'], 'none' ); ?>><?php esc_html_e( 'None', 'wpc-composite-products' ); ?></option>
                    <option value="ID" <?php selected( $component['orderby'], 'ID' ); ?>><?php esc_html_e( 'ID', 'wpc-composite-products' ); ?></option>
                    <option value="title" <?php selected( $component['orderby'], 'title' ); ?>><?php esc_html_e( 'Name', 'wpc-composite-products' ); ?></option>
                    <option value="type" <?php selected( $component['orderby'], 'type' ); ?>><?php esc_html_e( 'Type', 'wpc-composite-products' ); ?></option>
                    <option value="rand" <?php selected( $component['orderby'], 'rand' ); ?>><?php esc_html_e( 'Rand', 'wpc-composite-products' ); ?></option>
                    <option value="date" <?php selected( $component['orderby'], 'date' ); ?>><?php esc_html_e( 'Date', 'wpc-composite-products' ); ?></option>
                    <option value="price" <?php selected( $component['orderby'], 'price' ); ?>><?php esc_html_e( 'Price', 'wpc-composite-products' ); ?></option>
                    <option value="modified" <?php selected( $component['orderby'], 'modified' ); ?>><?php esc_html_e( 'Modified', 'wpc-composite-products' ); ?></option>
                </select>
</label></span> &nbsp; <span><?php esc_html_e( 'Order', 'wpc-composite-products' ); ?> <label>
<select name="<?php echo esc_attr( 'wooco_components[' . $key . '][order]' ); ?>" class="wooco_component_order_val">
                    <option value="default" <?php selected( $component['order'], 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-composite-products' ); ?></option>
                    <option value="DESC" <?php selected( $component['order'], 'DESC' ); ?>><?php esc_html_e( 'DESC', 'wpc-composite-products' ); ?></option>
                    <option value="ASC" <?php selected( $component['order'], 'ASC' ); ?>><?php esc_html_e( 'ASC', 'wpc-composite-products' ); ?></option>
                    </select>
</label></span>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_hide wooco_show_if_other">
                                <div class="wooco_component_content_line_label wooco_component_type_label">
									<?php esc_html_e( 'Terms', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
									<?php
									if ( ! is_array( $component['other'] ) ) {
										// old versions before 6.0
										$other = array_map( 'trim', explode( ',', $component['other'] ) );
									} else {
										$other = $component['other'];
									}
									?>
                                    <label>
                                        <select class="wooco_terms wooco_component_other_val" multiple="multiple" name="<?php echo esc_attr( 'wooco_components[' . $key . '][other][]' ); ?>" data-<?php echo esc_attr( $component['type'] ); ?>="<?php echo esc_attr( implode( ',', $other ) ); ?>">
											<?php
											if ( ! empty( $other ) ) {
												foreach ( $other as $t ) {
													if ( $term = get_term_by( 'slug', $t, $component['type'] ) ) {
														echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
													}
												}
											}
											?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_hide wooco_show_if_products">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Products', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select class="wooco_products wooco_component_products_val" data-allow_clear="false" style="width: 100%;" data-sortable="1" multiple="multiple" name="<?php echo esc_attr( 'wooco_components[' . $key . '][products][]' ); ?>" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-composite-products' ); ?>">
											<?php
											if ( ! is_array( $component['products'] ) ) {
												// old versions before 6.0
												$_product_ids = explode( ',', $component['products'] );
											} else {
												$_product_ids = $component['products'];
											}

											if ( ! empty( $_product_ids ) ) {
												foreach ( $_product_ids as $_product_id ) {
													if ( ! empty( $_product_id ) ) {
														if ( is_numeric( $_product_id ) ) {
															// id
															$_product = wc_get_product( $_product_id );
														} else {
															// sku
															$_product = wc_get_product( wc_get_product_id_by_sku( $_product_id ) );
														}

														if ( $_product ) {
															echo '<option value="' . esc_attr( $_product_id ) . '" selected="selected">' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
														}
													}
												}
											}
											?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_show wooco_hide_if_products">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Exclude', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select class="wooco_products wooco_component_exclude_val" data-allow_clear="false" style="width: 100%;" data-sortable="1" multiple="multiple" name="<?php echo esc_attr( 'wooco_components[' . $key . '][exclude][]' ); ?>" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-composite-products' ); ?>">
											<?php
											if ( ! is_array( $component['exclude'] ) ) {
												// old versions before 6.0
												$_product_ids = explode( ',', $component['exclude'] );
											} else {
												$_product_ids = $component['exclude'];
											}

											if ( ! empty( $_product_ids ) ) {
												foreach ( $_product_ids as $_product_id ) {
													if ( ! empty( $_product_id ) ) {
														if ( is_numeric( $_product_id ) ) {
															// id
															$_product = wc_get_product( $_product_id );
														} else {
															// sku
															$_product = wc_get_product( wc_get_product_id_by_sku( $_product_id ) );
														}

														if ( $_product ) {
															echo '<option value="' . esc_attr( $_product_id ) . '" selected="selected">' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
														}
													}
												}
											}
											?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Default option', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select class="wooco_products" style="width: 100%;" data-allow_clear="true" name="<?php echo esc_attr( 'wooco_components[' . $key . '][default]' ); ?>" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-composite-products' ); ?>">
											<?php
											if ( ! empty( $component['default'] ) ) {
												if ( is_numeric( $component['default'] ) ) {
													// id
													$product_default = wc_get_product( $component['default'] );
												} else {
													// sku
													$product_default = wc_get_product( wc_get_product_id_by_sku( $component['default'] ) );
												}

												if ( $product_default ) {
													echo '<option value="' . esc_attr( $component['default'] ) . '" selected="selected">' . wp_kses_post( $product_default->get_formatted_name() ) . '</option>';
												}
											}
											?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Required', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][optional]' ); ?>" class="wooco_component_optional_val">
                                            <option value="no" <?php selected( $component['optional'], 'no' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                            <option value="yes" <?php selected( $component['optional'], 'yes' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'New price', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][price]' ); ?>" class="wooco_component_price_val" type="text" style="width: 60px; display: inline-block" value="<?php echo esc_attr( self::format_price( $component['price'] ) ); ?>"/>
                                    </label>
                                    <span class="woocommerce-help-tip" data-tip="<?php esc_html_e( 'Set a new price using a number (eg. "49" for $49) or a percentage (eg. "90%" of the original price).', 'wpc-composite-products' ); ?>"></span>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Quantity', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][qty]' ); ?>" class="wooco_component_qty_val" type="number" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $component['qty'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Custom quantity', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][custom_qty]' ); ?>" class="wooco_component_custom_qty_val">
                                            <option value="no" <?php selected( $component['custom_qty'], 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            <option value="yes" <?php selected( $component['custom_qty'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_show_if_custom_qty">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Each item\'s quantity limit', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
									<?php esc_html_e( 'Min', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][min]' ); ?>" class="wooco_component_min_val" type="number" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $component['min'] ); ?>"/>
                                    </label>
									<?php esc_html_e( 'Max', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][max]' ); ?>" class="wooco_component_max_val" type="number" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $component['max'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_show_if_custom_qty">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Whole component\'s quantity limit', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
									<?php esc_html_e( 'Min', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][m_min]' ); ?>" class="wooco_component_m_min_val" type="number" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $component['m_min'] ); ?>"/>
                                    </label>
									<?php esc_html_e( 'Max', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][m_max]' ); ?>" class="wooco_component_m_max_val" type="number" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $component['m_max'] ); ?>"/>
                                    </label>
                                    <span class="woocommerce-help-tip" data-tip="<?php esc_html_e( 'For multiple selection only.', 'wpc-composite-products' ); ?>"></span>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Multiple selection', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][multiple]' ); ?>" class="wooco_component_multiple_val">
                                            <option value="no" <?php selected( $component['multiple'], 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            <option value="yes" <?php selected( $component['multiple'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
									<?php esc_html_e( 'Selector interface', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][selector]' ); ?>" class="wooco_component_selector_val">
                                            <option value="default" <?php selected( $component['selector'], 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-composite-products' ); ?></option>
                                            <option value="list" <?php selected( $component['selector'], 'list' ); ?>><?php esc_html_e( 'List', 'wpc-composite-products' ); ?></option>
                                            <option value="grid_2" <?php selected( $component['selector'], 'grid_2' ); ?>><?php esc_html_e( 'Grid - 2 columns', 'wpc-composite-products' ); ?></option>
                                            <option value="grid_3" <?php selected( $component['selector'], 'grid_3' ); ?>><?php esc_html_e( 'Grid - 3 columns', 'wpc-composite-products' ); ?></option>
                                            <option value="grid_4" <?php selected( $component['selector'], 'grid_4' ); ?>><?php esc_html_e( 'Grid - 4 columns', 'wpc-composite-products' ); ?></option>
                                        </select> </label>
                                    <span class="woocommerce-help-tip" data-tip="<?php esc_html_e( 'Specify selector interface for this component. If not, the default selector interface will be used.', 'wpc-composite-products' ); ?>"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
		<?php }

		function ajax_add_component() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
				die( 'Permissions check failed!' );
			}

			$component = [];
			$form_data = isset( $_POST['form_data'] ) ? sanitize_post( $_POST['form_data'] ) : '';

			if ( ! empty( $form_data ) ) {
				$components = [];
				parse_str( $form_data, $components );

				if ( isset( $components['wooco_components'] ) && is_array( $components['wooco_components'] ) ) {
					$component = reset( $components['wooco_components'] );
				}
			}

			self::component( true, $component );
			wp_die();
		}

		function ajax_save_components() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
				die( 'Permissions check failed!' );
			}

			if ( ! isset( $_POST['pid'] ) || ! current_user_can( 'edit_post', absint( sanitize_text_field( $_POST['pid'] ) ) ) ) {
				die( 'Permissions check failed!' );
			}

			$pid       = absint( sanitize_text_field( $_POST['pid'] ) );
			$form_data = isset( $_POST['form_data'] ) ? sanitize_post( $_POST['form_data'] ) : '';

			if ( $pid && $form_data ) {
				$components = [];
				parse_str( $form_data, $components );

				if ( isset( $components['wooco_components'] ) ) {
					update_post_meta( $pid, 'wooco_components', self::sanitize_array( $components['wooco_components'] ) );
				}
			}

			wp_die();
		}

		function ajax_export_components() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
				die( 'Permissions check failed!' );
			}

			$product_id = absint( $_POST['pid'] ?? 0 );
			$components = get_post_meta( $product_id, 'wooco_components', true );
			echo '<textarea style="width: 100%; height: 200px">' . esc_textarea( ! empty( $components ) ? serialize( $components ) : '' ) . '</textarea>';
			echo '<div>' . esc_html__( 'You can copy this field and use it for a CSV import file.', 'wpc-composite-products' ) . '</div>';

			wp_die();
		}

		function ajax_search_term() {
			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wooco_nonce' ) ) {
				die( 'Permissions check failed!' );
			}

			$return = [];

			$args = [
				'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
				'orderby'    => 'id',
				'order'      => 'ASC',
				'hide_empty' => false,
				'fields'     => 'all',
				'name__like' => sanitize_text_field( $_REQUEST['term'] ),
			];

			$terms = get_terms( $args );

			if ( is_array( $terms ) && count( $terms ) ) {
				foreach ( $terms as $term ) {
					$return[] = [ $term->slug, $term->name ];
				}
			}

			wp_send_json( $return );
		}

		function ajax_load_gallery() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
				die( 'Permissions check failed!' );
			}

			if ( empty( $_POST['ids'] ) || ! is_array( $_POST['ids'] ) ) {
				wp_send_json_error();
			}

			$main_product_id = absint( $_POST['product_id'] ?? 0 );
			$key             = sanitize_text_field( $_POST['key'] ?? '' );
			$image_ids       = [];

			foreach ( $_POST['ids'] as $id ) {
				$id_arr = explode( '/', $id );
				$_id    = absint( $id_arr[0] ?? 0 );

				if ( $_id && ( $_product = wc_get_product( $_id ) ) ) {
					if ( $_product_image = $_product->get_image_id() ) {
						$image_ids[] = $_product_image;
					}

					if ( is_a( $_product, 'WC_Product_Variation' ) ) {
						if ( apply_filters( 'wooco_gallery_include_product_images', false, $_product ) ) {
							// get images from WPC Additional Variation Images
							$_images = array_filter( explode( ',', get_post_meta( $_id, 'wpcvi_images', true ) ) );

							if ( ! empty( $_images ) ) {
								$image_ids = array_merge( $image_ids, $_images );
							}
						}

						if ( apply_filters( 'wooco_gallery_include_variable_featured', false, $_product ) ) {
							// get featured from parent variable product
							$_parent_id = $_product->get_parent_id();

							if ( $_parent_id && ( $_parent = wc_get_product( $_parent_id ) ) && ( $_parent_image = $_parent->get_image_id() ) ) {
								$image_ids[] = $_parent_image;
							}
						}

						if ( apply_filters( 'wooco_gallery_include_variable_images', false, $_product ) ) {
							// get images from parent variable product
							$_parent_id = $_product->get_parent_id();

							if ( $_parent_id && ( $_parent = wc_get_product( $_parent_id ) ) && ( $_parent_images = $_parent->get_gallery_image_ids() ) ) {
								if ( ! empty( $_parent_images ) && is_array( $_parent_images ) ) {
									$image_ids = array_merge( $image_ids, $_parent_images );
								}
							}
						}
					} else {
						if ( apply_filters( 'wooco_gallery_include_product_images', false, $_product ) ) {
							$_images = $_product->get_gallery_image_ids();

							if ( ! empty( $_images ) && is_array( $_images ) ) {
								$image_ids = array_merge( $image_ids, $_images );
							}
						}
					}
				}
			}

			$include_main_images   = apply_filters( 'wooco_gallery_include_main_images', false, $main_product_id );
			$include_main_featured = apply_filters( 'wooco_gallery_include_main_featured', false, $main_product_id );

			if ( ( $include_main_images || $include_main_featured ) && ( $main_product = wc_get_product( $main_product_id ) ) ) {
				if ( $include_main_images ) {
					$main_images = $main_product->get_gallery_image_ids();

					if ( ! empty( $main_images ) && is_array( $main_images ) ) {
						$image_ids = array_merge( $main_images, $image_ids );
					}
				}

				if ( $include_main_featured ) {
					$main_featured = $main_product->get_image_id();

					if ( ! empty( $main_featured ) ) {
						array_unshift( $image_ids, $main_featured );
					}
				}
			}

			// remove duplicated images
			$image_ids = array_unique( $image_ids );

			if ( empty( $image_ids ) ) {
				wp_send_json_error();
			}

			$gallery_html = '<div class="woocommerce-product-gallery woocommerce-product-gallery--wooco woocommerce-product-gallery--wooco-' . esc_attr( $key ) . ' woocommerce-product-gallery--wooco-' . absint( $main_product_id ) . ' woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-' . esc_attr( apply_filters( 'woocommerce_product_thumbnails_columns', 4 ) ) . ' images" data-columns="' . esc_attr( apply_filters( 'woocommerce_product_thumbnails_columns', 4 ) ) . '" style="opacity: 0; transition: opacity .25s ease-in-out;">';
			$gallery_html .= apply_filters( 'wooco_gallery_before', '', $image_ids, $main_product_id );
			$gallery_html .= '<figure class="woocommerce-product-gallery__wrapper">';

			foreach ( $image_ids as $id ) {
				$gallery_html .= apply_filters( 'woocommerce_single_product_image_thumbnail_html', wc_get_gallery_image_html( $id ), $id );
			}

			$gallery_html .= '</figure>';
			$gallery_html .= apply_filters( 'wooco_gallery_after', '', $image_ids, $main_product_id );
			$gallery_html .= '</div>';

			wp_send_json( [ 'gallery' => apply_filters( 'wooco_gallery', $gallery_html, $image_ids, $main_product_id ) ] );
		}

		function ajax_search_product() {
			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wooco_nonce' ) ) {
				die( 'Permissions check failed!' );
			}

			if ( isset( $_REQUEST['term'] ) ) {
				$term = (string) wc_clean( wp_unslash( $_REQUEST['term'] ) );
			}

			if ( empty( $term ) ) {
				wp_die();
			}

			$products   = [];
			$limit      = absint( apply_filters( 'wooco_json_search_limit', 30 ) );
			$data_store = WC_Data_Store::load( 'product' );
			$ids        = $data_store->search_products( $term, '', true, false, $limit );

			foreach ( $ids as $id ) {
				$product_object = wc_get_product( $id );

				if ( ! wc_products_array_filter_readable( $product_object ) ) {
					continue;
				}

				$formatted_name = $product_object->get_formatted_name();

				if ( apply_filters( 'wooco_use_sku', false ) ) {
					$products[] = [
						$product_object->get_sku() ?: $product_object->get_id(),
						rawurldecode( wp_strip_all_tags( $formatted_name ) )
					];
				} else {
					$products[] = [
						$product_object->get_id(),
						rawurldecode( wp_strip_all_tags( $formatted_name ) )
					];
				}
			}

			wp_send_json( apply_filters( 'wooco_json_search_found_products', $products ) );
		}

		function register_settings() {
			// settings
			register_setting( 'wooco_settings', 'wooco_settings' );

			// localization
			register_setting( 'wooco_localization', 'wooco_localization' );
		}

		function admin_menu() {
			add_submenu_page( 'wpclever', esc_html__( 'WPC Composite Products', 'wpc-composite-products' ), esc_html__( 'Composite Products', 'wpc-composite-products' ), 'manage_options', 'wpclever-wooco', [
				$this,
				'admin_menu_content'
			] );
		}

		function admin_menu_content() {
			add_thickbox();
			$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
			?>
            <div class="wpclever_settings_page wrap">
                <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Composite Products', 'wpc-composite-products' ) . ' ' . esc_html( WOOCO_VERSION ) . ' ' . ( defined( 'WOOCO_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-composite-products' ) . '</span>' : '' ); ?></h1>
                <div class="wpclever_settings_page_desc about-text">
                    <p>
						<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-composite-products' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                        <br/>
                        <a href="<?php echo esc_url( WOOCO_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-composite-products' ); ?></a> |
                        <a href="<?php echo esc_url( WOOCO_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-composite-products' ); ?></a> |
                        <a href="<?php echo esc_url( WOOCO_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-composite-products' ); ?></a>
                    </p>
                </div>
				<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-composite-products' ); ?></p>
                    </div>
				<?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=how' ) ); ?>" class="<?php echo $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
							<?php esc_html_e( 'How to use?', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=settings' ) ); ?>" class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
							<?php esc_html_e( 'Settings', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=localization' ) ); ?>" class="<?php echo $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
							<?php esc_html_e( 'Localization', 'wpc-composite-products' ); ?>
                        </a> <a href="<?php echo esc_url( WOOCO_DOCS ); ?>" class="nav-tab" target="_blank">
							<?php esc_html_e( 'Docs', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=premium' ) ); ?>" class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>" style="color: #c9356e">
							<?php esc_html_e( 'Premium Version', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
							<?php esc_html_e( 'Essential Kit', 'wpc-composite-products' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
					<?php if ( $active_tab === 'how' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
								<?php esc_html_e( 'When creating the product, please choose product data is "Smart composite" then you can see the search field to start search and add component products.', 'wpc-composite-products' ); ?>
                            </p>
                            <p>
                                <img src="<?php echo esc_url( WOOCO_URI . 'assets/images/how-01.jpg' ); ?>" alt=""/>
                            </p>
                        </div>
						<?php
					} elseif ( $active_tab === 'settings' ) {
						$price_format             = self::get_setting( 'price_format', 'from_regular' );
						$product_price            = self::get_setting( 'product_price', 'sale_price' );
						$selector                 = self::get_setting( 'selector', 'ddslick' );
						$exclude_hidden           = self::get_setting( 'exclude_hidden', 'no' );
						$exclude_unpurchasable    = self::get_setting( 'exclude_unpurchasable', 'yes' );
						$show_alert               = self::get_setting( 'show_alert', 'load' );
						$show_qty                 = self::get_setting( 'show_qty', 'yes' );
						$show_image               = self::get_setting( 'show_image', 'yes' );
						$show_price               = self::get_setting( 'show_price', 'yes' );
						$show_availability        = self::get_setting( 'show_availability', 'yes' );
						$show_short_description   = self::get_setting( 'show_short_description', 'no' );
						$option_none_image        = self::get_setting( 'option_none_image', 'placeholder' );
						$option_none_image_id     = self::get_setting( 'option_none_image_id', '' );
						$option_none_required     = self::get_setting( 'option_none_required', 'no' );
						$checkbox                 = self::get_setting( 'checkbox', 'no' );
						$checked                  = self::get_setting( 'checked', 'yes' );
						$change_image             = self::get_setting( 'change_image', 'yes' );
						$change_price             = self::get_setting( 'change_price', 'yes' );
						$change_price_custom      = self::get_setting( 'change_price_custom', '.summary > .price' );
						$product_link             = self::get_setting( 'product_link', 'no' );
						$coupon_restrictions      = self::get_setting( 'coupon_restrictions', 'no' );
						$cart_contents_count      = self::get_setting( 'cart_contents_count', 'composite' );
						$hide_composite_name      = self::get_setting( 'hide_composite_name', 'no' );
						$hide_component_name      = self::get_setting( 'hide_component_name', 'yes' );
						$hide_component           = self::get_setting( 'hide_component', 'no' );
						$hide_component_mini_cart = self::get_setting( 'hide_component_mini_cart', 'no' );
						$hide_component_order     = self::get_setting( 'hide_component_order', 'no' );
						?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th colspan="2">
										<?php esc_html_e( 'General', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Price format', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[price_format]">
                                                <option value="from_regular" <?php selected( $price_format, 'from_regular' ); ?>><?php esc_html_e( 'From regular price', 'wpc-composite-products' ); ?></option>
                                                <option value="from_sale" <?php selected( $price_format, 'from_sale' ); ?>><?php esc_html_e( 'From sale price', 'wpc-composite-products' ); ?></option>
                                                <option value="normal" <?php selected( $price_format, 'normal' ); ?>><?php esc_html_e( 'Regular and sale price', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Choose a price format for composites on the archive page.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Calculate product prices', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[product_price]">
                                                <option value="sale_price" <?php selected( $product_price, 'sale_price' ); ?>><?php esc_html_e( 'from Sale price', 'wpc-composite-products' ); ?></option>
                                                <option value="regular_price" <?php selected( $product_price, 'regular_price' ); ?>><?php esc_html_e( 'from Regular price', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Component product pricing methods: from Sale price (default) or Regular price.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Selector interface', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[selector]">
                                                <option value="list" <?php selected( $selector, 'list' ); ?>><?php esc_html_e( 'List', 'wpc-composite-products' ); ?></option>
                                                <option value="grid_2" <?php selected( $selector, 'grid_2' ); ?>><?php esc_html_e( 'Grid - 2 columns', 'wpc-composite-products' ); ?></option>
                                                <option value="grid_3" <?php selected( $selector, 'grid_3' ); ?>><?php esc_html_e( 'Grid - 3 columns', 'wpc-composite-products' ); ?></option>
                                                <option value="grid_4" <?php selected( $selector, 'grid_4' ); ?>><?php esc_html_e( 'Grid - 4 columns', 'wpc-composite-products' ); ?></option>
                                                <option value="ddslick" <?php selected( $selector, 'ddslick' ); ?>><?php esc_html_e( 'Dropdown - ddSlick', 'wpc-composite-products' ); ?></option>
                                                <option value="select2" <?php selected( $selector, 'select2' ); ?>><?php esc_html_e( 'Dropdown - Select2', 'wpc-composite-products' ); ?></option>
                                                <option value="select" <?php selected( $selector, 'select' ); ?>><?php esc_html_e( 'Dropdown - HTML select tag', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description">Read more about <a href="https://designwithpc.com/Plugins/ddSlick" target="_blank">ddSlick</a>, <a href="https://select2.org/" target="_blank">Select2</a> and <a href="https://www.w3schools.com/tags/tag_select.asp" target="_blank">HTML select tag</a></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exclude hidden', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[exclude_hidden]">
                                                <option value="yes" <?php selected( $exclude_hidden, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $exclude_hidden, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Exclude hidden products from the list.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exclude unpurchasable', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[exclude_unpurchasable]">
                                                <option value="yes" <?php selected( $exclude_unpurchasable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $exclude_unpurchasable, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Exclude unpurchasable products from the list.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show alert', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_alert]">
                                                <option value="load" <?php selected( $show_alert, 'load' ); ?>><?php esc_html_e( 'On composite loaded', 'wpc-composite-products' ); ?></option>
                                                <option value="change" <?php selected( $show_alert, 'change' ); ?>><?php esc_html_e( 'On composite changing', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $show_alert, 'no' ); ?>><?php esc_html_e( 'No, always hide the alert', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show the inline alert under the components.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show quantity', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_qty]">
                                                <option value="yes" <?php selected( $show_qty, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $show_qty, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show the quantity number before component product name.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show image', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_image]">
                                                <option value="yes" <?php selected( $show_image, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $show_image, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show price', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_price]">
                                                <option value="yes" <?php selected( $show_price, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $show_price, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show availability', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_availability]">
                                                <option value="yes" <?php selected( $show_availability, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $show_availability, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show short description', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_short_description]">
                                                <option value="yes" <?php selected( $show_short_description, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $show_short_description, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Component selector', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[checkbox]" class="wooco_settings_checkbox">
                                                <option value="yes" <?php selected( $checkbox, 'yes' ); ?>><?php esc_html_e( 'Checkbox', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $checkbox, 'no' ); ?>><?php esc_html_e( 'Option none', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Use checkbox or Option none for the dropdown selector.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="wooco_settings_checkbox_hide wooco_settings_checkbox_show_yes">
                                    <th><?php esc_html_e( 'Checked by default', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[checked]">
                                                <option value="yes" <?php selected( $checked, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $checked, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Mark checkboxes as checked by default.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="wooco_settings_checkbox_hide wooco_settings_checkbox_show_no">
                                    <th><?php esc_html_e( 'Show "Option none" for required component', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[option_none_required]">
                                                <option value="yes" <?php selected( $option_none_required, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $option_none_required, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="wooco_settings_checkbox_hide wooco_settings_checkbox_show_no">
                                    <th><?php esc_html_e( '"Option none" image', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="wooco_settings[option_none_image]" class="wooco_option_none_image">
                                                <option value="placeholder" <?php selected( $option_none_image, 'placeholder' ); ?>><?php esc_html_e( 'Placeholder image', 'wpc-composite-products' ); ?></option>
                                                <option value="product" <?php selected( $option_none_image, 'product' ); ?>><?php esc_html_e( 'Main product\'s image', 'wpc-composite-products' ); ?></option>
                                                <option value="custom" <?php selected( $option_none_image, 'custom' ); ?>><?php esc_html_e( 'Custom image', 'wpc-composite-products' ); ?></option>
                                                <option value="none" <?php selected( $option_none_image, 'none' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'If you choose "Placeholder image", you can change it in WooCommerce > Settings > Products > Placeholder image.', 'wpc-composite-products' ); ?></span>
                                        <div class="wooco_option_none_image_custom" style="display: none">
											<?php wp_enqueue_media(); ?>
                                            <span class="wooco_option_none_image_preview" id="wooco_option_none_image_preview">
                                                        <?php if ( $option_none_image_id ) {
	                                                        echo '<img src="' . esc_url( wp_get_attachment_url( $option_none_image_id ) ) . '"/>';
                                                        } ?>
                                                    </span>
                                            <input id="wooco_option_none_image_upload" type="button" class="button" value="<?php esc_attr_e( 'Upload image', 'wpc-composite-products' ); ?>"/>
                                            <input type="hidden" name="wooco_settings[option_none_image_id]" id="wooco_option_none_image_id" value="<?php echo esc_attr( $option_none_image_id ); ?>"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change image gallery', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[change_image]">
                                                <option value="yes" <?php selected( $change_image, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $change_image, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Change the main product’s image gallery based on selected products.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change price', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[change_price]" class="wooco_change_price">
                                                <option value="yes" <?php selected( $change_price, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_custom" <?php selected( $change_price, 'yes_custom' ); ?>><?php esc_html_e( 'Yes, custom selector', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $change_price, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label> <label>
                                            <input type="text" name="wooco_settings[change_price_custom]" value="<?php echo esc_attr( $change_price_custom ); ?>" placeholder=".summary > .price" class="wooco_change_price_custom"/>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Change the main product’s price based on the changes in prices of selected variations in a grouped products. This uses Javascript to change the main product’s price to it depends heavily on theme’s HTML. If the price doesn\'t change when this option is enabled, please contact us and we can help you adjust the JS file.', 'wpc-composite-products' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Link to individual product', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[product_link]">
                                                <option value="yes" <?php selected( $product_link, 'yes' ); ?>><?php esc_html_e( 'Yes, open on the same tab', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_blank" <?php selected( $product_link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open on a new tab', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_popup" <?php selected( $product_link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $product_link, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <p class="description"><?php esc_html_e( 'Add a link to the target individual product below this selection.', 'wpc-composite-products' ); ?> If you choose "Open quick view popup", please install
                                            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
                                        </p>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
										<?php esc_html_e( 'Cart & Checkout', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Coupon restrictions', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[coupon_restrictions]">
                                                <option value="no" <?php selected( $coupon_restrictions, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                                <option value="composite" <?php selected( $coupon_restrictions, 'composite' ); ?>><?php esc_html_e( 'Exclude composite', 'wpc-composite-products' ); ?></option>
                                                <option value="component" <?php selected( $coupon_restrictions, 'component' ); ?>><?php esc_html_e( 'Exclude component products', 'wpc-composite-products' ); ?></option>
                                                <option value="both" <?php selected( $coupon_restrictions, 'both' ); ?>><?php esc_html_e( 'Exclude both composite and component products', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Choose products you want to exclude from coupons.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Cart content count', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[cart_contents_count]">
                                                <option value="composite" <?php selected( $cart_contents_count, 'composite' ); ?>><?php esc_html_e( 'Composite only', 'wpc-composite-products' ); ?></option>
                                                <option value="component_products" <?php selected( $cart_contents_count, 'component_products' ); ?>><?php esc_html_e( 'Component products only', 'wpc-composite-products' ); ?></option>
                                                <option value="both" <?php selected( $cart_contents_count, 'both' ); ?>><?php esc_html_e( 'Both composite and component products', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide composite name before component products', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_composite_name]">
                                                <option value="yes" <?php selected( $hide_composite_name, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $hide_composite_name, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component name before component products', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component_name]">
                                                <option value="yes" <?php selected( $hide_component_name, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $hide_component_name, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component products on mini-cart', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component_mini_cart]">
                                                <option value="yes" <?php selected( $hide_component_mini_cart, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $hide_component_mini_cart, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Hide component products, just show the main composite on mini-cart.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component products on cart & checkout page', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component]">
                                                <option value="yes" <?php selected( $hide_component, 'yes' ); ?>><?php esc_html_e( 'Yes, just show the main composite', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_text" <?php selected( $hide_component, 'yes_text' ); ?>><?php esc_html_e( 'Yes, but shortly list component sub-product names under the main composite in one line', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_list" <?php selected( $hide_component, 'yes_list' ); ?>><?php esc_html_e( 'Yes, but list component sub-product names under the main composite in separate lines', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $hide_component, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component products on order details', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component_order]">
                                                <option value="yes" <?php selected( $hide_component_order, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_text" <?php selected( $hide_component_order, 'yes_text' ); ?>><?php esc_html_e( 'Yes, but shortly list component sub-product names under the main composite in one line', 'wpc-composite-products' ); ?></option>
                                                <option value="yes_list" <?php selected( $hide_component_order, 'yes_list' ); ?>><?php esc_html_e( 'Yes, but list component sub-product names under the main composite in separate lines', 'wpc-composite-products' ); ?></option>
                                                <option value="no" <?php selected( $hide_component_order, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-composite-products' ); ?></option>
                                            </select> </label>
                                        <p class="description"><?php esc_html_e( 'Hide component products, just show the main composite on order details (order confirmation or emails).', 'wpc-composite-products' ); ?></p>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
										<?php settings_fields( 'wooco_settings' ); ?><?php submit_button(); ?>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } elseif ( $active_tab === 'localization' ) { ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'General', 'wpc-composite-products' ); ?></th>
                                    <td>
										<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-composite-products' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Option none (optional component)', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[option_none]" value="<?php echo esc_attr( self::localization( 'option_none' ) ); ?>" placeholder="<?php esc_attr_e( 'No, thanks. I don\'t need this', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Text to display for showing a "Don\'t choose any product" option.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Option none (required component)', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[option_none_required]" value="<?php echo esc_attr( self::localization( 'option_none_required' ) ); ?>" placeholder="<?php esc_attr_e( 'Please make your choice here', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Text to display for showing a "Don\'t choose any product" option.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total text', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wooco_localization[total]" class="regular-text" value="<?php echo esc_attr( self::localization( 'total' ) ); ?>" placeholder="<?php esc_attr_e( 'Total price:', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Selected text', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wooco_localization[selected]" class="regular-text" value="<?php echo esc_attr( self::localization( 'selected' ) ); ?>" placeholder="<?php esc_attr_e( 'Selected:', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Saved text', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wooco_localization[saved]" class="regular-text" value="<?php echo esc_attr( self::localization( 'saved' ) ); ?>" placeholder="<?php esc_attr_e( '(saved [d])', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Use [d] to show the saved percentage.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Quantity label', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[qty_label]" value="<?php echo esc_attr( self::localization( 'qty_label' ) ); ?>" placeholder="<?php esc_attr_e( 'Qty:', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
										<?php esc_html_e( '"Add to cart" button labels', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Shop/archive page', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <div style="margin-bottom: 5px">
                                            <label>
                                                <input type="text" class="regular-text" name="wooco_localization[button_select]" value="<?php echo esc_attr( self::localization( 'button_select' ) ); ?>" placeholder="<?php esc_attr_e( 'Select options', 'wpc-composite-products' ); ?>"/>
                                            </label>
                                            <span class="description"><?php esc_html_e( 'For purchasable composites.', 'wpc-composite-products' ); ?></span>
                                        </div>
                                        <div>
                                            <label>
                                                <input type="text" class="regular-text" name="wooco_localization[button_read]" value="<?php echo esc_attr( self::localization( 'button_read' ) ); ?>" placeholder="<?php esc_attr_e( 'Read more', 'wpc-composite-products' ); ?>"/>
                                            </label>
                                            <span class="description"><?php esc_html_e( 'For unpurchasable composites.', 'wpc-composite-products' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Single product page', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[button_single]" value="<?php echo esc_attr( self::localization( 'button_single' ) ); ?>" placeholder="<?php esc_attr_e( 'Add to cart', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
										<?php esc_html_e( 'Cart & Checkout', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Components', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[cart_components]" value="<?php echo esc_attr( self::localization( 'cart_components' ) ); ?>" placeholder="<?php esc_attr_e( 'Components', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php /* translators: components */
										esc_html_e( 'Components: %s', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[cart_components_s]" value="<?php echo esc_attr( self::localization( 'cart_components_s' ) ); ?>" placeholder="<?php /* translators: components */
											esc_attr_e( 'Components: %s', 'wpc-composite-products' ); ?>"/> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php /* translators: composite */
										esc_html_e( 'Composite: %s', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text" name="wooco_localization[cart_composite_s]" value="<?php echo esc_attr( self::localization( 'cart_composite_s' ) ); ?>" placeholder="<?php /* translators: composite */
											esc_attr_e( 'Composite: %s', 'wpc-composite-products' ); ?>"/> </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
										<?php esc_html_e( 'Alert', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Require selection', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_selection]" value="<?php echo esc_attr( self::localization( 'alert_selection' ) ); ?>" placeholder="<?php esc_attr_e( 'Please choose a purchasable product for the component [name] before adding this composite to the cart.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Different selection', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_same]" value="<?php echo esc_attr( self::localization( 'alert_same' ) ); ?>" placeholder="<?php esc_attr_e( 'Please select a different product for each component.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Whole component\'s quantity minimum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_m_min]" value="<?php echo esc_attr( self::localization( 'alert_m_min' ) ); ?>" placeholder="<?php esc_attr_e( 'Please choose at least a total quantity of [min] products for the component [name].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Whole component\'s quantity maximum reached', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_m_max]" value="<?php echo esc_attr( self::localization( 'alert_m_max' ) ); ?>" placeholder="<?php esc_attr_e( 'Sorry, you can only choose at max a total quantity of [max] products for the component [name].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Minimum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_min]" value="<?php echo esc_attr( self::localization( 'alert_min' ) ); ?>" placeholder="<?php esc_attr_e( 'Please choose at least a total quantity of [min] products before adding this composite to the cart.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Maximum reached', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_max]" value="<?php echo esc_attr( self::localization( 'alert_max' ) ); ?>" placeholder="<?php esc_attr_e( 'Sorry, you can only choose at max a total quantity of [max] products before adding this composite to the cart.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total minimum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_total_min]" value="<?php echo esc_attr( self::localization( 'alert_total_min' ) ); ?>" placeholder="<?php esc_attr_e( 'The total must meet the minimum amount of [min].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total maximum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_total_max]" value="<?php echo esc_attr( self::localization( 'alert_total_max' ) ); ?>" placeholder="<?php esc_attr_e( 'The total must meet the maximum amount of [max].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
										<?php settings_fields( 'wooco_localization' ); ?><?php submit_button(); ?>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } elseif ( $active_tab == 'tools' ) { ?>
                        <table class="form-table">
                            <tr class="heading">
                                <th scope="row"><?php esc_html_e( 'Data Migration', 'wpc-composite-products' ); ?></th>
                                <td>
									<?php esc_html_e( 'If selected products don\'t appear on the current version. Please try running Migrate tool.', 'wpc-composite-products' ); ?>

									<?php
									echo '<p>';
									$num   = absint( $_GET['num'] ?? 50 );
									$paged = absint( $_GET['paged'] ?? 1 );

									if ( isset( $_GET['act'] ) && ( $_GET['act'] === 'migrate' ) ) {
										$args = [
											'post_type'      => 'product',
											'posts_per_page' => $num,
											'paged'          => $paged,
											'meta_query'     => [
												[
													'key'     => 'wooco_components',
													'compare' => 'EXISTS'
												]
											]
										];

										$posts = get_posts( $args );

										if ( ! empty( $posts ) ) {
											foreach ( $posts as $post ) {
												$components = get_post_meta( $post->ID, 'wooco_components', true );

												if ( is_array( $components ) && ! empty( $components ) ) {
													$new_components = [];

													foreach ( $components as $component ) {
														if ( ! empty( $component['products'] ) && is_string( $component['products'] ) ) {
															$component['products'] = explode( ',', $component['products'] );
														}

														if ( ( $component['type'] === 'categories' ) && ! empty( $component['categories'] ) ) {
															$component['type'] = 'product_cat';

															if ( is_string( $component['categories'] ) ) {
																$component['other'] = explode( ',', $component['categories'] );
															} else {
																$component['other'] = $component['categories'];
															}

															foreach ( $component['other'] as $k => $c ) {
																$component['other'][ $k ] = self::get_term_slug( $c, 'product_cat' );
															}

															unset( $component['categories'] );
														}

														if ( ( $component['type'] === 'tags' ) && ! empty( $component['tags'] ) ) {
															$component['type'] = 'product_tag';

															if ( is_string( $component['tags'] ) ) {
																$component['other'] = explode( ',', $component['tags'] );
															} else {
																$component['other'] = $component['tags'];
															}

															foreach ( $component['other'] as $k => $t ) {
																$component['other'][ $k ] = self::get_term_slug( $t, 'product_tag' );
															}

															unset( $component['tags'] );
														}

														$new_key                    = self::generate_key();
														$new_components[ $new_key ] = $component;
													}

													update_post_meta( $post->ID, 'wooco_components', $new_components );
												}
											}

											echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Migrating...', 'wpc-composite-products' ) . '</span>';
											echo '<p class="description">' . esc_html__( 'Please wait until it has finished!', 'wpc-composite-products' ) . '</p>';
											?>
                                            <script type="text/javascript">
                                              (function($) {
                                                $(function() {
                                                  setTimeout(function() {
                                                    window.location.href = '<?php echo esc_url_raw( admin_url( 'admin.php?page=wpclever-wooco&tab=tools&act=migrate&num=' . $num . '&paged=' . ( $paged + 1 ) ) ); ?>';
                                                  }, 1000);
                                                });
                                              })(jQuery);
                                            </script>
										<?php } else {
											echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Finished!', 'wpc-composite-products' ) . '</span>';
										}
									} else {
										echo '<a class="button btn" href="' . esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=tools&act=migrate' ) ) . '">' . esc_html__( 'Migrate', 'wpc-composite-products' ) . '</a>';
									}
									echo '</p>';
									?>
                                </td>
                            </tr>
                        </table>
					<?php } elseif ( $active_tab == 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/composite-products?utm_source=pro&utm_medium=wooco&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/composite-products</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Use Categories, Tags, or Attributes as the source for component options.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
					<?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}

		function get_term_slug( $slug_or_id, $taxonomy ) {
			if ( is_numeric( $slug_or_id ) && ( $term = get_term_by( 'id', $slug_or_id, $taxonomy ) ) ) {
				return $term->slug;
			}

			return $slug_or_id;
		}

		function enqueue_scripts() {
			if ( self::get_setting( 'selector', 'ddslick' ) === 'ddslick' ) {
				wp_enqueue_script( 'ddslick', WOOCO_URI . 'assets/libs/ddslick/jquery.ddslick.min.js', [ 'jquery' ], WOOCO_VERSION, true );
			}

			if ( self::get_setting( 'selector', 'ddslick' ) === 'select2' ) {
				wp_enqueue_style( 'select2' );
				wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', [ 'jquery' ], WOOCO_VERSION, true );
			}

			wp_enqueue_style( 'wooco-frontend', WOOCO_URI . 'assets/css/frontend.css', [], WOOCO_VERSION );
			wp_enqueue_script( 'wooco-frontend', WOOCO_URI . 'assets/js/frontend.js', [
				'jquery',
				'imagesloaded'
			], WOOCO_VERSION, true );
			wp_localize_script( 'wooco-frontend', 'wooco_vars', apply_filters( 'wooco_vars', [
					'wc_ajax_url'              => WC_AJAX::get_endpoint( '%%endpoint%%' ),
					'nonce'                    => wp_create_nonce( 'wooco_nonce' ),
					'price_decimals'           => wc_get_price_decimals(),
					'price_format'             => get_woocommerce_price_format(),
					'price_thousand_separator' => wc_get_price_thousand_separator(),
					'price_decimal_separator'  => wc_get_price_decimal_separator(),
					'currency_symbol'          => get_woocommerce_currency_symbol(),
					'trim_zeros'               => apply_filters( 'woocommerce_price_trim_zeros', false ),
					'quickview_variation'      => apply_filters( 'wooco_quickview_variation', 'default' ),
					'gallery_selector'         => apply_filters( 'wooco_gallery_selector', '.woocommerce-product-gallery' ),
					'main_gallery_selector'    => apply_filters( 'wooco_main_gallery_selector', '.woocommerce-product-gallery:not(.woocommerce-product-gallery--wooco)' ),
					'selector'                 => self::get_setting( 'selector', 'ddslick' ),
					'change_image'             => self::get_setting( 'change_image', 'yes' ),
					'change_price'             => self::get_setting( 'change_price', 'yes' ),
					'price_selector'           => self::get_setting( 'change_price_custom', '' ),
					'product_link'             => self::get_setting( 'product_link', 'no' ),
					'show_alert'               => self::get_setting( 'show_alert', 'load' ),
					'hide_component_name'      => self::get_setting( 'hide_component_name', 'yes' ),
					'total_text'               => self::localization( 'total', esc_html__( 'Total price:', 'wpc-composite-products' ) ),
					'selected_text'            => self::localization( 'selected', esc_html__( 'Selected:', 'wpc-composite-products' ) ),
					'saved_text'               => self::localization( 'saved', esc_html__( '(saved [d])', 'wpc-composite-products' ) ),
					'alert_min'                => self::localization( 'alert_min', esc_html__( 'Please choose at least a total quantity of [min] products before adding this composite to the cart.', 'wpc-composite-products' ) ),
					'alert_max'                => self::localization( 'alert_max', esc_html__( 'Sorry, you can only choose at max a total quantity of [max] products before adding this composite to the cart.', 'wpc-composite-products' ) ),
					'alert_m_min'              => self::localization( 'alert_m_min', esc_html__( 'Please choose at least a total quantity of [min] products for the component [name].', 'wpc-composite-products' ) ),
					'alert_m_max'              => self::localization( 'alert_m_max', esc_html__( 'Sorry, you can only choose at max a total quantity of [max] products for the component [name].', 'wpc-composite-products' ) ),
					'alert_same'               => self::localization( 'alert_same', esc_html__( 'Please select a different product for each component.', 'wpc-composite-products' ) ),
					'alert_selection'          => self::localization( 'alert_selection', esc_html__( 'Please choose a purchasable product for the component [name] before adding this composite to the cart.', 'wpc-composite-products' ) ),
					'alert_total_min'          => self::localization( 'alert_total_min', esc_html__( 'The total must meet the minimum amount of [min].', 'wpc-composite-products' ) ),
					'alert_total_max'          => self::localization( 'alert_total_max', esc_html__( 'The total must meet the maximum amount of [max].', 'wpc-composite-products' ) )
				] )
			);
		}

		function admin_enqueue_scripts( $hook ) {
			if ( apply_filters( 'wooco_ignore_backend_scripts', false, $hook ) ) {
				return null;
			}

			wp_enqueue_style( 'hint', WOOCO_URI . 'assets/css/hint.css' );
			wp_enqueue_style( 'wooco-backend', WOOCO_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WOOCO_VERSION );
			wp_enqueue_script( 'wooco-backend', WOOCO_URI . 'assets/js/backend.js', [
				'jquery',
				'jquery-ui-dialog',
				'jquery-ui-sortable',
				'wc-enhanced-select',
				'selectWoo'
			], WOOCO_VERSION, true );
			wp_localize_script( 'wooco-backend', 'wooco_vars', [
					'nonce' => wp_create_nonce( 'wooco_nonce' )
				]
			);
		}

		function action_links( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WOOCO_FILE );
			}

			if ( $plugin === $file ) {
				$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-composite-products' ) . '</a>';
				$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-composite-products' ) . '</a>';
				array_unshift( $links, $settings );
			}

			return (array) $links;
		}

		function row_meta( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WOOCO_FILE );
			}

			if ( $plugin === $file ) {
				$row_meta = [
					'docs'    => '<a href="' . esc_url( WOOCO_DOCS ) . '" target="_blank">' . esc_html__( 'Docs', 'wpc-composite-products' ) . '</a>',
					'support' => '<a href="' . esc_url( WOOCO_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-composite-products' ) . '</a>',
				];

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		function cart_contents_count( $count ) {
			$cart_contents_count = self::get_setting( 'cart_contents_count', 'composite' );

			if ( $cart_contents_count !== 'both' ) {
				$cart_contents = WC()->cart->cart_contents;

				foreach ( $cart_contents as $cart_item ) {
					if ( ( $cart_contents_count === 'component_products' ) && ! empty( $cart_item['wooco_ids'] ) ) {
						$count -= $cart_item['quantity'];
					}

					if ( ( $cart_contents_count === 'composite' ) && ! empty( $cart_item['wooco_parent_id'] ) ) {
						$count -= $cart_item['quantity'];
					}
				}
			}

			return $count;
		}

		function cart_item_name( $name, $item ) {
			if ( ! empty( $item['wooco_parent_id'] ) ) {
				if ( ( self::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['wooco_component'] ) ) {
					$_name = $item['wooco_component'] . ': ' . $name;
				} else {
					$_name = $name;
				}

				if ( self::get_setting( 'hide_composite_name', 'no' ) === 'no' ) {
					if ( $parent_product = wc_get_product( $item['wooco_parent_id'] ) ) {
						if ( str_contains( $name, '</a>' ) ) {
							$_name = '<a href="' . get_permalink( $item['wooco_parent_id'] ) . '">' . $parent_product->get_name() . '</a>' . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $_name;
						} else {
							$_name = $parent_product->get_name() . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $_name;
						}
					}
				}

				return apply_filters( 'wooco_cart_item_name', $_name, $name, $item );
			}

			return $name;
		}

		function formatted_line_subtotal( $subtotal, $item ) {
			if ( ! empty( $item['wooco_ids'] ) && isset( $item['wooco_price'] ) && ( $item['wooco_price'] !== '' ) ) {
				return apply_filters( 'wooco_order_item_subtotal', wc_price( (float) $item['wooco_price'] * $item['quantity'] ), $subtotal, $item );
			}

			return $subtotal;
		}

		function cart_item_price( $price, $cart_item ) {
			if ( isset( $cart_item['wooco_ids'], $cart_item['wooco_keys'], $cart_item['wooco_price'] ) && method_exists( $cart_item['data'], 'get_pricing' ) && ( $cart_item['data']->get_pricing() !== 'only' ) ) {
				// composite
				return apply_filters( 'wooco_cart_item_price', wc_price( $cart_item['wooco_price'] ), $price, $cart_item );
			}

			if ( isset( $cart_item['wooco_parent_key'] ) ) {
				// component products
				$cart_parent_key = $cart_item['wooco_parent_key'];

				if ( isset( WC()->cart->cart_contents[ $cart_parent_key ] ) && method_exists( WC()->cart->cart_contents[ $cart_parent_key ]['data'], 'get_pricing' ) && ( WC()->cart->cart_contents[ $cart_parent_key ]['data']->get_pricing() === 'only' ) ) {
					// return original price when pricing is only
					$item_product = wc_get_product( $cart_item['data']->get_id() );

					return apply_filters( 'wooco_cart_item_price', wc_price( wc_get_price_to_display( $item_product ) ), $price, $cart_item );
				}
			}

			return $price;
		}

		function cart_item_subtotal( $subtotal, $cart_item = null ) {
			if ( isset( $cart_item['wooco_ids'], $cart_item['wooco_keys'], $cart_item['wooco_price'] ) && method_exists( $cart_item['data'], 'get_pricing' ) && ( $cart_item['data']->get_pricing() !== 'only' ) ) {
				// composite
				return apply_filters( 'wooco_cart_item_subtotal', wc_price( $cart_item['wooco_price'] * $cart_item['quantity'] ), $subtotal, $cart_item );
			}

			if ( isset( $cart_item['wooco_parent_key'] ) ) {
				// component products
				$cart_parent_key = $cart_item['wooco_parent_key'];

				if ( isset( WC()->cart->cart_contents[ $cart_parent_key ] ) && method_exists( WC()->cart->cart_contents[ $cart_parent_key ]['data'], 'get_pricing' ) && ( WC()->cart->cart_contents[ $cart_parent_key ]['data']->get_pricing() === 'only' ) ) {
					// return original price when pricing is only
					$item_product = wc_get_product( $cart_item['data']->get_id() );

					return apply_filters( 'wooco_cart_item_subtotal', wc_price( wc_get_price_to_display( $item_product, [ 'qty' => $cart_item['quantity'] ] ) ), $subtotal, $cart_item );
				}
			}

			return $subtotal;
		}

		function cart_item_removed( $cart_item_key, $cart ) {
			$new_keys = [];

			foreach ( $cart->cart_contents as $cart_k => $cart_i ) {
				if ( ! empty( $cart_i['wooco_key'] ) ) {
					$new_keys[ $cart_k ] = $cart_i['wooco_key'];
				}
			}

			if ( isset( $cart->removed_cart_contents[ $cart_item_key ]['wooco_keys'] ) ) {
				$keys = $cart->removed_cart_contents[ $cart_item_key ]['wooco_keys'];

				foreach ( $keys as $key ) {
					$cart->remove_cart_item( $key );

					if ( $new_key = array_search( $key, $new_keys ) ) {
						$cart->remove_cart_item( $new_key );
					}
				}
			}
		}

		function check_in_cart( $product_id ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] === $product_id ) {
					return true;
				}
			}

			return false;
		}

		function add_cart_item_data( $cart_item_data, $product_id ) {
			$_product = wc_get_product( $product_id );

			if ( $_product && $_product->is_type( 'composite' ) && $_product->get_components() ) {
				// make sure this is a composite
				$ids = '';

				if ( isset( $_REQUEST['wooco_ids'] ) ) {
					$ids = $_REQUEST['wooco_ids'];
					unset( $_REQUEST['wooco_ids'] );
				}

				$ids = self::clean_ids( $ids );

				if ( ! empty( $ids ) ) {
					$cart_item_data['wooco_ids'] = $ids;
				}
			}

			return $cart_item_data;
		}

		function found_in_cart( $found_in_cart, $product_id ) {
			if ( apply_filters( 'wooco_sold_individually_found_in_cart', true ) && self::check_in_cart( $product_id ) ) {
				return true;
			}

			return $found_in_cart;
		}

		function add_to_cart_validation( $passed, $product_id ) {
			if ( ( $_product = wc_get_product( $product_id ) ) && $_product->is_type( 'composite' ) && ( $components = $_product->get_components() ) ) {
				$ids   = '';
				$items = [];

				if ( isset( $_REQUEST['wooco_ids'] ) ) {
					$ids = $_REQUEST['wooco_ids'];
				}

				$ids = self::clean_ids( $ids );
				$qty = (float) ( $_REQUEST['quantity'] ?? 1 );

				if ( ! empty( $ids ) && ( $items = self::get_items( $ids ) ) ) {
					foreach ( $items as $item ) {
						$item_id      = $item['id'];
						$item_key     = $item['key'];
						$item_product = wc_get_product( $item_id );

						if ( ! isset( $components[ $item_key ] ) ) {
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}

						if ( ! $item_product ) {
							wc_add_notice( esc_html__( 'One of the component products is unavailable.', 'wpc-composite-products' ), 'error' );
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}

						if ( $item_product->is_type( 'variation' ) ) {
							$attributes = $item_product->get_variation_attributes();

							foreach ( $attributes as $attribute ) {
								if ( empty( $attribute ) ) {
									wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
									wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

									return false;
								}
							}
						}

						if ( $item_product->is_type( 'variable' ) || $item_product->is_type( 'composite' ) ) {
							wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}

						if ( ! $item_product->is_in_stock() || ! $item_product->is_purchasable() ) {
							wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}

						if ( isset( $components[ $item_key ]['custom_qty'] ) && ( $components[ $item_key ]['custom_qty'] === 'yes' ) ) {
							// custom qty
							if ( ! empty( $components[ $item_key ]['min'] ) && ( (float) $item['qty'] < (float) $components[ $item_key ]['min'] ) ) {
								wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

								return false;
							}

							if ( ! empty( $components[ $item_key ]['max'] ) && ( (float) $item['qty'] > (float) $components[ $item_key ]['max'] ) ) {
								wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

								return false;
							}
						} else {
							// fixed qty
							if ( isset( $components[ $item_key ]['qty'] ) && ( $components[ $item_key ]['qty'] != $item['qty'] ) ) {
								wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

								return false;
							}
						}

						if ( $item_product->is_sold_individually() && apply_filters( 'wooco_sold_individually_found_in_cart', true ) && self::check_in_cart( $item['id'] ) ) {
							wc_add_notice( sprintf( /* translators: product name */ esc_html__( 'You cannot add another "%s" to your cart.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}

						if ( $item_product->managing_stock() ) {
							$qty_in_cart  = ( $quantities = WC()->cart->get_cart_item_quantities() ) && isset( $quantities[ $item_product->get_stock_managed_by_id() ] ) ? $quantities[ $item_product->get_stock_managed_by_id() ] : 0;
							$qty_to_check = 0;
							$_items       = self::get_items( $ids );

							foreach ( $_items as $_item ) {
								if ( $_item['id'] == $item_id ) {
									$qty_to_check += $_item['qty'];
								}
							}

							if ( ! $item_product->has_enough_stock( $qty_in_cart + $qty_to_check * $qty ) ) {
								wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" has not enough stock.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
								wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

								return false;
							}
						}

						if ( post_password_required( $item['id'] ) ) {
							wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is protected and cannot be purchased.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}
					}
				}

				// check required
				foreach ( $components as $ck => $component ) {
					if ( isset( $component['optional'] ) && ( $component['optional'] === 'no' ) ) {
						if ( empty( $items ) || ! in_array( $ck, array_column( $items, 'key' ) ) ) {
							wc_add_notice( esc_html__( 'Missing a required component product.', 'wpc-composite-products' ), 'error' );
							wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

							return false;
						}
					}
				}
			}

			return $passed;
		}

		function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
			if ( ! empty( $cart_item_data['wooco_ids'] ) && ( $items = self::get_items( $cart_item_data['wooco_ids'] ) ) ) {
				self::add_to_cart_items( $items, $cart_item_key, $product_id, $quantity );
			}
		}

		function restore_cart_item( $cart_item_key ) {
			if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_ids'] ) ) {
				unset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'] );

				$product_id = WC()->cart->cart_contents[ $cart_item_key ]['product_id'];
				$quantity   = WC()->cart->cart_contents[ $cart_item_key ]['quantity'];

				if ( $items = self::get_items( WC()->cart->cart_contents[ $cart_item_key ]['wooco_ids'] ) ) {
					self::add_to_cart_items( $items, $cart_item_key, $product_id, $quantity );
				}
			}
		}

		function add_to_cart_items( $items, $cart_item_key, $product_id, $quantity ) {
			if ( apply_filters( 'wooco_exclude_components', false ) ) {
				return;
			}

			$separately = apply_filters( 'wooco_add_to_cart_separately', false );

			if ( ( $_product = wc_get_product( $product_id ) ) && $_product->is_type( 'composite' ) && ( $components = $_product->get_components() ) ) {
				// save current key associated with wooco_parent_key
				WC()->cart->cart_contents[ $cart_item_key ]['wooco_key'] = $cart_item_key;

				// add child products
				$count = 0; // for same component product

				foreach ( $items as $item ) {
					$count ++;
					$item_id  = $item['id'];
					$item_qty = $item['qty'];
					$item_key = $item['key'];

					if ( isset( $components[ $item_key ] ) && ( $item_product = wc_get_product( $item['id'] ) ) && ( 'trash' !== $item_product->get_status() ) && ( $item_id > 0 ) && ( $item_qty > 0 ) ) {
						$item_variation_id = 0;
						$item_variation    = [];

						if ( $item_product instanceof WC_Product_Variation ) {
							// ensure we don't add a variation to the cart directly by variation ID
							$item_variation_id = $item_id;
							$item_id           = $item_product->get_parent_id();
							$item_variation    = $item_product->get_variation_attributes();
						}

						// add to cart
						if ( ! $separately ) {
							$item_data = [
								'wooco_pos'        => $count,
								'wooco_qty'        => $item_qty,
								'wooco_price'      => self::format_price( $components[ $item_key ]['price'] ?? '100%' ),
								'wooco_component'  => $components[ $item_key ]['name'] ?? '',
								'wooco_parent_id'  => $product_id,
								'wooco_parent_key' => $cart_item_key
							];

							$item_key = WC()->cart->add_to_cart( $item_id, $item_qty * $quantity, $item_variation_id, $item_variation, $item_data );

							if ( empty( $item_key ) ) {
								// can't add the composite product
								if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'] ) ) {
									$keys = WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'];

									foreach ( $keys as $key ) {
										// remove all components
										WC()->cart->remove_cart_item( $key );
									}

									// remove the composite
									WC()->cart->remove_cart_item( $cart_item_key );

									// break out of the loop
									break;
								}
							} elseif ( ! isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'] ) || ! in_array( $item_key, WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'], true ) ) {
								// save current key
								WC()->cart->cart_contents[ $item_key ]['wooco_key'] = $item_key;
								// add keys
								WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'][] = $item_key;
							}
						} else {
							// add to cart separately
							WC()->cart->add_to_cart( $item_id, $item_qty * $quantity, $item_variation_id, $item_variation );
							// remove main product
							WC()->cart->remove_cart_item( $cart_item_key );
						}
					}
				}
			}
		}

		function before_mini_cart_contents() {
			WC()->cart->calculate_totals();
		}

		function before_calculate_totals( $cart_object ) {
			if ( ! defined( 'DOING_AJAX' ) && is_admin() ) {
				// This is necessary for WC 3.0+
				return;
			}

			$cart_contents = $cart_object->cart_contents;
			$new_keys      = [];

			foreach ( $cart_contents as $cart_k => $cart_i ) {
				if ( ! empty( $cart_i['wooco_key'] ) ) {
					$new_keys[ $cart_k ] = $cart_i['wooco_key'];
				}
			}

			foreach ( $cart_contents as $cart_item_key => $cart_item ) {
				// child product qty
				if ( ! empty( $cart_item['wooco_parent_key'] ) ) {
					$parent_new_key = array_search( $cart_item['wooco_parent_key'], $new_keys );

					// remove orphaned components
					if ( ! $parent_new_key || ! isset( $cart_contents[ $parent_new_key ] ) || ( isset( $cart_contents[ $parent_new_key ]['wooco_keys'] ) && ! in_array( $cart_item_key, $cart_contents[ $parent_new_key ]['wooco_keys'] ) ) ) {
						unset( $cart_contents[ $cart_item_key ] );
						continue;
					}

					// sync quantity
					if ( ! empty( $cart_item['wooco_qty'] ) ) {
						WC()->cart->cart_contents[ $cart_item_key ]['quantity'] = $cart_item['wooco_qty'] * $cart_contents[ $parent_new_key ]['quantity'];
					}
				}

				// child product price
				if ( ! empty( $cart_item['wooco_parent_id'] ) ) {
					$parent_product = wc_get_product( $cart_item['wooco_parent_id'] );

					if ( $parent_product && $parent_product->is_type( 'composite' ) && method_exists( $parent_product, 'get_pricing' ) ) {
						if ( $parent_product->get_pricing() === 'only' ) {
							$cart_item['data']->set_price( 0 );
						} else {
							if ( $cart_item['variation_id'] > 0 ) {
								$_product = wc_get_product( $cart_item['variation_id'] );
							} else {
								$_product = wc_get_product( $cart_item['product_id'] );
							}

							$new_price = false;
							$_price    = apply_filters( 'wooco_product_original_price', ( self::get_setting( 'product_price', 'sale_price' ) === 'regular_price' ) ? $_product->get_regular_price() : $_product->get_price(), $_product );

							if ( isset( $cart_item['wooco_price'] ) && ( $cart_item['wooco_price'] !== '' ) ) {
								$new_price = true;
								$_price    = self::get_new_price( $_price, $cart_item['wooco_price'] );
							}

							if ( $discount = self::get_discount( get_post_meta( $cart_item['wooco_parent_id'], 'wooco_discount_percent', true ) ) ) {
								$new_price = true;
								$_price    = $_price * ( 100 - $discount ) / 100;
							}

							if ( $new_price ) {
								// set new price for child product
								$cart_item['data']->set_price( (float) $_price );
							}
						}
					}
				}

				// main product price
				if ( ! empty( $cart_item['wooco_ids'] ) && $cart_item['data']->is_type( 'composite' ) && method_exists( $cart_item['data'], 'get_pricing' ) && ( $cart_item['data']->get_pricing() !== 'only' ) ) {
					//$price = $cart_item['data']->get_pricing() === 'include' ? wc_get_price_to_display( $cart_item['data'] ) : 0;

					if ( $cart_item['variation_id'] > 0 ) {
						$composite = wc_get_product( $cart_item['variation_id'] );
					} else {
						$composite = wc_get_product( $cart_item['product_id'] );
					}

					$price = $composite && ( $cart_item['data']->get_pricing() === 'include' ) ? wc_get_price_to_display( $composite ) : 0;

					if ( ! empty( $cart_item['wooco_keys'] ) ) {
						foreach ( $cart_item['wooco_keys'] as $key ) {
							if ( isset( $cart_contents[ $key ] ) ) {
								if ( $cart_contents[ $key ]['variation_id'] > 0 ) {
									$_product = wc_get_product( $cart_contents[ $key ]['variation_id'] );
								} else {
									$_product = wc_get_product( $cart_contents[ $key ]['product_id'] );
								}

								if ( ! $_product ) {
									continue;
								}

								$_price = apply_filters( 'wooco_product_original_price', ( self::get_setting( 'product_price', 'sale_price' ) === 'regular_price' ) ? $_product->get_regular_price() : $_product->get_price(), $_product );

								if ( isset( $cart_contents[ $key ]['wooco_price'] ) && ( $cart_contents[ $key ]['wooco_price'] !== '' ) ) {
									$_price = self::get_new_price( $_price, $cart_contents[ $key ]['wooco_price'] );
								}

								if ( $discount = self::get_discount( get_post_meta( $cart_item['product_id'], 'wooco_discount_percent', true ) ) ) {
									$_price = $_price * ( 100 - $discount ) / 100;
								}

								$price += wc_get_price_to_display( $_product, [
									'price' => $_price,
									'qty'   => $cart_contents[ $key ]['wooco_qty']
								] );
							}
						}
					}

					WC()->cart->cart_contents[ $cart_item_key ]['wooco_price'] = $price;

					if ( $cart_item['data']->get_pricing() === 'exclude' ) {
						$cart_item['data']->set_price( 0 );
					}
				}
			}
		}

		function cart_item_visible( $visible, $item ) {
			if ( isset( $item['wooco_parent_id'] ) ) {
				return false;
			}

			return $visible;
		}

		function order_item_visible( $visible, $order_item ) {
			if ( $order_item->get_meta( 'wooco_parent_id' ) || $order_item->get_meta( '_wooco_parent_id' ) ) {
				return false;
			}

			return $visible;
		}

		function cart_item_class( $class, $item ) {
			if ( isset( $item['wooco_parent_id'] ) ) {
				$class .= ' wooco-cart-item wooco-cart-child wooco-item-child';
			} elseif ( isset( $item['wooco_ids'] ) ) {
				$class .= ' wooco-cart-item wooco-cart-parent wooco-item-parent';

				if ( self::get_setting( 'hide_component', 'no' ) !== 'no' ) {
					$class .= ' wooco-hide-component';
				}
			}

			return $class;
		}

		function cart_item_meta( $item_data, $cart_item ) {
			if ( empty( $cart_item['wooco_ids'] ) ) {
				return $item_data;
			}

			if ( self::get_setting( 'hide_component', 'no' ) === 'yes_list' ) {
				$items_str = [];

				if ( $items = self::get_items( $cart_item['wooco_ids'] ) ) {
					foreach ( $items as $item ) {
						if ( $item_product = wc_get_product( $item['id'] ) ) {
							if ( ( self::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
								$items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item, $cart_item );
							} else {
								$items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item, $cart_item );
							}
						}
					}
				}

				if ( ! empty( $items_str ) ) {
					$item_data[] = [
						'key'     => self::localization( 'cart_components', esc_html__( 'Components', 'wpc-composite-products' ) ),
						'value'   => esc_html( $cart_item['wooco_ids'] ),
						'display' => apply_filters( 'wooco_order_component_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items ),
					];
				}
			} else {
				$items_str = [];

				if ( $items = self::get_items( $cart_item['wooco_ids'] ) ) {
					foreach ( $items as $item ) {
						if ( $item_product = wc_get_product( $item['id'] ) ) {
							if ( ( self::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
								$items_str[] = apply_filters( 'wooco_order_component_product_name', $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name(), $item, $cart_item );
							} else {
								$items_str[] = apply_filters( 'wooco_order_component_product_name', $item['qty'] . ' × ' . $item_product->get_name(), $item, $cart_item );
							}
						}
					}
				}

				if ( ! empty( $items_str ) ) {
					$item_data[] = [
						'key'     => self::localization( 'cart_components', esc_html__( 'Components', 'wpc-composite-products' ) ),
						'value'   => esc_html( $cart_item['wooco_ids'] ),
						'display' => apply_filters( 'wooco_order_component_product_names', implode( '; ', $items_str ), $items ),
					];
				}
			}

			return $item_data;
		}

		function order_item_get_formatted_meta_data( $formatted_meta ) {
			foreach ( $formatted_meta as $key => $meta ) {
				if ( ( $meta->key === 'wooco_ids' ) || ( $meta->key === 'wooco_parent_id' ) || ( $meta->key === 'wooco_qty' ) || ( $meta->key === 'wooco_price' ) || ( $meta->key === 'wooco_component' ) ) {
					unset( $formatted_meta[ $key ] );
				}
			}

			return $formatted_meta;
		}

		function add_order_item_meta( $item, $cart_item_key, $values ) {
			if ( isset( $values['wooco_parent_id'] ) ) {
				$item->update_meta_data( 'wooco_parent_id', $values['wooco_parent_id'] );
			}

			if ( isset( $values['wooco_qty'] ) ) {
				$item->update_meta_data( 'wooco_qty', $values['wooco_qty'] );
			}

			if ( isset( $values['wooco_ids'] ) ) {
				$item->update_meta_data( 'wooco_ids', $values['wooco_ids'] );
			}

			if ( isset( $values['wooco_price'] ) ) {
				$item->update_meta_data( 'wooco_price', $values['wooco_price'] );
			}

			if ( isset( $values['wooco_component'] ) ) {
				$item->update_meta_data( 'wooco_component', $values['wooco_component'] );
			}
		}

		function hidden_order_itemmeta( $hidden ) {
			return array_merge( $hidden, [
				'wooco_parent_id',
				'wooco_qty',
				'wooco_ids',
				'wooco_price',
				'wooco_pos',
				'wooco_component'
			] );
		}

		function order_item_meta_start( $order_item_id, $order_item ) {
			if ( $ids = $order_item->get_meta( 'wooco_ids' ) ) {
				if ( $items = self::get_items( $ids ) ) {
					if ( self::get_setting( 'hide_component_order', 'no' ) === 'yes_list' ) {
						$items_str = [];

						foreach ( $items as $item ) {
							if ( $item_product = wc_get_product( $item['id'] ) ) {
								if ( ( self::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
									$items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
								} else {
									$items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
								}
							}
						}

						$items_str = apply_filters( 'wooco_order_component_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items );
					} else {
						$items_str = [];

						foreach ( $items as $item ) {
							if ( $item_product = wc_get_product( $item['id'] ) ) {
								if ( ( self::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
									$items_str[] = apply_filters( 'wooco_order_component_product_name', $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name(), $item );
								} else {
									$items_str[] = apply_filters( 'wooco_order_component_product_name', $item['qty'] . ' × ' . $item_product->get_name(), $item );
								}
							}
						}

						$items_str = apply_filters( 'wooco_order_component_product_names', implode( '; ', $items_str ), $items );
					}

					echo wp_kses_post( apply_filters( 'wooco_before_order_itemmeta_composite', '<div class="wooco-itemmeta-composite">' . sprintf( self::localization( 'cart_components_s', /* translators: components */ esc_html__( 'Components: %s', 'wpc-composite-products' ) ), $items_str ) . '</div>', $order_item_id, $order_item ) );
				}
			}

			if ( ( $parent_id = $order_item->get_meta( 'wooco_parent_id' ) ) && ( $parent_product = wc_get_product( $parent_id ) ) ) {
				if ( ( $component = $order_item->get_meta( 'wooco_component' ) ) && ! empty( $component ) ) {
					echo wp_kses_post( apply_filters( 'wooco_before_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( self::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $component ) . '</div>', $order_item_id, $order_item ) );
				} else {
					echo wp_kses_post( apply_filters( 'wooco_before_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( self::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() ) . '</div>', $order_item_id, $order_item ) );
				}
			}
		}

		function before_order_itemmeta( $order_item_id, $order_item ) {
			if ( $ids = $order_item->get_meta( 'wooco_ids' ) ) {
				if ( $items = self::get_items( $ids ) ) {
					$items_str = [];

					foreach ( $items as $item ) {
						if ( $item_product = wc_get_product( $item['id'] ) ) {
							if ( ( self::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
								$items_str[] = apply_filters( 'wooco_admin_order_component_product_name', '<li>' . $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
							} else {
								$items_str[] = apply_filters( 'wooco_admin_order_component_product_name', '<li>' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
							}
						}
					}

					$items_str = apply_filters( 'wooco_order_component_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items );

					echo wp_kses_post( apply_filters( 'wooco_before_admin_order_itemmeta_composite', '<div class="wooco-itemmeta-composite">' . sprintf( self::localization( 'cart_components_s', /* translators: components */ esc_html__( 'Components: %s', 'wpc-composite-products' ) ), $items_str ) . '</div>', $order_item_id, $order_item ) );
				}
			}

			if ( ( $parent_id = $order_item->get_meta( 'wooco_parent_id' ) ) && ( $parent_product = wc_get_product( $parent_id ) ) ) {
				if ( ( $component = $order_item->get_meta( 'wooco_component' ) ) && ! empty( $component ) ) {
					echo wp_kses_post( apply_filters( 'wooco_before_admin_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( self::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $component ) . '</div>', $order_item_id, $order_item ) );
				} else {
					echo wp_kses_post( apply_filters( 'wooco_before_admin_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( self::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() ) . '</div>', $order_item_id, $order_item ) );
				}
			}
		}

		function get_cart_item_from_session( $cart_item, $item_session_values ) {
			if ( ! empty( $item_session_values['wooco_ids'] ) ) {
				$cart_item['wooco_ids']   = $item_session_values['wooco_ids'];
				$cart_item['wooco_price'] = $item_session_values['wooco_price'] ?? '';
			}

			if ( ! empty( $item_session_values['wooco_parent_id'] ) ) {
				$cart_item['wooco_parent_id']  = $item_session_values['wooco_parent_id'];
				$cart_item['wooco_pos']        = $item_session_values['wooco_pos'] ?? '';
				$cart_item['wooco_qty']        = $item_session_values['wooco_qty'] ?? '';
				$cart_item['wooco_price']      = $item_session_values['wooco_price'] ?? '';
				$cart_item['wooco_component']  = $item_session_values['wooco_component'] ?? '';
				$cart_item['wooco_parent_key'] = $item_session_values['wooco_parent_key'] ?? '';
			}

			return $cart_item;
		}

		function display_post_states( $states, $post ) {
			if ( 'product' == get_post_type( $post->ID ) ) {
				if ( ( $product = wc_get_product( $post->ID ) ) && $product->is_type( 'composite' ) ) {
					$count = 0;

					if ( ( $components = $product->get_components() ) && is_array( $components ) ) {
						$count = count( $components );
					}

					$states[] = apply_filters( 'wooco_post_states', '<span class="wooco-state">' . sprintf( /* translators: count */ esc_html__( 'Composite (%d)', 'wpc-composite-products' ), $count ) . '</span>', $count, $product );
				}
			}

			return $states;
		}

		function cart_item_remove_link( $link, $cart_item_key ) {
			if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_parent_key'] ) ) {
				$parent_key = WC()->cart->cart_contents[ $cart_item_key ]['wooco_parent_key'];

				if ( isset( WC()->cart->cart_contents[ $parent_key ] ) || array_search( $parent_key, array_column( WC()->cart->cart_contents, 'wooco_key', 'key' ) ) ) {
					return '';
				}
			}

			return $link;
		}

		function cart_item_quantity( $quantity, $cart_item_key, $cart_item ) {
			// add qty as text - not input
			if ( isset( $cart_item['wooco_parent_id'] ) ) {
				return $cart_item['quantity'];
			}

			return $quantity;
		}

		function product_type_selector( $types ) {
			$types['composite'] = esc_html__( 'Smart composite', 'wpc-composite-products' );

			return $types;
		}

		function product_data_tabs( $tabs ) {
			$tabs['composite'] = [
				'label'  => esc_html__( 'Components', 'wpc-composite-products' ),
				'target' => 'wooco_settings',
				'class'  => [ 'show_if_composite' ],
			];

			return $tabs;
		}

		function product_data_panels() {
			global $post, $thepostid, $product_object;

			if ( $product_object instanceof WC_Product ) {
				$product_id = $product_object->get_id();
			} elseif ( is_numeric( $thepostid ) ) {
				$product_id = $thepostid;
			} elseif ( $post instanceof WP_Post ) {
				$product_id = $post->ID;
			} else {
				$product_id = 0;
			}

			if ( ! $product_id ) {
				?>
                <div id='wooco_settings' class='panel woocommerce_options_panel wooco_table'>
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-composite-products' ); ?></p>
                </div>
				<?php
				return;
			}

			$components    = get_post_meta( $product_id, 'wooco_components', true );
			$pricing       = get_post_meta( $product_id, 'wooco_pricing', true );
			$same_products = get_post_meta( $product_id, 'wooco_same_products', true );
			$shipping_fee  = get_post_meta( $product_id, 'wooco_shipping_fee', true );
			?>
            <div id='wooco_settings' class='panel woocommerce_options_panel wooco_table'>
                <table class="wooco_components">
                    <thead></thead>
                    <tbody>
					<?php if ( ! empty( $components ) && is_array( $components ) ) {
						foreach ( $components as $component_key => $component ) {
							self::component( false, $component, $component_key );
						}
					} else {
						self::component( true );
					} ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>
                            <div>
                                <a href="#" class="wooco_add_component button">
									<?php esc_html_e( '+ Add component', 'wpc-composite-products' ); ?>
                                </a> <a href="#" class="wooco_expand_all">
									<?php esc_html_e( 'Expand All', 'wpc-composite-products' ); ?>
                                </a> <a href="#" class="wooco_collapse_all">
									<?php esc_html_e( 'Collapse All', 'wpc-composite-products' ); ?>
                                </a>
                            </div>
                            <div>
                                <!--
								<a href="#" class="wooco_export_components hint--left" aria-label="<?php esc_attr_e( 'Remember to save current components before exporting to get the latest version.', 'wpc-composite-products' ); ?>">
									<?php esc_html_e( 'Export', 'wpc-composite-products' ); ?>
								</a>
								-->
                                <a href="#" class="wooco_save_components button button-primary">
									<?php esc_html_e( 'Save components', 'wpc-composite-products' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    </tfoot>
                </table>
                <table>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Pricing', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label for="wooco_pricing"></label><select id="wooco_pricing" name="wooco_pricing">
                                <option value="only" <?php selected( $pricing, 'only' ); ?>><?php esc_html_e( 'Only base price', 'wpc-composite-products' ); ?></option>
                                <option value="include" <?php selected( $pricing, 'include' ); ?>><?php esc_html_e( 'Include base price', 'wpc-composite-products' ); ?></option>
                                <option value="exclude" <?php selected( $pricing, 'exclude' ); ?>><?php esc_html_e( 'Exclude base price', 'wpc-composite-products' ); ?></option>
                            </select>
                            <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( '"Base price" is the price set in the General tab. When "Only base price" is chosen, the total price won\'t change despite the price changes in variable components.', 'wpc-composite-products' ); ?>"></span>
                            <span style="color: #c9356e">* <?php esc_html_e( 'Always put a price in the General tab to display the Add to Cart button. This is also the base price.', 'wpc-composite-products' ); ?></span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Discount', 'wpc-composite-products' ); ?></th>
                        <td style="vertical-align: middle; line-height: 30px;">
                            <label for="wooco_discount_percent"></label><input id="wooco_discount_percent" name="wooco_discount_percent" type="number" min="0.0001" step="0.0001" max="99.9999" value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_discount_percent', true ) ?: '' ); ?>" style="width: 80px"/>%.
                            <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'The universal percentage discount will be applied equally on each component\'s price, not on the total.', 'wpc-composite-products' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
						<?php
						$min = get_post_meta( $product_id, 'wooco_qty_min', true ) ?: '';
						$max = get_post_meta( $product_id, 'wooco_qty_max', true ) ?: '';

						if ( class_exists( 'WPCleverWoopq' ) && ( get_option( '_woopq_decimal', 'no' ) === 'yes' ) ) {
							$step = '0.000001';
						} else {
							$step = '1';

							if ( ! empty( $min ) ) {
								$min = (int) $min;
							}

							if ( ! empty( $max ) ) {
								$max = (int) $max;
							}
						}
						?>
                        <th><?php esc_html_e( 'Quantity', 'wpc-composite-products' ); ?></th>
                        <td style="vertical-align: middle; line-height: 30px;">
                            Min <label>
                                <input name="wooco_qty_min" type="number" style="width: 80px" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $min ); ?>"/>
                            </label> Max <label>
                                <input name="wooco_qty_max" type="number" min="0" style="width: 80px" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $max ); ?>"/>
                            </label>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Total limits', 'wpc-composite-products' ); ?></th>
                        <td>
                            <input id="wooco_total_limits" name="wooco_total_limits" type="checkbox" <?php echo( get_post_meta( $product_id, 'wooco_total_limits', true ) === 'on' ? 'checked' : '' ); ?>/>
                            <label for="wooco_total_limits"><?php esc_html_e( 'Configure total limits for the current composite.', 'wpc-composite-products' ); ?></label>
                            <span class="wooco_show_if_total_limits">
                                        Min <label for="wooco_total_limits_min"></label><input id="wooco_total_limits_min" name="wooco_total_limits_min" type="number" min="0" style="width: 80px" value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_total_limits_min', true ) ); ?>"/>
                                        Max <label for="wooco_total_limits_max"></label><input id="wooco_total_limits_max" name="wooco_total_limits_max" type="number" min="0" style="width: 80px" value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_total_limits_max', true ) ); ?>"/> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
                                    </span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Same products', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label for="wooco_same_products"></label><select id="wooco_same_products" name="wooco_same_products">
                                <option value="allow" <?php selected( $same_products, 'allow' ); ?>><?php esc_html_e( 'Allow', 'wpc-composite-products' ); ?></option>
                                <option value="do_not_allow" <?php selected( $same_products, 'do_not_allow' ); ?>><?php esc_html_e( 'Do not allow', 'wpc-composite-products' ); ?></option>
                            </select>
                            <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Allow/Do not allow the buyer to choose the same products in the components.', 'wpc-composite-products' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Shipping fee', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label for="wooco_shipping_fee"></label><select id="wooco_shipping_fee" name="wooco_shipping_fee">
                                <option value="whole" <?php selected( $shipping_fee, 'whole' ); ?>><?php esc_html_e( 'Apply to the whole composite', 'wpc-composite-products' ); ?></option>
                                <option value="each" <?php selected( $shipping_fee, 'each' ); ?>><?php esc_html_e( 'Apply to each component product', 'wpc-composite-products' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Custom display price', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label>
                                <input type="text" name="wooco_custom_price" value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_custom_price', true ) ); ?>"/>
                            </label> E.g: <code>From $10 to $100</code>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Above text', 'wpc-composite-products' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
                                    <textarea name="wooco_before_text"><?php echo esc_textarea( get_post_meta( $product_id, 'wooco_before_text', true ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Under text', 'wpc-composite-products' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
                                    <textarea name="wooco_after_text"><?php echo esc_textarea( get_post_meta( $product_id, 'wooco_after_text', true ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
					<?php do_action( 'wooco_product_settings', $product_id ); ?>
                </table>
            </div>
			<?php
		}

		function process_meta_composite( $post_id ) {
			if ( isset( $_POST['wooco_components'] ) ) {
				update_post_meta( $post_id, 'wooco_components', self::sanitize_array( $_POST['wooco_components'] ) );
			}

			if ( isset( $_POST['wooco_pricing'] ) ) {
				update_post_meta( $post_id, 'wooco_pricing', sanitize_text_field( $_POST['wooco_pricing'] ) );
			}

			if ( isset( $_POST['wooco_discount_percent'] ) ) {
				update_post_meta( $post_id, 'wooco_discount_percent', sanitize_text_field( $_POST['wooco_discount_percent'] ) );
			}

			if ( isset( $_POST['wooco_qty_min'] ) ) {
				update_post_meta( $post_id, 'wooco_qty_min', sanitize_text_field( $_POST['wooco_qty_min'] ) );
			}

			if ( isset( $_POST['wooco_qty_max'] ) ) {
				update_post_meta( $post_id, 'wooco_qty_max', sanitize_text_field( $_POST['wooco_qty_max'] ) );
			}

			if ( isset( $_POST['wooco_total_limits'] ) ) {
				update_post_meta( $post_id, 'wooco_total_limits', 'on' );
			} else {
				update_post_meta( $post_id, 'wooco_total_limits', 'off' );
			}

			if ( isset( $_POST['wooco_total_limits_min'] ) ) {
				update_post_meta( $post_id, 'wooco_total_limits_min', sanitize_text_field( $_POST['wooco_total_limits_min'] ) );
			}

			if ( isset( $_POST['wooco_total_limits_max'] ) ) {
				update_post_meta( $post_id, 'wooco_total_limits_max', sanitize_text_field( $_POST['wooco_total_limits_max'] ) );
			}

			if ( isset( $_POST['wooco_same_products'] ) ) {
				update_post_meta( $post_id, 'wooco_same_products', sanitize_text_field( $_POST['wooco_same_products'] ) );
			}

			if ( isset( $_POST['wooco_shipping_fee'] ) ) {
				update_post_meta( $post_id, 'wooco_shipping_fee', sanitize_text_field( $_POST['wooco_shipping_fee'] ) );
			}

			if ( isset( $_POST['wooco_custom_price'] ) ) {
				update_post_meta( $post_id, 'wooco_custom_price', sanitize_post_field( 'post_content', $_POST['wooco_custom_price'], $post_id, 'display' ) );
			}

			if ( isset( $_POST['wooco_before_text'] ) ) {
				update_post_meta( $post_id, 'wooco_before_text', sanitize_post_field( 'post_content', $_POST['wooco_before_text'], $post_id, 'display' ) );
			}

			if ( isset( $_POST['wooco_after_text'] ) ) {
				update_post_meta( $post_id, 'wooco_after_text', sanitize_post_field( 'post_content', $_POST['wooco_after_text'], $post_id, 'display' ) );
			}
		}

		function add_to_cart_form() {
			self::show_items();
			wc_get_template( 'single-product/add-to-cart/simple.php' );
		}

		function add_to_cart_button() {
			global $product;

			if ( $product && $product->is_type( 'composite' ) ) {
				echo '<input name="wooco_ids" class="wooco-ids wooco-ids-' . esc_attr( $product->get_id() ) . '" type="hidden" value=""/>';
			}
		}

		function loop_add_to_cart_link( $link, $product ) {
			if ( $product->is_type( 'composite' ) ) {
				$link = str_replace( 'ajax_add_to_cart', '', $link );
			}

			return $link;
		}

		function cart_shipping_packages( $packages ) {
			if ( ! empty( $packages ) ) {
				foreach ( $packages as $package_key => $package ) {
					if ( ! empty( $package['contents'] ) ) {
						foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
							if ( ! empty( $cart_item['wooco_parent_id'] ) ) {
								if ( get_post_meta( $cart_item['wooco_parent_id'], 'wooco_shipping_fee', true ) !== 'each' ) {
									unset( $packages[ $package_key ]['contents'][ $cart_item_key ] );
								}
							}

							if ( ! empty( $cart_item['wooco_ids'] ) ) {
								if ( get_post_meta( $cart_item['data']->get_id(), 'wooco_shipping_fee', true ) === 'each' ) {
									unset( $packages[ $package_key ]['contents'][ $cart_item_key ] );
								}
							}
						}
					}
				}
			}

			return $packages;
		}

		function get_price_html( $price, $product ) {
			if ( $product->is_type( 'composite' ) ) {
				$product_id   = $product->get_id();
				$custom_price = stripslashes( get_post_meta( $product_id, 'wooco_custom_price', true ) );

				if ( ! empty( $custom_price ) ) {
					return $custom_price;
				}

				if ( $product->get_pricing() !== 'only' ) {
					switch ( self::get_setting( 'price_format', 'from_regular' ) ) {
						case 'from_regular':
							return esc_html__( 'From', 'wpc-composite-products' ) . ' ' . wc_price( wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ) );
						case 'from_sale':
							return esc_html__( 'From', 'wpc-composite-products' ) . ' ' . wc_price( wc_get_price_to_display( $product, [ 'price' => $product->get_price() ] ) );
					}
				}
			}

			return $price;
		}

		function product_price_class( $class ) {
			global $product;

			if ( $product && $product->is_type( 'composite' ) ) {
				$class .= ' wooco-price-' . $product->get_id();
			}

			return $class;
		}

		function order_again_cart_item_data( $item_data, $item ) {
			if ( isset( $item['wooco_ids'] ) ) {
				$item_data['wooco_ids']         = $item['wooco_ids'];
				$item_data['wooco_order_again'] = 'yes';
			}

			if ( isset( $item['wooco_parent_id'] ) ) {
				$item_data['wooco_order_again'] = 'yes';
				$item_data['wooco_parent_id']   = $item['wooco_parent_id'];
			}

			return $item_data;
		}

		function cart_loaded_from_session() {
			foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
				if ( isset( $cart_item['wooco_order_again'], $cart_item['wooco_parent_id'] ) ) {
					WC()->cart->remove_cart_item( $cart_item_key );
				}

				if ( isset( $cart_item['wooco_order_again'], $cart_item['wooco_ids'] ) ) {
					if ( $items = self::get_items( $cart_item['wooco_ids'] ) ) {
						self::add_to_cart_items( $items, $cart_item_key, $cart_item['product_id'], $cart_item['quantity'] );
					}
				}
			}
		}

		function coupon_is_valid_for_product( $valid, $product, $coupon, $item ) {
			if ( ( self::get_setting( 'coupon_restrictions', 'no' ) === 'both' ) && ( isset( $item['wooco_parent_id'] ) || isset( $item['wooco_ids'] ) ) ) {
				// exclude both composite and component products
				return false;
			}

			if ( ( self::get_setting( 'coupon_restrictions', 'no' ) === 'composite' ) && isset( $item['wooco_ids'] ) ) {
				// exclude composite
				return false;
			}

			if ( ( self::get_setting( 'coupon_restrictions', 'no' ) === 'component' ) && isset( $item['wooco_parent_id'] ) ) {
				// exclude component products
				return false;
			}

			return $valid;
		}

		function show_items( $product = null ) {
			if ( ! $product ) {
				global $product;
			}

			if ( ! $product || ! $product->is_type( 'composite' ) ) {
				return;
			}

			$df_products = isset( $_GET['df'] ) ? explode( ',', sanitize_text_field( $_GET['df'] ) ) : [];
			$product_id  = $product->get_id();
			$order       = 1;

			do_action( 'wooco_before_wrap', $product );

			if ( $components = $product->get_components() ) {
				// get settings
				$selector             = self::get_setting( 'selector', 'ddslick' );
				$show_price           = self::get_setting( 'show_price', 'yes' ) === 'yes';
				$show_availability    = self::get_setting( 'show_availability', 'yes' ) === 'yes';
				$show_image           = self::get_setting( 'show_image', 'yes' ) === 'yes';
				$checked              = self::get_setting( 'checked', 'yes' ) === 'yes';
				$checkbox             = self::get_setting( 'checkbox', 'no' ) === 'yes';
				$product_link         = self::get_setting( 'product_link', 'no' );
				$option_none_required = self::get_setting( 'option_none_required', 'no' ) === 'yes';
				$total_limit          = get_post_meta( $product_id, 'wooco_total_limits', true ) === 'on';
				$total_limit_min      = get_post_meta( $product_id, 'wooco_total_limits_min', true );
				$total_limit_max      = get_post_meta( $product_id, 'wooco_total_limits_max', true );

				// option none image
				$option_none_image = $option_none_image_full = self::get_setting( 'option_none_image', 'placeholder' ) !== 'none' ? wc_placeholder_img_src() : '';

				if ( ( self::get_setting( 'option_none_image', 'placeholder' ) === 'product' ) && ( $product_image_id = $product->get_image_id() ) ) {
					$product_image          = wp_get_attachment_image_src( $product_image_id, self::$image_size );
					$product_image_full     = wp_get_attachment_image_src( $product_image_id, 'full' );
					$option_none_image      = $product_image[0] ?? '';
					$option_none_image_full = $product_image_full[0] ?? '';
				}

				if ( ( self::get_setting( 'option_none_image', 'placeholder' ) === 'custom' ) && ( $option_none_image_id = self::get_setting( 'option_none_image_id' ) ) ) {
					$custom_image           = wp_get_attachment_image_src( $option_none_image_id, self::$image_size );
					$custom_image_full      = wp_get_attachment_image_src( $option_none_image_id, 'full' );
					$option_none_image      = $custom_image[0] ?? '';
					$option_none_image_full = $custom_image_full[0] ?? '';
				}

				echo '<div class="' . esc_attr( apply_filters( 'wooco_wrap_class', 'wooco_wrap wooco-wrap wooco-wrap-' . $product_id, $product ) ) . '" data-id="' . esc_attr( $product_id ) . '">';

				if ( $before_text = apply_filters( 'wooco_before_text', get_post_meta( $product_id, 'wooco_before_text', true ), $product_id ) ) {
					echo '<div class="wooco_before_text wooco-before-text wooco-text">' . wp_kses_post( do_shortcode( $before_text ) ) . '</div>';
				}

				do_action( 'wooco_before_components', $product );
				?>
                <div class="<?php echo esc_attr( apply_filters( 'wooco_components_class', 'wooco_components wooco-components', $product ) ); ?>" data-percent="<?php echo esc_attr( $product->get_discount() ); ?>" data-min="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_qty_min', true ) ); ?>" data-max="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_qty_max', true ) ); ?>" data-price="<?php echo esc_attr( wc_get_price_to_display( $product ) ); ?>" data-regular-price="<?php echo esc_attr( wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ) ); ?>" data-pricing="<?php echo esc_attr( $product->get_pricing() ); ?>" data-same="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_same_products', true ) === 'do_not_allow' ? 'no' : 'yes' ); ?>" data-checkbox="<?php echo esc_attr( $checkbox ? 'yes' : 'no' ); ?>" data-total-min="<?php echo esc_attr( $total_limit && $total_limit_min ? $total_limit_min : 0 ); ?>" data-total-max="<?php echo esc_attr( $total_limit && $total_limit_max ? $total_limit_max : '-1' ); ?>">
					<?php
					foreach ( $components as $key => $component ) {
						$component_type = $component['type'];

						if ( $component_type === 'products' ) {
							$component_val = $component['products'] ?? [];
						} else {
							$component_val = $component['other'] ?? [];
						}

						$component_default = $component['default'] ?? 0;

						if ( ! is_numeric( $component_default ) ) {
							// sku
							$component_default = wc_get_product_id_by_sku( $component_default );
						}

						$component_default    = absint( $df_products[ $order - 1 ] ?? $component_default );
						$component_default    = apply_filters( 'wooco_component_default', $component_default, $component );
						$component['default'] = $component_default;
						$component_required   = isset( $component['optional'] ) && ( $component['optional'] === 'no' );
						$component_multiple   = isset( $component['multiple'] ) && ( $component['multiple'] === 'yes' );
						$component_qty        = (float) ( $component['qty'] ?? 1 );
						$component_custom_qty = isset( $component['custom_qty'] ) && $component['custom_qty'] === 'yes';
						$component_exclude    = $component['exclude'] ?? [];
						$component_orderby    = (string) ( $component['orderby'] ?? 'default' );
						$component_order      = (string) ( $component['order'] ?? 'default' );
						$component_price      = isset( $component['price'] ) ? self::format_price( $component['price'] ) : '';
						$component_products   = self::get_products( $component_type, $component_val, $component_orderby, $component_order, $component_exclude, $component_default, $component_qty, $component_price, $component_custom_qty );
						$component_selector   = isset( $component['selector'] ) && $component['selector'] !== 'default' ? $component['selector'] : $selector;

						// force set 'grid_3' if enable multiple
						if ( $component_multiple && ! in_array( $component_selector, [
								'list',
								'grid_2',
								'grid_3',
								'grid_4'
							] ) ) {
							$component_selector = 'grid_3';
						}

						$component_dropdown = ! in_array( $component_selector, [
								'list',
								'grid_2',
								'grid_3',
								'grid_4'
							] ) && ! $component_multiple;
						$component_class    = 'wooco_component wooco_component_' . $order . ' wooco_component_type_' . $component_type . ' wooco_component_has_' . ( $component_products ? count( $component_products ) : '0' ) . ' wooco_component_layout_' . $component_selector;

						if ( $component_required ) {
							$component_class .= ' wooco_component_required';
						}

						if ( $option_none_required ) {
							$component_class .= ' wooco_component_option_none_required';
						}

						if ( $component_multiple ) {
							$component_class .= ' wooco_component_multiple';
						}

						if ( $component_custom_qty ) {
							$component_class .= ' wooco_component_custom_qty';
						}

						if ( ! $component_products && ! $component_required ) {
							// have no products and isn't required, hide it
							continue;
						}

						echo '<div class="' . esc_attr( apply_filters( 'wooco_component_class', $component_class, $component, $order ) ) . '">';
						do_action( 'wooco_before_component', $component, $order );

						if ( ! empty( $component['name'] ) ) {
							echo '<div class="wooco_component_name">' . esc_html( $component['name'] ) . '</div>';
						}

						if ( ! empty( $component['desc'] ) ) {
							echo '<div class="wooco_component_desc">' . wp_kses_post( $component['desc'] ) . '</div>';
						}

						if ( ! $component_products ) {
							if ( $component_required ) {
								// have no product and required
								?>
                                <div class="wooco_component_product wooco_component_product_none" data-key="<?php echo esc_attr( $key ); ?>" data-name="<?php echo esc_attr( $component['name'] ); ?>" data-id="0" data-qty="<?php echo esc_attr( $component_qty ); ?>" data-m-min="<?php echo esc_attr( ! empty( $component['m_min'] ) ? (float) $component['m_min'] : '0' ); ?>" data-m-max="<?php echo esc_attr( ! empty( $component['m_max'] ) ? (float) $component['m_max'] : '10000' ); ?>" data-price="0" data-regular-price="0" data-new-price="0" data-required="yes" data-custom-qty="<?php echo esc_attr( $component_custom_qty ? 'yes' : 'no' ); ?>" data-multiple="<?php echo esc_attr( $component_multiple ? 'yes' : 'no' ); ?>"></div>
								<?php
							}
						} else {
							if ( ( count( $component_products ) === 1 ) && $component_required ) {
								// have one product and required
								$one_required = true;
							} else {
								$one_required = false;
							}

							$option_none_image       = apply_filters( 'wooco_option_none_img_src', $option_none_image, $component, $product );
							$option_none_image_full  = apply_filters( 'wooco_option_none_img_full', $option_none_image_full, $component, $product );
							$option_none             = $component_required ? self::localization( 'option_none_required', esc_html__( 'Please make your choice here', 'wpc-composite-products' ) ) : self::localization( 'option_none', esc_html__( 'No, thanks. I don\'t need this', 'wpc-composite-products' ) );
							$option_none_label       = apply_filters( 'wooco_option_none', $option_none, $component, $product );
							$option_none_description = apply_filters( 'wooco_option_none_description', wc_price( 0 ), $component, $product );
							$option_none_data        = apply_filters( 'wooco_option_none_data', [
								'id'            => '-1',
								'pid'           => '-1',
								'qty'           => '0',
								'price'         => '',
								'regular-price' => '',
								'link'          => '',
								'price-html'    => '',
								'imagesrc'      => esc_url( $option_none_image ),
								'imagefull'     => esc_url( $option_none_image_full ),
								'availability'  => '',
								'description'   => htmlentities( $option_none_description ),
							], $component, $product );
							?>
                            <div class="wooco_component_product" data-key="<?php echo esc_attr( $key ); ?>" data-id="-1" data-qty="<?php echo esc_attr( $component['qty'] ); ?>" data-m-min="<?php echo esc_attr( ! empty( $component['m_min'] ) ? (float) $component['m_min'] : '0' ); ?>" data-m-max="<?php echo esc_attr( ! empty( $component['m_max'] ) ? (float) $component['m_max'] : '10000' ); ?>" data-price="0" data-regular-price="0" data-price-html="" data-name="<?php echo esc_attr( $component['name'] ); ?>" data-new-price="<?php echo esc_attr( $component_price ); ?>" data-required="<?php echo esc_attr( $component_required ? 'yes' : 'no' ); ?>" data-custom-qty="<?php echo esc_attr( $component_custom_qty ? 'yes' : 'no' ); ?>" data-multiple="<?php echo esc_attr( $component_multiple ? 'yes' : 'no' ); ?>">

								<?php if ( $checkbox && $component_dropdown ) { ?>
                                    <div class="wooco_component_product_checkbox">
                                        <label>
                                            <input class="wooco-checkbox" type="checkbox" <?php echo( apply_filters( 'wooco_component_checkbox_checked', $checked || $component_required, $component ) ? 'checked="checked"' : '' ); ?>
												<?php echo( apply_filters( 'wooco_component_checkbox_disabled', $component_required, $component ) ? 'disabled' : '' ); ?>/>
                                        </label>
                                    </div>
								<?php } ?>

								<?php if ( ( $selector === 'select' ) && $show_image ) { ?>
                                    <div class="wooco_component_product_image">
										<?php echo '<img src="' . esc_url( $option_none_image ) . '"/>'; ?>
                                    </div>
								<?php } ?>

                                <div class="wooco_component_product_selection">
									<?php if ( ! $component_dropdown ) {
										// list or grid
										$_order = 1;

										if ( $component_selector === 'list' ) {
											echo '<div class="wooco_component_product_selection_list">';

											foreach ( $component_products as $component_product ) {
												if ( $component_product_obj = wc_get_product( $component_product['id'] ) ) {
													$item_selected = apply_filters( 'wooco_component_product_selected', isset( $component['default'] ) && ( $component['default'] == $component_product['id'] ), $component_product, $component );
													echo '<div class="wooco_component_product_selection_list_item wooco_component_product_selection_item ' . ( $item_selected || $one_required ? 'wooco_item_selected' : '' ) . '" ' . self::data_attributes( $component_product ) . '>';
													echo '<div class="wooco_component_product_selection_list_item_choose"><span></span></div>';
													echo '<div class="wooco_component_product_selection_list_item_image">' . wp_kses_post( $component_product_obj->get_image() ) . '</div>';
													echo '<div class="wooco_component_product_selection_list_item_info">';
													echo '<div class="wooco_component_product_selection_list_item_name">' . wp_kses_post( $component_product['name'] ) . '</div>';
													echo '<div class="wooco_component_product_selection_list_item_desc">' . wp_kses_post( html_entity_decode( $component_product['description'] ) ) . '</div>';
													echo '</div>';

													if ( $component_custom_qty ) {
														$min = 0;
														$max = 1000;
														$qty = $component['qty'];

														if ( ! empty( $component['min'] ) ) {
															$min = $component['min'];
														}

														if ( ! empty( $component['max'] ) ) {
															$max = $component['max'];
														}

														if ( class_exists( 'WPCleverWoopq' ) && ( get_option( '_woopq_decimal', 'no' ) === 'yes' ) ) {
															$step = get_option( '_woopq_step' ) ?: '1';
														} else {
															$step = '1';
															$qty  = (int) $qty;
															$min  = (int) $min;
															$max  = (int) $max;
														}

														if ( ( $max_purchase = $component_product_obj->get_max_purchase_quantity() ) && ( $max_purchase > 0 ) && ( $max_purchase < $max ) ) {
															// get_max_purchase_quantity can return -1
															$max = $max_purchase;
														}

														echo '<div class="wooco_component_product_selection_list_item_qty wooco_component_product_selection_item_qty wooco-qty">';
														echo '<span class="wooco-qty-label">' . esc_html( self::localization( 'qty_label', esc_html__( 'Qty:', 'wpc-composite-products' ) ) ) . '</span>';
														echo '<span class="wooco-qty-input">';
														echo '<span class="wooco_component_product_qty_btn wooco_component_product_qty_minus wooco-minus">-</span>';
														echo woocommerce_quantity_input( [
															'input_value' => $qty,
															'min_value'   => $min,
															'max_value'   => $max,
															'wooco_qty'   => [
																'input_value' => $qty,
																'min_value'   => $min,
																'max_value'   => $max
															],
															'classes'     => apply_filters( 'wooco_qty_classes', [
																'input-text',
																'wooco_component_product_qty_input',
																'qty',
																'text'
															], $component ),
															'input_name'  => 'wooco_qty_' . $order . '_' . $_order
															// compatible with WPC Product Quantity
														], $component_product_obj, false );
														echo '<span class="wooco_component_product_qty_btn wooco_component_product_qty_plus wooco-plus">+</span>';
														echo '</span>';
														echo '</div>';

														//echo wp_kses_post( apply_filters( 'wooco_qty_input', $qty_input, $qty, $step, $min, $max ) );
													}

													if ( $product_link !== 'no' ) {
														if ( $component_product_obj->is_visible() || apply_filters( 'wooco_hidden_product_link', false ) ) {
															$quickview_id = is_a( $component_product_obj, 'WC_Product_Variation' ) && ( apply_filters( 'wooco_quickview_variation', 'default' ) === 'parent' ) ? $component_product_obj->get_parent_id() : $component_product['id'];
															echo '<div class="wooco_component_product_selection_list_item_link"><a ' . ( $product_link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $quickview_id ) . '" data-context="wooco"' : 'class="wooco_component_product_selection_list_item_link"' ) . ' href="' . esc_url( $component_product_obj->get_permalink() ) . '" ' . ( $product_link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . esc_html( $component_product_obj->get_name() ) . '</a></div>';
														}
													}

													echo '</div>';
												}

												$_order ++;
											}

											echo '</div>';
										} else {
											echo '<div class="wooco_component_product_selection_grid">';

											foreach ( $component_products as $component_product ) {
												if ( $component_product_obj = wc_get_product( $component_product['id'] ) ) {
													$item_selected = apply_filters( 'wooco_component_product_selected', isset( $component['default'] ) && ( $component['default'] == $component_product['id'] ), $component_product, $component );
													echo '<div class="wooco_component_product_selection_grid_item wooco_component_product_selection_item ' . ( $item_selected || $one_required ? 'wooco_item_selected' : '' ) . '" ' . self::data_attributes( $component_product ) . '>';
													echo '<div class="wooco_component_product_selection_grid_item_image">' . wp_kses_post( $component_product_obj->get_image() ) . '</div>';
													echo '<div class="wooco_component_product_selection_grid_item_info">';
													echo '<div class="wooco_component_product_selection_grid_item_name">' . wp_kses_post( $component_product['name'] ) . '</div>';
													echo '<div class="wooco_component_product_selection_grid_item_desc">' . wp_kses_post( html_entity_decode( $component_product['description'] ) ) . '</div>';

													if ( $component_custom_qty ) {
														$min = 0;
														$max = 1000;
														$qty = $component['qty'];

														if ( ! empty( $component['min'] ) ) {
															$min = $component['min'];
														}

														if ( ! empty( $component['max'] ) ) {
															$max = $component['max'];
														}

														if ( class_exists( 'WPCleverWoopq' ) && ( get_option( '_woopq_decimal', 'no' ) === 'yes' ) ) {
															$step = get_option( '_woopq_step' ) ?: '1';
														} else {
															$step = '1';
															$qty  = (int) $qty;
															$min  = (int) $min;
															$max  = (int) $max;
														}

														if ( ( $max_purchase = $component_product_obj->get_max_purchase_quantity() ) && ( $max_purchase > 0 ) && ( $max_purchase < $max ) ) {
															// get_max_purchase_quantity can return -1
															$max = $max_purchase;
														}

														echo '<div class="wooco_component_product_selection_grid_item_qty wooco_component_product_selection_item_qty wooco-qty">';
														echo '<span class="wooco-qty-label">' . esc_html( self::localization( 'qty_label', esc_html__( 'Qty:', 'wpc-composite-products' ) ) ) . '</span>';
														echo '<span class="wooco-qty-input">';
														echo '<span class="wooco_component_product_qty_btn wooco_component_product_qty_minus wooco-minus">-</span>';
														echo woocommerce_quantity_input( [
															'input_value' => $qty,
															'min_value'   => $min,
															'max_value'   => $max,
															'wooco_qty'   => [
																'input_value' => $qty,
																'min_value'   => $min,
																'max_value'   => $max
															],
															'classes'     => apply_filters( 'wooco_qty_classes', [
																'input-text',
																'wooco_component_product_qty_input',
																'qty',
																'text'
															], $component ),
															'input_name'  => 'wooco_qty_' . $order . '_' . $_order
															// compatible with WPC Product Quantity
														], $component_product_obj, false );
														echo '<span class="wooco_component_product_qty_btn wooco_component_product_qty_plus wooco-plus">+</span>';
														echo '</span>';
														echo '</div>';

														//echo wp_kses_post( apply_filters( 'wooco_qty_input', $qty_input, $qty, $step, $min, $max ) );
													}

													echo '</div>';

													if ( $product_link !== 'no' ) {
														if ( $component_product_obj->is_visible() || apply_filters( 'wooco_hidden_product_link', false ) ) {
															$quickview_id = is_a( $component_product_obj, 'WC_Product_Variation' ) && ( apply_filters( 'wooco_quickview_variation', 'default' ) === 'parent' ) ? $component_product_obj->get_parent_id() : $component_product['id'];
															echo '<a ' . ( $product_link === 'yes_popup' ? 'class="wooco_component_product_selection_grid_item_link woosq-link" data-id="' . esc_attr( $quickview_id ) . '" data-context="wooco"' : 'class="wooco_component_product_selection_grid_item_link"' ) . ' href="' . esc_url( $component_product_obj->get_permalink() ) . '" ' . ( $product_link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . esc_html( $component_product_obj->get_name() ) . '</a>';
														}
													}

													echo '</div>';
												}

												$_order ++;
											}

											echo '</div>';
										}
									} else { ?>
                                        <label for="<?php echo esc_attr( 'wooco_component_product_select_' . $order ); ?>"></label>
                                        <select class="wooco_component_product_select" id="<?php echo esc_attr( 'wooco_component_product_select_' . $order ); ?>">
											<?php
											if ( ! $checkbox && ( ! $component_required || $option_none_required ) && ! $one_required ) {
												echo '<option value="-1" ' . self::data_attributes( $option_none_data ) . '>' . esc_html( $option_none_label ) . '</option>';
											}

											foreach ( $component_products as $component_product ) {
												echo '<option value="' . esc_attr( $component_product['purchasable'] === 'yes' ? $component_product['id'] : 0 ) . '" ' . self::data_attributes( $component_product ) . ' ' . selected( $component['default'], $component_product['id'], false ) . ' ' . esc_attr( $component_product['purchasable'] !== 'yes' ? 'disabled' : '' ) . '>' . esc_html( $component_product['name'] ) . '</option>';
											}
											?>
                                        </select>
									<?php } ?>
                                </div>

								<?php
								if ( ( $selector === 'select' ) && $show_availability ) {
									echo '<div class="wooco_component_product_availability"></div>';
								}

								if ( ( $selector === 'select' ) && $show_price ) {
									echo '<div class="wooco_component_product_price"></div>';
								}

								if ( $component_custom_qty && $component_dropdown ) {
									$min = 0;
									$max = 1000;
									$qty = $component['qty'];

									if ( ! empty( $component['min'] ) ) {
										$min = $component['min'];
									}

									if ( ! empty( $component['max'] ) ) {
										$max = $component['max'];
									}

									if ( class_exists( 'WPCleverWoopq' ) && ( get_option( '_woopq_decimal', 'no' ) === 'yes' ) ) {
										$step = get_option( '_woopq_step' ) ?: '1';
									} else {
										$step = '1';
										$qty  = (int) $qty;
										$min  = (int) $min;
										$max  = (int) $max;
									}

									echo '<div class="wooco_component_product_qty wooco-qty">';
									echo '<span class="wooco-qty-label">' . esc_html( self::localization( 'qty_label', esc_html__( 'Qty:', 'wpc-composite-products' ) ) ) . '</span>';
									echo '<span class="wooco-qty-input">';
									echo '<span class="wooco_component_product_qty_btn wooco_component_product_qty_minus wooco-minus">-</span>';
									echo '<input class="wooco_component_product_qty_input input-text text qty" type="number" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $qty ) . '"/>';
									echo '<span class="wooco_component_product_qty_btn wooco_component_product_qty_plus wooco-plus">+</span>';
									echo '</span>';
									echo '</div>';

									//echo wp_kses_post( apply_filters( 'wooco_qty_input', $qty_input, $qty, $step, $min, $max ) );
								}
								?>
                            </div>
							<?php
						}

						do_action( 'wooco_after_component', $component, $order );
						echo '</div>';
						$order ++;
					}
					?>
                </div>
				<?php
				echo '<div class="wooco_summary wooco-summary wooco-text"><div class="wooco_total wooco-total"></div><div class="wooco_count wooco-count"></div></div>';

				if ( self::get_setting( 'show_alert', 'load' ) !== 'no' ) {
					echo '<div class="wooco_alert wooco-alert wooco-text" style="display: none"></div>';
				}

				do_action( 'wooco_after_components', $product );

				if ( $after_text = apply_filters( 'wooco_after_text', get_post_meta( $product_id, 'wooco_after_text', true ), $product_id ) ) {
					echo '<div class="wooco_after_text wooco-after-text wooco-text">' . wp_kses_post( do_shortcode( $after_text ) ) . '</div>';
				}

				echo '</div>';
			}

			do_action( 'wooco_after_wrap', $product );
		}

		function get_product_id_by_sku( $id = null ) {
			if ( ! is_numeric( $id ) ) {
				return wc_get_product_id_by_sku( $id );
			}

			return $id;
		}

		function get_products( $type, $val, $orderby, $order, $exclude = [], $default = 0, $qty = 1, $price = '', $custom_qty = false ) {
			$has_default           = false;
			$products              = $_products = [];
			$val_arr               = array_unique( ! is_array( $val ) ? array_map( 'trim', explode( ',', $val ) ) : $val );
			$exclude_ids           = $type != 'products' ? ( ! is_array( $exclude ) ? explode( ',', $exclude ) : $exclude ) : [];
			$exclude_ids           = array_map( [ $this, 'get_product_id_by_sku' ], $exclude_ids );
			$limit                 = apply_filters( 'wooco_limit', - 1 );
			$exclude_hidden        = apply_filters( 'wooco_exclude_hidden', self::get_setting( 'exclude_hidden', 'no' ) === 'yes' );
			$exclude_unpurchasable = apply_filters( 'wooco_exclude_unpurchasable', self::get_setting( 'exclude_unpurchasable', 'yes' ) === 'yes' );

			if ( $orderby === 'name' ) {
				$orderby = 'title';
			}

			if ( ! is_numeric( $default ) ) {
				$default = wc_get_product_id_by_sku( $default );
			}

			if ( $type !== 'products' ) {
				return $products;
			}

			if ( apply_filters( 'wooco_use_wc_get_products', true ) ) {
				// query args
				if ( $type === 'products' ) {
					if ( ( $orderby === 'default' ) && ( $order === 'DESC' ) ) {
						$val_arr = array_reverse( $val_arr );
					}

					$val_arr = array_map( [ $this, 'get_product_id_by_sku' ], $val_arr );

					if ( $orderby === 'default' ) {
						$orderby = 'post__in';
					}

					$args = [
						'is_wooco' => true,
						'type'     => array_merge( [ 'variation' ], array_keys( wc_get_product_types() ) ),
						'include'  => $val_arr,
						'orderby'  => $orderby,
						'order'    => $order,
						'limit'    => $limit
					];
				} else {
					$args = [
						'is_wooco'  => true,
						'orderby'   => $orderby,
						'order'     => $order,
						'limit'     => $limit,
						'tax_query' => [
							[
								'taxonomy' => $type,
								'field'    => 'slug',
								'terms'    => $val_arr,
								'operator' => 'IN',
							]
						]
					];
				}

				// order by price
				if ( $orderby === 'price' ) {
					$args['orderby']  = 'meta_value_num';
					$args['meta_key'] = '_price';
				}

				$args['status'] = [ 'publish' ];

				// filter
				$args = apply_filters( 'wooco_wc_get_products_args', $args );

				// query products
				$_products = apply_filters( 'wooco_wc_get_products', wc_get_products( $args ), $type, $val_arr, $orderby, $order, $limit );
			}

			if ( empty( $_products ) && apply_filters( 'wooco_use_wp_get_posts', true ) ) {
				// try get_posts in some cases get_products does not work
				if ( $type === 'products' ) {
					$args = apply_filters( 'wooco_wp_get_posts_args', [
						'is_wooco'       => true,
						'fields'         => 'ids',
						'post_type'      => [ 'product', 'product_variation' ],
						'post_status'    => [ 'publish' ],
						'include'        => $val_arr,
						'orderby'        => $orderby,
						'order'          => $order,
						'posts_per_page' => $limit
					] );
				} else {
					$args = apply_filters( 'wooco_wp_get_posts_args', [
						'is_wooco'       => true,
						'fields'         => 'ids',
						'post_type'      => [ 'product', 'product_variation' ],
						'post_status'    => [ 'publish' ],
						'tax_query'      => [
							[
								'taxonomy' => $type,
								'field'    => 'slug',
								'terms'    => $val_arr,
								'operator' => 'IN',
							]
						],
						'orderby'        => $orderby,
						'order'          => $order,
						'posts_per_page' => $limit
					] );
				}

				$_posts = apply_filters( 'wooco_wp_get_posts', get_posts( $args ), $type, $val_arr, $orderby, $order, $limit );

				if ( ! empty( $_posts ) && is_array( $_posts ) ) {
					$_products = array_map( 'wc_get_product', $_posts );
				}

				$_products = apply_filters( 'wooco_pre_get_products', $_products, $type, $val_arr, $orderby, $order, $limit );
			}

			if ( is_array( $_products ) && ! empty( $_products ) ) {
				foreach ( $_products as $_product ) {
					if ( $_product->is_type( 'composite' ) ) {
						continue;
					}

					$_product_id = $_product->get_id();

					if ( ( $type === 'products' ) && ! in_array( $_product_id, $val_arr ) && ( $_product_id != $default ) ) {
						continue;
					}

					if ( in_array( $_product_id, $exclude_ids ) ) {
						continue;
					}

					if ( ! apply_filters( 'wooco_product_visible', true, $_product ) || ( ! $_product->is_visible() && $exclude_hidden ) ) {
						continue;
					}

					if ( $_product->is_type( 'variable' ) ) {
						$children = $_product->get_children();

						if ( ! empty( $children ) ) {
							foreach ( $children as $child ) {
								if ( in_array( $child, $exclude_ids ) ) {
									continue;
								}

								$child_product = wc_get_product( $child );

								if ( ! $child_product || ( ! $child_product->variation_is_visible() && $exclude_hidden ) || ( $exclude_unpurchasable && ! self::is_purchasable( $child_product, $qty ) ) ) {
									continue;
								}

								if ( apply_filters( 'wooco_check_variation_tag', false ) && ( $type === 'product_tag' ) ) {
									// check variation tag
									if ( ! has_term( $val_arr, 'product_tag', $child ) ) {
										continue;
									}
								}

								if ( apply_filters( 'wooco_check_variation_attribute', true ) && ( str_starts_with( $type, 'pa_' ) ) ) {
									// check variation attribute
									$attrs = $child_product->get_attributes();

									if ( ! isset( $attrs[ $type ] ) || ( ! in_array( $attrs[ $type ], $val_arr ) ) ) {
										continue;
									}
								}

								$products[ 'pid_' . $child ] = self::get_product_data( $child_product, $qty, $price, $custom_qty );

								if ( $child == $default ) {
									$has_default = true;
								}
							}
						}
					} else {
						if ( $exclude_unpurchasable && ! self::is_purchasable( $_product, $qty ) ) {
							continue;
						}

						$products[ 'pid_' . $_product_id ] = self::get_product_data( $_product, $qty, $price, $custom_qty );

						if ( $_product_id == $default ) {
							$has_default = true;
						}
					}
				}

				if ( ! $has_default ) {
					// add default product
					if ( $product_default = wc_get_product( $default ) ) {
						if ( $product_default->is_type( 'variable' ) ) {
							// select a available variation
							$available_variations = $product_default->get_available_variations();

							if ( count( $available_variations ) > 0 ) {
								$sort_default_variations = apply_filters( 'wooco_sort_default_variations', 'default' );

								if ( $sort_default_variations === 'price_asc' ) {
									$display_price = array_column( $available_variations, 'display_price' );

									array_multisort( $display_price, SORT_ASC, $available_variations );
								}

								if ( $sort_default_variations === 'price_desc' ) {
									$display_price = array_column( $available_variations, 'display_price' );

									array_multisort( $display_price, SORT_DESC, $available_variations );
								}

								foreach ( array_reverse( $available_variations ) as $available_variation ) {
									$available_variation_id      = $available_variation['variation_id'];
									$available_variation_product = wc_get_product( $available_variation_id );

									if ( ! $available_variation_product || ( ! $available_variation_product->variation_is_visible() && $exclude_hidden ) || ( $exclude_unpurchasable && ! self::is_purchasable( $available_variation_product, $qty ) ) ) {
										continue;
									}

									// add variation
									$products = [ 'pid_' . $available_variation_id => self::get_product_data( $available_variation_product, $qty, $price, $custom_qty ) ] + $products;
								}

								foreach ( $available_variations as $available_variation ) {
									$available_variation_id      = $available_variation['variation_id'];
									$available_variation_product = wc_get_product( $available_variation_id );

									if ( $available_variation_product && self::is_purchasable( $available_variation_product, $qty ) ) {
										// select default variation
										$products = [ 'pid_' . $available_variation_id => self::get_product_data( $available_variation_product, $qty, $price, $custom_qty ) ] + $products;
										break;
									}
								}
							}
						} else {
							if ( self::is_purchasable( $product_default, $qty ) || ! $exclude_unpurchasable ) {
								$products = [ 'pid_' . $default => self::get_product_data( $product_default, $qty, $price, $custom_qty ) ] + $products;
							}
						}
					}
				}
			}

			return apply_filters( 'wooco_get_products', $products, $type, $val, $orderby, $order, $exclude, $default, $qty, $price, $custom_qty );
		}

		function is_purchasable( $product, $qty ) {
			return $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( $qty ) && ( 'trash' !== $product->get_status() );
		}

		function get_product_data( $product, $qty = 1, $price = '', $custom_qty = false ) {
			// settings
			$show_price             = self::get_setting( 'show_price', 'yes' ) === 'yes';
			$show_availability      = self::get_setting( 'show_availability', 'yes' ) === 'yes';
			$show_short_description = self::get_setting( 'show_short_description', 'no' ) === 'yes';
			$show_image             = self::get_setting( 'show_image', 'yes' ) === 'yes';
			$show_qty               = self::get_setting( 'show_qty', 'yes' ) === 'yes';

			if ( $show_image ) {
				if ( $product_image_id = $product->get_image_id() ) {
					$img             = wp_get_attachment_image_src( $product_image_id, self::$image_size );
					$img_full        = wp_get_attachment_image_src( $product_image_id, 'full' );
					$img_gallery     = wp_get_attachment_image_src( $product_image_id, 'woocommerce_gallery_thumbnail' );
					$img_src         = $img[0] ?? wc_placeholder_img_src();
					$img_full_src    = $img_full[0] ?? wc_placeholder_img_src();
					$img_gallery_src = $img_gallery[0] ?? wc_placeholder_img_src();
				} else {
					$img_src = $img_full_src = $img_gallery_src = wc_placeholder_img_src();
				}
			} else {
				$img_src = $img_full_src = $img_gallery_src = '';
			}

			$price_ori     = apply_filters( 'wooco_product_original_price', ( self::get_setting( 'product_price', 'sale_price' ) === 'regular_price' ) ? $product->get_regular_price() : $product->get_price(), $product );
			$price_display = wc_get_price_to_display( $product, [ 'price' => $price_ori ] );
			$price_html    = apply_filters( 'wooco_product_original_price_html', $product->get_price_html(), $product );

			if ( $price !== '' ) {
				// new price
				$new_price = self::get_new_price( $price_ori, $price );

				if ( $new_price !== (float) $price_ori ) {
					$price_display = wc_get_price_to_display( $product, [ 'price' => $new_price ] );
					$price_html    = wc_format_sale_price( wc_get_price_to_display( $product, [ 'price' => $price_ori ] ), $price_display );
				}
			}

			if ( ! $custom_qty && $show_qty ) {
				$name = $qty . ' &times; ' . $product->get_name();
			} else {
				$name = $product->get_name();
			}

			$desc = '';

			if ( $show_price ) {
				$desc .= '<span>' . $price_html . '</span>';
			}

			if ( $show_availability ) {
				$desc .= '<span>' . wc_get_stock_html( $product ) . '</span>';
			}

			if ( $show_short_description ) {
				if ( $product->is_type( 'variation' ) ) {
					$desc .= '<div class="wooco_component_product_short_description wooco_component_variation_short_description">' . $product->get_description() . '</div>';
				} else {
					$desc .= '<div class="wooco_component_product_short_description">' . $product->get_short_description() . '</div>';
				}
			}

			return apply_filters( 'wooco_product_data', [
				'id'            => $product->get_id(),
				'qty'           => $qty,
				'pid'           => $product->is_type( 'variation' ) && $product->get_parent_id() ? $product->get_parent_id() : 0,
				'purchasable'   => apply_filters( 'wooco_product_purchasable', ( self::is_purchasable( $product, $qty ) ? 'yes' : 'no' ), $product, $qty, $price ),
				'link'          => apply_filters( 'wooco_product_link', ( ! $product->is_visible() && ! apply_filters( 'wooco_hidden_product_link', false ) ? '' : $product->get_permalink() ), $product, $qty, $price ),
				'name'          => apply_filters( 'wooco_product_name', $name, $product, $qty, $price ),
				'price'         => apply_filters( 'wooco_product_price', $price_display, $product, $qty, $price ),
				'new-price'     => apply_filters( 'wooco_product_new_price', $price, $product, $qty, $price ),
				'regular-price' => apply_filters( 'wooco_product_regular_price', wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $product, $qty, $price ),
				'regular_price' => apply_filters( 'wooco_product_regular_price', wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $product, $qty, $price ),
				'price-html'    => apply_filters( 'wooco_product_price_html', htmlentities( $price_html ), $product, $qty, $price ),
				'price_html'    => apply_filters( 'wooco_product_price_html', htmlentities( $price_html ), $product, $qty, $price ),
				'description'   => apply_filters( 'wooco_product_description', htmlentities( $desc ), $product, $qty, $price ),
				'availability'  => apply_filters( 'wooco_product_availability', htmlentities( wc_get_stock_html( $product ) ), $product, $qty, $price ),
				'image'         => apply_filters( 'wooco_product_image', $img_src, $product, $qty, $price ),
				'imagesrc'      => apply_filters( 'wooco_product_image', $img_src, $product, $qty, $price ),
				'imagefull'     => apply_filters( 'wooco_product_image_full', $img_full_src, $product, $qty, $price ),
				'image_full'    => apply_filters( 'wooco_product_image_full', $img_full_src, $product, $qty, $price ),
				'image_gallery' => apply_filters( 'wooco_product_image_gallery', $img_gallery_src, $product, $qty, $price ),
			], $product, $qty, $price );
		}

		function export_process( $value, $meta, $product ) {
			if ( $meta->key === 'wooco_components' ) {
				$components = get_post_meta( $product->get_id(), 'wooco_components', true );

				if ( ! empty( $components ) && is_array( $components ) ) {
					return json_encode( $components );
				}
			}

			return $value;
		}

		function import_process( $object, $data ) {
			if ( isset( $data['meta_data'] ) ) {
				foreach ( $data['meta_data'] as $meta ) {
					if ( $meta['key'] === 'wooco_components' ) {
						$object->update_meta_data( 'wooco_components', json_decode( $meta['value'], true ) );
						break;
					}
				}
			}

			return $object;
		}

		function wpcsm_locations( $locations ) {
			$locations['WPC Composite Products'] = [
				'wooco_before_wrap'       => esc_html__( 'Before container', 'wpc-composite-products' ),
				'wooco_after_wrap'        => esc_html__( 'After container', 'wpc-composite-products' ),
				'wooco_before_components' => esc_html__( 'Before component list', 'wpc-composite-products' ),
				'wooco_after_components'  => esc_html__( 'After component list', 'wpc-composite-products' ),
				'wooco_before_component'  => esc_html__( 'Before component', 'wpc-composite-products' ),
				'wooco_after_component'   => esc_html__( 'After component', 'wpc-composite-products' ),
			];

			return $locations;
		}

		function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
				}
			}

			return $arr;
		}

		function clean_ids( $ids ) {
			$ids = preg_replace( '/[^,.%\/0-9a-zA-Z]/', '', $ids );

			return $ids;
		}

		function data_attributes( $attrs ) {
			$attrs_arr = [];

			foreach ( $attrs as $key => $attr ) {
				$attrs_arr[] = esc_attr( 'data-' . sanitize_title( $key ) ) . '="' . esc_attr( $attr ) . '"';
			}

			return implode( ' ', $attrs_arr );
		}

		public static function localization( $key = '', $default = '' ) {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return apply_filters( 'wooco_localization_' . $key, $str );
		}

		public static function get_items( $ids ) {
			$arr = [];

			if ( ! empty( $ids ) ) {
				$items = explode( ',', $ids );

				if ( is_array( $items ) && count( $items ) > 0 ) {
					foreach ( $items as $item ) {
						$item_arr = explode( '/', $item );
						$arr[]    = [
							'id'  => absint( $item_arr[0] ?? 0 ),
							'qty' => (float) ( $item_arr[1] ?? 1 ),
							'key' => sanitize_key( $item_arr[2] ?? '' )
						];
					}
				}
			}

			return apply_filters( 'wooco_get_items', $arr, $ids );
		}

		public static function format_price( $price ) {
			// format price to percent or number
			return preg_replace( '/[^.%0-9]/', '', $price );
		}

		public static function get_new_price( $old_price, $new_price ) {
			if ( str_contains( $new_price, '%' ) ) {
				$calc_price = ( (float) $new_price * $old_price ) / 100;
			} else {
				$calc_price = (float) $new_price;
			}

			return $calc_price;
		}

		public static function get_discount( $number ) {
			$discount = 0;

			if ( is_numeric( $number ) && ( (float) $number < 100 ) && ( (float) $number > 0 ) ) {
				$discount = (float) $number;
			}

			return $discount;
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'wooco_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'wooco_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'wooco_generate_key', $key );
		}
	}

	return WPCleverWooco::instance();
}
