<?php
/**
 * Bundle product data panels and saving.
 *
 * @package WCProductBundler
 */

namespace WCPB\Admin;

use WCPB\Support\Product_Data;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Product data admin UI for bundle products.
 */
class Bundle_Product_Data {
	/**
	 * Shared product data helper.
	 *
	 * @var Product_Data
	 */
	private $product_data;

	/**
	 * Constructor.
	 *
	 * @param Product_Data $product_data Product data helper.
	 */
	public function __construct(Product_Data $product_data) {
		$this->product_data = $product_data;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action('woocommerce_product_options_general_product_data', array($this, 'render_general_fields'));
		add_action('woocommerce_product_data_panels', array($this, 'render_bundle_panel'));
		add_action('woocommerce_admin_process_product_object', array($this, 'save_product'));
		add_action('woocommerce_process_product_meta_bundle', array($this, 'persist_bundle_meta'), 20);
	}

	/**
	 * Add base bundle price field to the general tab.
	 *
	 * @return void
	 */
	public function render_general_fields() {
		echo '<div class="options_group show_if_bundle">';

		woocommerce_wp_select(
			array(
				'id'          => Product_Data::PRICING_MODE_META,
				'label'       => __('Bundle pricing', 'wc-product-bundler'),
				'value'       => $this->product_data->get_pricing_mode(get_the_ID()),
				'options'     => array(
					'fixed_price'      => __('Fixed bundle price', 'wc-product-bundler'),
					'percent_discount' => __('Percentage discount', 'wc-product-bundler'),
					'fixed_discount'   => __('Fixed discount amount', 'wc-product-bundler'),
				),
				'description' => __('Choose whether the bundle uses a fixed sell price or a discount off the bundled items subtotal.', 'wc-product-bundler'),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Product_Data::BASE_PRICE_META,
				'label'             => __('Bundle fixed price', 'wc-product-bundler'),
				'desc_tip'          => true,
				'description'       => __('Used when Bundle pricing is set to Fixed bundle price.', 'wc-product-bundler'),
				'type'              => 'price',
				'value'             => $this->get_saved_base_price(get_the_ID()),
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Product_Data::DISCOUNT_VALUE_META,
				'label'             => __('Bundle discount value', 'wc-product-bundler'),
				'desc_tip'          => true,
				'description'       => __('Used for Percentage discount and Fixed discount amount pricing. Enter a percent like 10 or a currency amount like 15.00.', 'wc-product-bundler'),
				'type'              => 'number',
				'value'             => $this->product_data->get_discount_value(get_the_ID()),
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => Product_Data::LAYOUT_META,
				'label'       => __('Bundle layout', 'wc-product-bundler'),
				'value'       => $this->product_data->get_layout_style(get_the_ID()),
				'options'     => array(
					'stacked' => __('Stacked list', 'wc-product-bundler'),
					'compact' => __('Compact summary', 'wc-product-bundler'),
				),
				'description' => __('Controls the storefront presentation of included items and optional add-ons.', 'wc-product-bundler'),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => Product_Data::STYLE_META,
				'label'       => __('Bundle style', 'wc-product-bundler'),
				'value'       => $this->product_data->get_style_variant(get_the_ID()),
				'options'     => array(
					'default' => __('Default blue', 'wc-product-bundler'),
					'slate'   => __('Slate neutral', 'wc-product-bundler'),
					'emerald' => __('Emerald green', 'wc-product-bundler'),
				),
				'description' => __('Choose a Tailwind-inspired storefront style variant for the bundle summary.', 'wc-product-bundler'),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => Product_Data::SHOW_SAVINGS_META,
				'label'       => __('Bundle savings', 'wc-product-bundler'),
				'description' => __('Show a "You save" amount on the storefront bundle card when the bundle has a discount.', 'wc-product-bundler'),
				'value'       => $this->product_data->should_show_savings(get_the_ID()) ? 'yes' : 'no',
			)
		);

		echo '</div>';
	}

	/**
	 * Render bundle configuration tab content.
	 *
	 * @return void
	 */
	public function render_bundle_panel() {
		global $post;

		$product_id     = $post instanceof \WP_Post ? $post->ID : 0;
		$included_items = $this->product_data->get_bundle_items($product_id);
		$optional_items = $this->product_data->get_optional_items($product_id);

		include WCPB_PATH . 'templates/admin-bundle-panel.php';
	}

