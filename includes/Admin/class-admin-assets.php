<?php
/**
 * Admin asset loader.
 *
 * @package WCProductBundler
 */

namespace WCPB\Admin;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Enqueue admin scripts and styles.
 */
class Admin_Assets {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	}

	/**
	 * Load assets on the WooCommerce product editor only.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public function enqueue($hook_suffix) {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
			return;
		}

		if (! $screen || 'product' !== $screen->post_type) {
			return;
		}

		wp_enqueue_style(
			'wcpb-admin',
			WCPB_URL . 'assets/css/admin.css',
			array(),
			WCPB_VERSION
		);

		wp_enqueue_script(
			'wcpb-admin',
			WCPB_URL . 'assets/js/admin.js',
			array('jquery', 'wc-enhanced-select', 'wc-admin-meta-boxes'),
			WCPB_VERSION,
			true
		);

		wp_localize_script(
			'wcpb-admin',
			'wcpbAdmin',
			array(
				'searchPlaceholder' => __('Search for a product or variation…', 'wc-product-bundler'),
				'includedLabel'     => __('Included item', 'wc-product-bundler'),
				'optionalLabel'     => __('Optional add-on', 'wc-product-bundler'),
				'removeLabel'       => __('Remove', 'wc-product-bundler'),
			)
		);
	}
}
