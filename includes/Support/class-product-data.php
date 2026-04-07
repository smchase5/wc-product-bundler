<?php
/**
 * Bundle product data helper.
 *
 * @package WCProductBundler
 */

namespace WCPB\Support;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Shared bundle data access and normalization.
 */
class Product_Data {
	/**
	 * Included bundle items meta key.
	 */
	const BUNDLE_ITEMS_META = '_wc_pb_bundle_items';

	/**
	 * Optional add-on items meta key.
	 */
	const OPTIONAL_ITEMS_META = '_wc_pb_optional_items';

	/**
	 * Bundle price meta key.
	 */
	const BASE_PRICE_META = '_wc_pb_base_price';

	/**
	 * Bundle pricing mode meta key.
	 */
	const PRICING_MODE_META = '_wc_pb_pricing_mode';

	/**
	 * Bundle discount value meta key.
	 */
	const DISCOUNT_VALUE_META = '_wc_pb_discount_value';

	/**
	 * Layout style meta key.
	 */
	const LAYOUT_META = '_wc_pb_layout_style';

	/**
	 * Storefront style variant meta key.
	 */
	const STYLE_META = '_wc_pb_style_variant';

	/**
	 * Show savings badge meta key.
	 */
	const SHOW_SAVINGS_META = '_wc_pb_show_savings';

	/**
	 * Parent cart item meta key.
	 */
	const PARENT_KEY = '_wcpb_parent_key';

	/**
	 * Parent item id order meta key.
	 */
	const PARENT_ORDER_ITEM_META = '_wcpb_parent_order_item_id';

	/**
	 * Child role meta key.
	 */
	const CHILD_ROLE_META = '_wcpb_child_role';

	/**
	 * Bundle configuration meta key.
	 */
	const CONFIG_META = '_wcpb_bundle_configuration';

	/**
	 * Selected variation attributes key stored per bundle child item.
	 */
	const CHOSEN_ATTRIBUTES_KEY = 'chosen_attributes';

	/**
	 * Get included bundle items for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_bundle_items($product_id) {
		return $this->normalize_items(get_post_meta($product_id, self::BUNDLE_ITEMS_META, true), 'included');
	}

	/**
	 * Get optional bundle items for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_optional_items($product_id) {
		return $this->normalize_items(get_post_meta($product_id, self::OPTIONAL_ITEMS_META, true), 'optional');
	}

	/**
	 * Get all bundle items keyed by role.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_all_items($product_id) {
		return array(
			'included' => $this->get_bundle_items($product_id),
			'optional' => $this->get_optional_items($product_id),
		);
	}

	/**
	 * Resolve the bundle base price.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_base_price($product_id) {
		$price = get_post_meta($product_id, self::BASE_PRICE_META, true);

		return '' !== $price ? wc_format_decimal($price) : '';
	}

	/**
	 * Get the bundle pricing mode.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_pricing_mode($product_id) {
		$mode = get_post_meta($product_id, self::PRICING_MODE_META, true);

		return $this->sanitize_pricing_mode($mode);
	}

	/**
	 * Get the bundle discount value.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_discount_value($product_id) {
		$value = get_post_meta($product_id, self::DISCOUNT_VALUE_META, true);

		return '' !== $value ? wc_format_decimal($value) : '';
	}

	/**
	 * Get chosen layout style.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_layout_style($product_id) {
		$layout = get_post_meta($product_id, self::LAYOUT_META, true);

		return in_array($layout, array('stacked', 'compact'), true) ? $layout : 'stacked';
	}

	/**
	 * Get chosen storefront style variant.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_style_variant($product_id) {
		$variant = get_post_meta($product_id, self::STYLE_META, true);

		return $this->sanitize_style_variant($variant);
	}

	/**
	 * Determine whether the storefront savings badge should be shown.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function should_show_savings($product_id) {
		return 'yes' === get_post_meta($product_id, self::SHOW_SAVINGS_META, true);
	}

	/**
	 * Calculate the savings amount for the included bundle items.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	public function get_bundle_savings_amount($product_id) {
		$items          = $this->get_bundle_items($product_id);
		$regular_total  = $this->calculate_items_subtotal($items);
		$discounted     = $this->calculate_discounted_items_total($regular_total, $this->get_pricing_mode($product_id), $this->get_base_price($product_id), $this->get_discount_value($product_id));
		$savings_amount = $regular_total - $discounted;

		return max(0.0, (float) wc_format_decimal($savings_amount));
	}

	/**
	 * Normalize a stored pricing mode to a supported value.
	 *
	 * @param string $mode Pricing mode.
	 * @return string
	 */
	public function sanitize_pricing_mode($mode) {
		$supported = array('fixed_price', 'percent_discount', 'fixed_discount');

		return in_array($mode, $supported, true) ? $mode : 'fixed_price';
	}