	/**
	 * Save bundle fields through the CRUD object.
	 *
	 * @param \WC_Product $product Product object being saved.
	 * @return void
	 */
	public function save_product($product) {
		$posted_type = isset($_POST['product-type']) ? sanitize_key(wp_unslash($_POST['product-type'])) : '';

		if (! ($product instanceof \WC_Product) || ('bundle' !== $product->get_type() && 'bundle' !== $posted_type)) {
			return;
		}

		$included_items = $this->product_data->sanitize_items(isset($_POST['wcpb_bundle_items']) ? wp_unslash($_POST['wcpb_bundle_items']) : array(), 'included');
		$optional_items = $this->product_data->sanitize_items(isset($_POST['wcpb_optional_items']) ? wp_unslash($_POST['wcpb_optional_items']) : array(), 'optional');
		$base_price     = isset($_POST[ Product_Data::BASE_PRICE_META ]) ? wc_format_decimal(wp_unslash($_POST[ Product_Data::BASE_PRICE_META ])) : '';
		$pricing_mode   = isset($_POST[ Product_Data::PRICING_MODE_META ]) ? $this->product_data->sanitize_pricing_mode(sanitize_key(wp_unslash($_POST[ Product_Data::PRICING_MODE_META ]))) : 'fixed_price';
		$discount_value = isset($_POST[ Product_Data::DISCOUNT_VALUE_META ]) ? wc_format_decimal(wp_unslash($_POST[ Product_Data::DISCOUNT_VALUE_META ])) : '';
		$layout_style   = isset($_POST[ Product_Data::LAYOUT_META ]) ? sanitize_key(wp_unslash($_POST[ Product_Data::LAYOUT_META ])) : 'stacked';
		$style_variant  = isset($_POST[ Product_Data::STYLE_META ]) ? $this->product_data->sanitize_style_variant(sanitize_key(wp_unslash($_POST[ Product_Data::STYLE_META ]))) : 'default';
		$show_savings   = ! empty($_POST[ Product_Data::SHOW_SAVINGS_META ]) ? 'yes' : 'no';

		$errors = array_merge(
			$this->product_data->validate_items($included_items),
			$this->product_data->validate_items($optional_items)
		);

		if (empty($included_items)) {
			$errors[] = __('A bundle must contain at least one included item.', 'wc-product-bundler');
		}

		if (! empty($errors)) {
			foreach (array_unique($errors) as $error) {
				\WC_Admin_Meta_Boxes::add_error($error);
			}

			return;
		}

		$product->update_meta_data(Product_Data::BUNDLE_ITEMS_META, $included_items);
		$product->update_meta_data(Product_Data::OPTIONAL_ITEMS_META, $optional_items);
		$product->update_meta_data(Product_Data::BASE_PRICE_META, $base_price);
		$product->update_meta_data(Product_Data::PRICING_MODE_META, $pricing_mode);
		$product->update_meta_data(Product_Data::DISCOUNT_VALUE_META, $discount_value);
		$product->update_meta_data(Product_Data::LAYOUT_META, in_array($layout_style, array('stacked', 'compact'), true) ? $layout_style : 'stacked');
		$product->update_meta_data(Product_Data::STYLE_META, $style_variant);
		$product->update_meta_data(Product_Data::SHOW_SAVINGS_META, $show_savings);

		$catalog_price = $this->get_catalog_price($included_items, $pricing_mode, $base_price, $discount_value);

		$product->set_regular_price($catalog_price);
		$product->set_price($catalog_price);
	}

