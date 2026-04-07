<?php
/**
 * Custom bundle product object.
 *
 * @package WCProductBundler
 */

namespace WCPB\Product;

use WCPB\Support\Product_Data;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Bundle product implementation.
 */
class Bundle_Product extends \WC_Product_Simple {
	/**
	 * Get product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'bundle';
	}

	/**
	 * Use the bundle page for loop CTAs when configuration is required first.
	 *
	 * @return string
	 */
	public function add_to_cart_url() {
		if ($this->bundle_requires_child_selection()) {
			return $this->get_permalink();
		}

		return parent::add_to_cart_url();
	}

	/**
	 * Disable AJAX add-to-cart for bundles that need child variation choices.
	 *
	 * @param string $feature Feature name.
	 * @return bool
	 */
	public function supports($feature) {
		if ('ajax_add_to_cart' === $feature && $this->bundle_requires_child_selection()) {
			return false;
		}

		return parent::supports($feature);
	}

	/**
	 * Check whether any child item needs variation selection before purchase.
	 *
	 * @return bool
	 */
	private function bundle_requires_child_selection() {
		$product_data = new Product_Data();
		$all_items    = $product_data->get_all_items($this->get_id());

		foreach (array('included', 'optional') as $role) {
			if (empty($all_items[ $role ]) || ! is_array($all_items[ $role ])) {
				continue;
			}

			foreach ($all_items[ $role ] as $item) {
				if ($product_data->item_requires_variation_selection($item)) {
					return true;
				}
			}
		}

		return false;
	}
}
