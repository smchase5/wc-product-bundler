<?php
/**
 * Main plugin bootstrap.
 *
 * @package WCProductBundler
 */

namespace WCPB;

use WCPB\Admin\Admin_Assets;
use WCPB\Admin\Bundle_Product_Data;
use WCPB\Cart\Bundle_Cart_Manager;
use WCPB\Frontend\Bundle_Template_Manager;
use WCPB\Product\Bundle_Product_Type;
use WCPB\Support\Dependencies;
use WCPB\Support\Product_Data;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Plugin bootstrap.
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Dependencies service.
	 *
	 * @var Dependencies
	 */
	private $dependencies;

	/**
	 * Shared product data helper.
	 *
	 * @var Product_Data
	 */
	private $product_data;

	/**
	 * Product type integration.
	 *
	 * @var Bundle_Product_Type
	 */
	private $product_type;

	/**
	 * Admin product data UI.
	 *
	 * @var Bundle_Product_Data
	 */
	private $admin_product_data;

	/**
	 * Admin assets loader.
	 *
	 * @var Admin_Assets
	 */
	private $admin_assets;

	/**
	 * Frontend bundle templates.
	 *
	 * @var Bundle_Template_Manager
	 */
	private $template_manager;

	/**
	 * Cart and order manager.
	 *
	 * @var Bundle_Cart_Manager
	 */
	private $cart_manager;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->dependencies = new Dependencies();
		$this->dependencies->register();

		if (! $this->dependencies->has_woocommerce()) {
			return;
		}

		$this->product_data       = new Product_Data();
		$this->product_type       = new Bundle_Product_Type($this->product_data);
		$this->admin_product_data = new Bundle_Product_Data($this->product_data);
		$this->admin_assets       = new Admin_Assets();
		$this->template_manager   = new Bundle_Template_Manager($this->product_data);
		$this->cart_manager       = new Bundle_Cart_Manager($this->product_data);

		$this->product_type->register();
		$this->admin_product_data->register();
		$this->admin_assets->register();
		$this->template_manager->register();
		$this->cart_manager->register();
	}
}
