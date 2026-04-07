<?php
/**
 * Registers the bundle product type with WooCommerce.
 *
 * @package WCProductBundler
 */

namespace WCPB\Product;

use WCPB\Support\Product_Data;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Bundle product type integration.
 */
class Bundle_Product_Type {
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
	 * Register product type hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter('product_type_selector', array($this, 'register_product_type'));
		add_filter('woocommerce_product_class', array($this, 'map_product_class'), 10, 2);
		add_filter('woocommerce_product_data_tabs', array($this, 'filter_product_data_tabs'));
		add_filter('woocommerce_product_get_price', array($this, 'filter_bundle_price'), 10, 2);
		add_filter('woocommerce_product_get_regular_price', array($this, 'filter_bundle_price'), 10, 2);
		add_filter('woocommerce_product_is_purchasable', array($this, 'filter_purchasable'), 10, 2);
		add_filter('woocommerce_product_add_to_cart_text', array($this, 'filter_add_to_cart_text'), 10, 2);
		add_filter('woocommerce_product_add_to_cart_url', array($this, 'filter_add_to_cart_url'), 10, 2);
		add_filter('woocommerce_loop_add_to_cart_args', array($this, 'filter_loop_add_to_cart_args'), 10, 2);
	}

	/**
	 * Add bundle to the product type selector.
	 *
	 * @param array<string, string> $types Product types.
	 * @return array<string, string>
	 */
	public function register_product_type($types) {
		$types['bundle'] = __('Bundle', 'wc-product-bundler');

		return $types;
	}

	/**
	 * Map the bundle product class.
	 *
	 * @param string $class_name Existing class.
	 * @param string $product_type Product type.
	 * @return string
	 */
	public function map_product_class($class_name, $product_type) {
		if ('bundle' === $product_type) {
			return Bundle_Product::class;
		}

		return $class_name;
	}

	/**
	 * Ensure bundle products expose the right product data tabs.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_product_data_tabs($tabs) {
		if (isset($tabs['shipping'])) {
			$tabs['shipping']['class'][] = 'show_if_bundle';
		}

		if (isset($tabs['inventory'])) {
			$tabs['inventory']['class'][] = 'hide_if_bundle';
		}

		$tabs['wcpb_bundle'] = array(
			'label'    => __('Bundled Items', 'wc-product-bundler'),
			'target'   => 'wcpb_bundle_options',
			'class'    => array('show_if_bundle'),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Surface the bundle base price from plugin meta.
	 *
	 * @param string     $price Existing price.
	 * @param \WC_Product $product Product instance.
	 * @return string
	 */
	public function filter_bundle_price($price, $product) {
		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type()) {
			return $price;
		}

		$bundle_price = $this->get_bundle_display_price($product->get_id());

		return '' !== $bundle_price ? $bundle_price : $price;
	}

	/**
	 * Determine if a bundle is purchasable.
	 *
	 * @param bool        $purchasable Purchasable flag.
	 * @param \WC_Product $product Product instance.
	 * @return bool
	 */
	public function filter_purchasable($purchasable, $product) {
		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type()) {
			return $purchasable;
		}

		return $product->exists() && '' !== $this->get_bundle_display_price($product->get_id()) && $purchasable;
	}

	/**
	 * Use a consistent add to cart label.
	 *
	 * @param string      $text Add to cart text.
	 * @param \WC_Product $product Product instance.
	 * @return string
	 */
	public function filter_add_to_cart_text($text, $product) {
		if ($product instanceof \WC_Product && 'bundle' === $product->get_type()) {
			return $this->bundle_requires_child_selection($product->get_id())
				? __('View bundle', 'wc-product-bundler')
				: __('Add bundle to cart', 'wc-product-bundler');
		}

		return $text;
	}

	/**
	 * Link configurable bundles to their product page instead of posting add-to-cart.
	 *
	 * @param string      $url Add to cart URL.
	 * @param \WC_Product $product Product instance.
	 * @return string
	 */
	public function filter_add_to_cart_url($url, $product) {
		if ($product instanceof \WC_Product && 'bundle' === $product->get_type() && $this->bundle_requires_child_selection($product->get_id())) {
			return $product->get_permalink();
		}

		return $url;
	}

	/**
	 * Remove loop add-to-cart behavior for configurable bundles while keeping button styling.
	 *
	 * @param array<string, mixed> $args Loop add to cart args.
	 * @param \WC_Product          $product Product instance.
	 * @return array<string, mixed>
	 */
	public function filter_loop_add_to_cart_args($args, $product) {
		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type() || ! $this->bundle_requires_child_selection($product->get_id())) {
			return $args;
		}

		$args['class'] = trim(
			preg_replace(
				'/\b(?:add_to_cart_button|ajax_add_to_cart)\b/',
				'',
				isset($args['class']) ? (string) $args['class'] : 'button'
			)
		);

		if (! empty($args['attributes']) && is_array($args['attributes'])) {
			unset($args['attributes']['data-product_id'], $args['attributes']['data-product_sku'], $args['attributes']['data-success_message'], $args['attributes']['role']);
		}

		return $args;
	}

	/**
	 * Calculate the displayed bundle price for catalog and product pages.
	 *
	 * @param int $product_id Bundle product ID.
	 * @return string
	 */
	private function get_bundle_display_price($product_id) {
		$included_items = $this->product_data->get_bundle_items($product_id);
		$pricing_mode   = $this->product_data->get_pricing_mode($product_id);
		$base_price     = $this->product_data->get_base_price($product_id);
		$discount_value = $this->product_data->get_discount_value($product_id);
		$subtotal       = $this->calculate_items_subtotal($included_items);

		if ('fixed_price' === $pricing_mode) {
			return '' !== $base_price ? $base_price : wc_format_decimal($subtotal);
		}

		if ('percent_discount' === $pricing_mode) {
			$discount = min(max((float) $discount_value, 0), 100);

			return wc_format_decimal(max(0, $subtotal - ($subtotal * ($discount / 100))));
		}

		return wc_format_decimal(max(0, $subtotal - max(0, (float) $discount_value)));
	}

	/**
	 * Calculate a subtotal for a list of bundle items.
	 *
	 * @param array<int, array<string, mixed>> $items Bundle items.
	 * @return float
	 */
	private function calculate_items_subtotal(array $items) {
		$subtotal = 0.0;

		foreach ($items as $item) {
			$product = $this->product_data->get_resolved_product($item);

			if (! ($product instanceof \WC_Product)) {
				continue;
			}

			$subtotal += (float) wc_format_decimal($product->get_price()) * max(1, (int) ($item['default_qty'] ?? 1));
		}

		return $subtotal;
	}

	/**
	 * Determine if any bundle child requires the shopper to choose a variation.
	 *
	 * @param int $product_id Bundle product ID.
	 * @return bool
	 */
	private function bundle_requires_child_selection($product_id) {
		$all_items = $this->product_data->get_all_items($product_id);

		foreach (array('included', 'optional') as $role) {
			if (empty($all_items[ $role ]) || ! is_array($all_items[ $role ])) {
				continue;
			}

			foreach ($all_items[ $role ] as $item) {
				if ($this->product_data->item_requires_variation_selection($item)) {
					return true;
				}
			}
		}

		return false;
	}
}