	/**
	 * Normalize a storefront style variant to a supported value.
	 *
	 * @param string $variant Storefront style variant.
	 * @return string
	 */
	public function sanitize_style_variant($variant) {
		$supported = array('default', 'slate', 'emerald');

		return in_array($variant, $supported, true) ? $variant : 'default';
	}

	/**
	 * Sanitize items received from the admin form.
	 *
	 * @param mixed  $raw_items Raw items.
	 * @param string $type     Item type.
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize_items($raw_items, $type) {
		$normalized = array();

		if (! is_array($raw_items)) {
			return $normalized;
		}

		foreach ($raw_items as $item) {
			$product_id   = isset($item['product_id']) ? absint($item['product_id']) : 0;
			$variation_id = isset($item['variation_id']) ? absint($item['variation_id']) : 0;
			$default_qty  = isset($item['default_qty']) ? max(0, absint($item['default_qty'])) : 0;
			$min_qty      = isset($item['min_qty']) ? max(0, absint($item['min_qty'])) : 0;
			$max_qty      = isset($item['max_qty']) ? max(0, absint($item['max_qty'])) : 0;
			$summary      = ! empty($item['show_summary']) ? 'yes' : 'no';

			if (! $product_id) {
				continue;
			}

			$item_type = 'optional' === $type ? 'optional' : 'included';

			if ('included' === $item_type && 0 === $default_qty) {
				$default_qty = 1;
			}

			if ($max_qty > 0 && $max_qty < $min_qty) {
				$max_qty = $min_qty;
			}

			if ('included' === $item_type && $min_qty > $default_qty) {
				$default_qty = $min_qty;
			}

			$normalized[] = array(
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'type'          => $item_type,
				'default_qty'   => $default_qty,
				'min_qty'       => $min_qty,
				'max_qty'       => $max_qty,
				'show_summary'  => $summary,
				'selected_text' => $this->get_product_label($product_id, $variation_id),
			);
		}

		return $normalized;
	}

	/**
	 * Validate item definitions for safe saving.
	 *
	 * @param array<int, array<string, mixed>> $items Item list.
	 * @return array<int, string>
	 */
	public function validate_items(array $items) {
		$errors = array();
		$seen   = array();

		foreach ($items as $item) {
			$key = (int) $item['product_id'] . ':' . (int) $item['variation_id'];

			if (isset($seen[ $key ])) {
				$errors[] = __('Bundle products cannot contain duplicate product or variation entries.', 'wc-product-bundler');
				break;
			}

			$seen[ $key ] = true;
			$product      = $this->get_resolved_product($item);

			if (! ($product instanceof \WC_Product)) {
				$errors[] = __('One or more selected bundle items could not be found.', 'wc-product-bundler');
				continue;
			}

			if ('bundle' === $product->get_type()) {
				$errors[] = __('Nested bundles are not supported in V1.', 'wc-product-bundler');
			}
		}

		return array_values(array_unique($errors));
	}