	/**
	 * Persist bundle meta after WooCommerce has finalized the saved product type.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function persist_bundle_meta($post_id) {
		$product = wc_get_product($post_id);

		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type()) {
			return;
		}

		$base_price = isset($_POST[ Product_Data::BASE_PRICE_META ]) ? wc_format_decimal(wp_unslash($_POST[ Product_Data::BASE_PRICE_META ])) : '';
		$pricing_mode = isset($_POST[ Product_Data::PRICING_MODE_META ]) ? $this->product_data->sanitize_pricing_mode(sanitize_key(wp_unslash($_POST[ Product_Data::PRICING_MODE_META ]))) : 'fixed_price';
		$discount_value = isset($_POST[ Product_Data::DISCOUNT_VALUE_META ]) ? wc_format_decimal(wp_unslash($_POST[ Product_Data::DISCOUNT_VALUE_META ])) : '';

		update_post_meta($post_id, Product_Data::BASE_PRICE_META, $base_price);
		update_post_meta($post_id, Product_Data::PRICING_MODE_META, $pricing_mode);
		update_post_meta($post_id, Product_Data::DISCOUNT_VALUE_META, $discount_value);
		update_post_meta($post_id, Product_Data::LAYOUT_META, $this->get_posted_layout_style());
		update_post_meta($post_id, Product_Data::STYLE_META, $this->get_posted_style_variant());
		update_post_meta($post_id, Product_Data::SHOW_SAVINGS_META, ! empty($_POST[ Product_Data::SHOW_SAVINGS_META ]) ? 'yes' : 'no');
		update_post_meta(
			$post_id,
			Product_Data::BUNDLE_ITEMS_META,
			$this->product_data->sanitize_items(isset($_POST['wcpb_bundle_items']) ? wp_unslash($_POST['wcpb_bundle_items']) : array(), 'included')
		);
		update_post_meta(
			$post_id,
			Product_Data::OPTIONAL_ITEMS_META,
			$this->product_data->sanitize_items(isset($_POST['wcpb_optional_items']) ? wp_unslash($_POST['wcpb_optional_items']) : array(), 'optional')
		);

		$included_items = $this->product_data->sanitize_items(isset($_POST['wcpb_bundle_items']) ? wp_unslash($_POST['wcpb_bundle_items']) : array(), 'included');
		$catalog_price  = $this->get_catalog_price($included_items, $pricing_mode, $base_price, $discount_value);

		$product->set_regular_price($catalog_price);
		$product->set_price($catalog_price);
		$product->save();
	}

	/**
	 * Resolve the saved base price for the editor field.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function get_saved_base_price($product_id) {
		$base_price = get_post_meta($product_id, Product_Data::BASE_PRICE_META, true);

		if ('' !== $base_price) {
			return $base_price;
		}

		$product = wc_get_product($product_id);

		return $product instanceof \WC_Product ? (string) $product->get_regular_price('edit') : '';
	}

	/**
	 * Get the posted bundle layout style safely.
	 *
	 * @return string
	 */
	private function get_posted_layout_style() {
		$layout_style = isset($_POST[ Product_Data::LAYOUT_META ]) ? sanitize_key(wp_unslash($_POST[ Product_Data::LAYOUT_META ])) : 'stacked';

		return in_array($layout_style, array('stacked', 'compact'), true) ? $layout_style : 'stacked';
	}

	/**
	 * Get the posted bundle style variant safely.
	 *
	 * @return string
	 */
	private function get_posted_style_variant() {
		$style_variant = isset($_POST[ Product_Data::STYLE_META ]) ? sanitize_key(wp_unslash($_POST[ Product_Data::STYLE_META ])) : 'default';

		return $this->product_data->sanitize_style_variant($style_variant);
	}

	/**
	 * Calculate the saved catalog price for the bundle product record.
	 *
	 * @param array<int, array<string, mixed>> $included_items Included bundle items.
	 * @param string                           $pricing_mode Pricing mode.
	 * @param string                           $base_price Fixed bundle price.
	 * @param string                           $discount_value Discount input.
	 * @return string
	 */
	private function get_catalog_price(array $included_items, $pricing_mode, $base_price, $discount_value) {
		$subtotal = 0.0;

		foreach ($included_items as $item) {
			$product = $this->product_data->get_resolved_product($item);

			if (! ($product instanceof \WC_Product)) {
				continue;
			}

			$subtotal += (float) wc_format_decimal($product->get_price()) * max(1, (int) ($item['default_qty'] ?? 1));
		}

		if ('fixed_price' === $pricing_mode) {
			return '' !== $base_price ? $base_price : wc_format_decimal($subtotal);
		}

		if ('percent_discount' === $pricing_mode) {
			$discount_percent = min(max((float) $discount_value, 0), 100);

			return wc_format_decimal(max(0, $subtotal - ($subtotal * ($discount_percent / 100))));
		}

		return wc_format_decimal(max(0, $subtotal - max(0, (float) $discount_value)));
	}
}
