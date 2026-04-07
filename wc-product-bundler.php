<?php
/**
 * Plugin Name: WC Product Bundler
 * Plugin URI: https://frontierwp.com/
 * Description: Create stable WooCommerce bundle products with fixed contents and optional add-ons.
 * Version: 0.1.0
 * Author: FrontierWP
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc-product-bundler
 *
 * @package WCProductBundler
 */

if (! defined('ABSPATH')) {
	exit;
}

define('WCPB_VERSION', '0.1.0');
define('WCPB_FILE', __FILE__);
define('WCPB_PATH', plugin_dir_path(__FILE__));
define('WCPB_URL', plugin_dir_url(__FILE__));

spl_autoload_register(
	static function ($class) {
		$prefix = 'WCPB\\';

		if (0 !== strpos($class, $prefix)) {
			return;
		}

		$relative   = substr($class, strlen($prefix));
		$relative   = str_replace('\\', '/', $relative);
		$path_parts = explode('/', $relative);
		$class_name = array_pop($path_parts);
		$file_name  = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
		$file_path  = WCPB_PATH . 'includes/' . (! empty($path_parts) ? implode('/', $path_parts) . '/' : '') . $file_name;

		if (file_exists($file_path)) {
			require_once $file_path;
		}
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if (! class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WCPB_FILE, true);
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\WCPB\Plugin::instance();
	}
);