	/**
	 * Resolve a WC product from bundle item data.
	 *
	 * @param array<string, mixed> $item Bundle item.
	 * @return \WC_Product|null
	 */
	public function get_resolved_product(array $item) {
		$product_id   = isset($item['product_id']) ? absint($item['product_id']) : 0;
		$variation_id = ! empty($item['selected_variation_id']) ? absint($item['selected_variation_id']) : (isset($item['variation_id']) ? absint($item['variation_id']) : 0);

		if ($variation_id > 0) {
			$product = wc_get_product($variation_id);

			if ($product instanceof \WC_Product) {
				return $product;
			}
		}

		return $product_id > 0 ? wc_get_product($product_id) : null;
	}

	/**
	 * Get the variable parent product when a bundle child requires shopper selection.
	 *
	 * @param array<string, mixed> $item Bundle item.
	 * @return \WC_Product_Variable|null
	 */
	public function get_variable_parent_product(array $item) {
		$product_id   = isset($item['product_id']) ? absint($item['product_id']) : 0;
		$variation_id = isset($item['variation_id']) ? absint($item['variation_id']) : 0;

		if (! $product_id || $variation_id) {
			return null;
		}

		$product = wc_get_product($product_id);

		return $product instanceof \WC_Product_Variable ? $product : null;
	}

	/**
	 * Determine whether the item needs customer variation selection.
	 *
	 * @param array<string, mixed> $item Bundle item.
	 * @return bool
	 */
	public function item_requires_variation_selection(array $item) {
		return $this->get_variable_parent_product($item) instanceof \WC_Product_Variable;
	}

	/**
	 * Resolve a posted variation choice for a variable bundle item.
	 *
	 * @param array<string, mixed> $item Bundle item.
	 * @param array<string, mixed> $posted_attributes Posted attribute values.
	 * @return array<string, mixed>
	 */
	public function resolve_variation_choice(array $item, array $posted_attributes) {
		$product = $this->get_variable_parent_product($item);

		if (! $product instanceof \WC_Product_Variable) {
			return array(
				'product'        => $this->get_resolved_product($item),
				'variation_id'   => isset($item['variation_id']) ? absint($item['variation_id']) : 0,
				'attributes'     => array(),
				'missing_fields' => array(),
				'is_valid'       => true,
			);
		}

		$variation_attributes = $product->get_variation_attributes();
		$chosen_attributes    = array();
		$missing_fields       = array();

		foreach ($variation_attributes as $attribute_name => $options) {
			$attribute_key = $this->normalize_variation_attribute_key($attribute_name);
			$value         = $this->get_posted_variation_attribute_value($posted_attributes, $attribute_name);

			if ('' === $value) {
				$missing_fields[] = wc_attribute_label($attribute_name, $product);
			}

			$chosen_attributes[ $attribute_key ] = $value;
		}

		if (! empty($missing_fields)) {
			return array(
				'product'        => $product,
				'variation_id'   => 0,
				'attributes'     => $chosen_attributes,
				'missing_fields' => $missing_fields,
				'is_valid'       => false,
			);
		}

		$data_store   = \WC_Data_Store::load('product');
		$variation_id = $data_store->find_matching_product_variation($product, $chosen_attributes);
		$variation    = $variation_id ? wc_get_product($variation_id) : null;

		return array(
			'product'        => $variation instanceof \WC_Product ? $variation : $product,
			'variation_id'   => $variation_id ? absint($variation_id) : 0,
			'attributes'     => $chosen_attributes,
			'missing_fields' => array(),
			'is_valid'       => $variation instanceof \WC_Product_Variation,
		);
	}

