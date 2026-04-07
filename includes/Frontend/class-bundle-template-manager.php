<?php
/**
 * Single-product template integration for bundle products.
 *
 * @package WCProductBundler
 */

namespace WCPB\Frontend;

use WCPB\Support\Product_Data;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Frontend template hooks for bundle products.
 */
class Bundle_Template_Manager {
	/**
	 * Shared product data helper.
	 *
	 * @var Product_Data
	 */
	private $product_data;

	/**
	 * Prevent duplicate form output when both Woo hook paths fire.
	 *
	 * @var bool
	 */
	private $has_rendered = false;

	/**
	 * Constructor.
	 *
	 * @param Product_Data $product_data Product data helper.
	 */
	public function __construct(Product_Data $product_data) {
		$this->product_data = $product_data;
	}

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('woocommerce_bundle_add_to_cart', array($this, 'render_add_to_cart'));
		add_action('woocommerce_single_product_summary', array($this, 'render_add_to_cart_fallback'), 31);
	}

	/**
	 * Enqueue frontend styles when needed.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$is_product_page = function_exists('is_product') && is_product();

		wp_enqueue_style('wcpb-frontend', WCPB_URL . 'assets/css/frontend.css', array(), WCPB_VERSION);

		if ($is_product_page) {
			global $product;

			if ($product instanceof \WC_Product && 'bundle' === $product->get_type()) {
				wp_enqueue_script(
					'wcpb-frontend',
					WCPB_URL . 'assets/js/frontend.js',
					array('jquery', 'wc-add-to-cart-variation'),
					WCPB_VERSION,
					true
				);
			}
		}

		if (wp_script_is('wc-blocks-checkout', 'registered')) {
			wp_enqueue_script(
				'wcpb-cart-blocks',
				WCPB_URL . 'assets/js/cart-blocks.js',
				array('wc-blocks-checkout'),
				WCPB_VERSION,
				true
			);
		}
	}

	/**
	 * Render the single product add-to-cart template.
	 *
	 * @return void
	 */
	public function render_add_to_cart() {
		global $product;

		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type() || $this->has_rendered) {
			return;
		}

		$this->has_rendered = true;

		$included_items = $this->product_data->get_bundle_items($product->get_id());
		$optional_items = $this->product_data->get_optional_items($product->get_id());
		$layout_style   = $this->product_data->get_layout_style($product->get_id());
		$style_variant  = $this->product_data->get_style_variant($product->get_id());
		$show_savings   = $this->product_data->should_show_savings($product->get_id());
		$savings_amount = $show_savings ? $this->product_data->get_bundle_savings_amount($product->get_id()) : 0.0;

		wc_get_template(
			'single-product/add-to-cart-bundle.php',
			array(
				'product'        => $product,
				'included_items' => $included_items,
				'optional_items' => $optional_items,
				'layout_style'   => $layout_style,
				'style_variant'  => $style_variant,
				'show_savings'   => $show_savings,
				'savings_amount' => $savings_amount,
				'product_data'   => $this->product_data,
			),
			'',
			WCPB_PATH . 'templates/'
		);
	}

	/**
	 * Fallback renderer for themes or Woo setups that do not surface the custom product-type hook.
	 *
	 * @return void
	 */
	public function render_add_to_cart_fallback() {
		$this->render_add_to_cart();
	}
}
