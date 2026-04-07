<?php
/**
 * WooCommerce dependency handling.
 *
 * @package WCProductBundler
 */

namespace WCPB\Support;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Dependency service.
 */
class Dependencies {
	/**
	 * Register dependency-related hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action('admin_notices', array($this, 'maybe_render_admin_notice'));
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function has_woocommerce() {
		return class_exists('WooCommerce');
	}

	/**
	 * Show a notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function maybe_render_admin_notice() {
		if ($this->has_woocommerce() || ! current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__('WC Product Bundler requires WooCommerce to be installed and active before bundle products can be used.', 'wc-product-bundler');
		echo '</p></div>';
	}
}