	/**
	 * Format selected variation attributes for display.
	 *
	 * @param array<string, string> $attributes Attributes.
	 * @param \WC_Product|null      $product Product context.
	 * @return string
	 */
	public function format_variation_attributes(array $attributes, $product = null) {
		$parts = array();

		foreach ($attributes as $attribute_name => $value) {
			if ('' === (string) $value) {
				continue;
			}

			$normalized_name = $this->normalize_variation_attribute_key($attribute_name);
			$taxonomy_name   = str_replace('attribute_', '', $normalized_name);
			$label           = wc_attribute_label($taxonomy_name, $product instanceof \WC_Product ? $product : null);
			$text  = $value;

			if (taxonomy_exists($taxonomy_name)) {
				$term = get_term_by('slug', $value, $taxonomy_name);

				if ($term && ! is_wp_error($term)) {
					$text = $term->name;
				}
			}

			$parts[] = $label . ': ' . $text;
		}

		return implode(', ', $parts);
	}

	/**
	 * Normalize a variation attribute key to WooCommerce's expected format.
	 *
	 * @param string $attribute_name Attribute key.
	 * @return string
	 */
	private function normalize_variation_attribute_key($attribute_name) {
		$attribute_name = (string) $attribute_name;

		if (0 === strpos($attribute_name, 'attribute_')) {
			$attribute_name = substr($attribute_name, strlen('attribute_'));
		}

		return 'attribute_' . sanitize_title($attribute_name);
	}

	/**
	 * Read a posted variation attribute value using common WooCommerce key shapes.
	 *
	 * @param array<string, mixed> $posted_attributes Posted attribute values.
	 * @param string              $attribute_name Product attribute name.
	 * @return string
	 */
	private function get_posted_variation_attribute_value(array $posted_attributes, $attribute_name) {
		$normalized_key = $this->normalize_variation_attribute_key($attribute_name);
		$attribute_slug = str_replace('attribute_', '', $normalized_key);
		$candidates     = array_unique(
			array(
				(string) $attribute_name,
				sanitize_title($attribute_name),
				$normalized_key,
				$attribute_slug,
			)
		);

		foreach ($candidates as $candidate) {
			if (isset($posted_attributes[ $candidate ])) {
				return wc_clean(wp_unslash($posted_attributes[ $candidate ]));
			}
		}

		return '';
	}

	/**
	 * Calculate a subtotal for bundle item definitions.
	 *
	 * @param array<int, array<string, mixed>> $items Bundle item definitions.
	 * @return float
	 */
	private function calculate_items_subtotal(array $items) {
		$subtotal = 0.0;

		foreach ($items as $item) {
			$product = $this->get_resolved_product($item);

			if (! ($product instanceof \WC_Product)) {
				continue;
			}

			$subtotal += (float) wc_format_decimal($product->get_price()) * max(1, (int) ($item['default_qty'] ?? 1));
		}

		return $subtotal;
	}

	/**
	 * Calculate the discounted total for included bundle items.
	 *
	 * @param float  $regular_total Regular included items total.
	 * @param string $pricing_mode Pricing mode.
	 * @param string $base_price Fixed bundle price.
	 * @param string $discount_value Discount input.
	 * @return float
	 */
	private function calculate_discounted_items_total($regular_total, $pricing_mode, $base_price, $discount_value) {
		if ('fixed_price' === $pricing_mode) {
			return '' !== $base_price ? max(0.0, (float) wc_format_decimal($base_price)) : $regular_total;
		}

		if ('percent_discount' === $pricing_mode) {
			$discount_percent = min(max((float) $discount_value, 0), 100);

			return max(0.0, $regular_total - ($regular_total * ($discount_percent / 100)));
		}

		return max(0.0, $regular_total - max(0.0, (float) $discount_value));
	}

	/**
	 * Normalize stored item data.
	 *
	 * @param mixed  $items Raw item data.
	 * @param string $type  Item type.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_items($items, $type) {
		return $this->sanitize_items(is_array($items) ? $items : array(), $type);
	}

	/**
	 * Build a human-readable product label.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	private function get_product_label($product_id, $variation_id) {
		$product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);

		if (! ($product instanceof \WC_Product)) {
			return '';
		}

		return wp_strip_all_tags($product->get_formatted_name());
	}
}
