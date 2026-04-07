<?php
/**
 * Cart, validation, and order handling for bundle products.
 *
 * @package WCProductBundler
 */

namespace WCPB\Cart;

use WCPB\Support\Product_Data;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Bundle cart manager.
 */
class Bundle_Cart_Manager {
	/**
	 * Shared product data helper.
	 *
	 * @var Product_Data
	 */
	private $product_data;

	/**
	 * Prevent recursion while adding child lines.
	 *
	 * @var bool
	 */
	private $adding_children = false;

	/**
	 * Prevent recursive cart removals while cleaning up bundle children.
	 *
	 * @var bool
	 */
	private $removing_bundle = false;

	/**
	 * Constructor.
	 *
	 * @param Product_Data $product_data Product data helper.
	 */
	public function __construct(Product_Data $product_data) {
		$this->product_data = $product_data;
	}

	/**
	 * Register cart and order hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
		add_filter('woocommerce_add_cart_item_data', array($this, 'capture_bundle_configuration'), 10, 3);
		add_action('woocommerce_add_to_cart', array($this, 'add_child_items'), 10, 6);
		add_filter('woocommerce_get_cart_item_from_session', array($this, 'restore_cart_item_data'), 10, 2);
		add_action('woocommerce_before_calculate_totals', array($this, 'set_child_item_prices'));
		add_filter('woocommerce_cart_item_quantity', array($this, 'filter_cart_quantity_markup'), 10, 3);
		add_filter('woocommerce_cart_item_remove_link', array($this, 'filter_remove_link'), 10, 2);
		add_filter('woocommerce_cart_item_visible', array($this, 'filter_cart_item_visibility'), 999, 3);
		add_filter('woocommerce_checkout_cart_item_visible', array($this, 'filter_cart_item_visibility'), 999, 3);
		add_filter('woocommerce_widget_cart_item_visible', array($this, 'filter_cart_item_visibility'), 999, 3);
		add_filter('woocommerce_cart_item_class', array($this, 'filter_cart_item_class'), 999, 3);
		add_filter('woocommerce_mini_cart_item_class', array($this, 'filter_cart_item_class'), 999, 3);
		add_filter('woocommerce_cart_item_name', array($this, 'filter_cart_item_name'), 10, 3);
		add_action('woocommerce_remove_cart_item', array($this, 'handle_cart_item_removal'), 10, 2);
		add_action('woocommerce_after_cart_item_quantity_update', array($this, 'sync_child_quantities'), 10, 4);
		add_action('woocommerce_checkout_create_order_line_item', array($this, 'persist_order_item_meta'), 10, 4);
		add_action('woocommerce_checkout_order_created', array($this, 'link_child_order_items'));
		add_filter('woocommerce_get_item_data', array($this, 'render_item_data'), 10, 2);
		add_filter('woocommerce_order_item_visible', array($this, 'filter_order_item_visibility'), 999, 2);
		add_filter('woocommerce_display_item_meta', array($this, 'filter_order_item_meta_html'), 10, 3);
		add_filter('woocommerce_store_api_product_quantity_editable', array($this, 'filter_store_api_quantity_editable'), 10, 3);
		add_action('woocommerce_blocks_loaded', array($this, 'register_store_api_extensions'));
	}

	/**
	 * Validate a bundle before the parent line is added.
	 *
	 * @param bool $passed Validation status.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity Requested parent quantity.
	 * @return bool
	 */
	public function validate_add_to_cart($passed, $product_id, $quantity) {
		$product = wc_get_product($product_id);

		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type()) {
			return $passed;
		}

		$included_items = $this->product_data->get_bundle_items($product_id);

		if (empty($included_items)) {
			wc_add_notice(__('This bundle is not configured correctly yet.', 'wc-product-bundler'), 'error');

			return false;
		}

		foreach ($included_items as $index => $item) {
			$required_qty = max(1, (int) $item['default_qty']) * max(1, $quantity);
			$child        = $this->resolve_requested_child_product($item, 'included', $index);

			if (is_wp_error($child)) {
				wc_add_notice($child->get_error_message(), 'error');

				return false;
			}

			if (! $this->is_child_purchasable($child, $required_qty)) {
				wc_add_notice(
					sprintf(
						/* translators: %s product name */
						__('The bundle cannot be added because "%s" does not have enough stock.', 'wc-product-bundler'),
						$child instanceof \WC_Product ? $child->get_name() : __('a bundled item', 'wc-product-bundler')
					),
					'error'
				);

				return false;
			}
		}

		$optional_input = isset($_POST['wcpb_optional_qty']) ? wp_unslash($_POST['wcpb_optional_qty']) : array();

		foreach ($this->product_data->get_optional_items($product_id) as $index => $item) {
			$selected_qty = isset($optional_input[ $index ]) ? absint($optional_input[ $index ]) : 0;
			$max_qty      = (int) $item['max_qty'];
			$min_qty      = (int) $item['min_qty'];

			if ($selected_qty < $min_qty) {
				wc_add_notice(__('An optional bundle item was submitted with an invalid quantity.', 'wc-product-bundler'), 'error');

				return false;
			}

			if ($max_qty > 0 && $selected_qty > $max_qty) {
				wc_add_notice(__('An optional bundle item exceeds the allowed quantity.', 'wc-product-bundler'), 'error');

				return false;
			}

			if ($selected_qty > 0) {
				$child = $this->resolve_requested_child_product($item, 'optional', $index);

				if (is_wp_error($child)) {
					wc_add_notice($child->get_error_message(), 'error');

					return false;
				}

				if (! $this->is_child_purchasable($child, $selected_qty * max(1, $quantity))) {
					wc_add_notice(
						sprintf(
							/* translators: %s product name */
							__('The optional add-on "%s" does not have enough stock.', 'wc-product-bundler'),
							$child instanceof \WC_Product ? $child->get_name() : __('bundle add-on', 'wc-product-bundler')
						),
						'error'
					);

					return false;
				}
			}
		}

		return $passed;
	}

	/**
	 * Store the chosen bundle configuration on the parent cart line.
	 *
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 * @param int                  $product_id Product ID.
	 * @param int                  $variation_id Variation ID.
	 * @return array<string, mixed>
	 */
	public function capture_bundle_configuration($cart_item_data, $product_id, $variation_id) {
		if ($this->adding_children) {
			return $cart_item_data;
		}

		$product = wc_get_product($variation_id ? $variation_id : $product_id);

		if (! ($product instanceof \WC_Product) || 'bundle' !== $product->get_type()) {
			return $cart_item_data;
		}

		$cart_item_data[ Product_Data::CONFIG_META ] = array(
			'included' => $this->build_configured_items($this->product_data->get_bundle_items($product_id), 'included'),
			'optional' => $this->build_selected_optional_items($product_id),
		);

		return $cart_item_data;
	}

	/**
	 * Add child cart lines for the saved bundle configuration.
	 *
	 * @param string $cart_item_key Parent cart item key.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Parent quantity.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variation Variation attributes.
	 * @param array  $cart_item_data Parent cart item data.
	 * @return void
	 */
	public function add_child_items($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
		if ($this->adding_children || empty($cart_item_data[ Product_Data::CONFIG_META ]['included'])) {
			return;
		}

		$this->adding_children = true;

		$config = $cart_item_data[ Product_Data::CONFIG_META ];

		foreach (array('included', 'optional') as $role) {
			if (empty($config[ $role ])) {
				continue;
			}

			foreach ($config[ $role ] as $item) {
				$this->add_child_item($item, $quantity, $cart_item_key, $role);
			}
		}

		$this->adding_children = false;
	}

	/**
	 * Restore session data for child cart lines.
	 *
	 * @param array<string, mixed> $cart_item Session cart item.
	 * @param array<string, mixed> $values Saved values.
	 * @return array<string, mixed>
	 */
	public function restore_cart_item_data($cart_item, $values) {
		foreach (array(Product_Data::PARENT_KEY, Product_Data::CHILD_ROLE_META, Product_Data::CONFIG_META, '_wcpb_base_quantity') as $meta_key) {
			if (isset($values[ $meta_key ])) {
				$cart_item[ $meta_key ] = $values[ $meta_key ];
			}
		}

		return $cart_item;
	}

	/**
	 * Keep bundle child prices hidden and move the computed total onto the parent item.
	 *
	 * @param \WC_Cart $cart Cart instance.
	 * @return void
	 */
	public function set_child_item_prices($cart) {
		if (! ($cart instanceof \WC_Cart) || (is_admin() && ! defined('DOING_AJAX'))) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
			if (empty($cart_item['data']) || ! ($cart_item['data'] instanceof \WC_Product)) {
				continue;
			}

			if (! empty($cart_item[ Product_Data::PARENT_KEY ])) {
				$cart->cart_contents[ $cart_item_key ]['data']->set_price(0);
				continue;
			}

			if (empty($cart_item[ Product_Data::CONFIG_META ]) || ! is_array($cart_item[ Product_Data::CONFIG_META ])) {
				continue;
			}

			$pricing = $this->get_bundle_pricing_details($cart_item);

			$cart->cart_contents[ $cart_item_key ]['data']->set_price($pricing['bundle_unit_total']);
		}
	}

	/**
	 * Prevent quantity editing on child rows.
	 *
	 * @param string $product_quantity Markup.
	 * @param string $cart_item_key Cart item key.
	 * @param array  $cart_item Cart item data.
	 * @return string
	 */
	public function filter_cart_quantity_markup($product_quantity, $cart_item_key, $cart_item) {
		if (empty($cart_item[ Product_Data::PARENT_KEY ])) {
			return $product_quantity;
		}

		return '<span class="wcpb-child-quantity">' . esc_html((string) $cart_item['quantity']) . '</span>';
	}

	/**
	 * Prevent independent child removal.
	 *
	 * @param string $link Existing remove link.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_remove_link($link, $cart_item_key) {
		$cart = WC()->cart;

		if (! $cart instanceof \WC_Cart) {
			return $link;
		}

		$cart_item = $cart->get_cart_item($cart_item_key);

		if (! empty($cart_item[ Product_Data::PARENT_KEY ])) {
			return '';
		}

		return $link;
	}

	/**
	 * Hide bundle child lines from shopper-facing cart views.
	 *
	 * @param bool   $visible Existing visibility state.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return bool
	 */
	public function filter_cart_item_visibility($visible, $cart_item, $cart_item_key) {
		if (! empty($cart_item[ Product_Data::PARENT_KEY ])) {
			return false;
		}

		return $visible;
	}

	/**
	 * Add a fallback CSS class to hidden child rows for themes that do not honor visibility filters.
	 *
	 * @param string $class Existing row class string.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_class($class, $cart_item, $cart_item_key) {
		if (empty($cart_item[ Product_Data::PARENT_KEY ])) {
			return $class;
		}

		$classes   = preg_split('/\s+/', trim((string) $class));
		$classes   = is_array($classes) ? array_filter($classes) : array();
		$classes[] = 'wcpb-hidden-child-item';

		return implode(' ', array_unique($classes));
	}

	/**
	 * Prevent quantity editing for bundle child items in Store API powered carts.
	 *
	 * @param bool              $editable Existing editable flag.
	 * @param \WC_Product       $product Product object.
	 * @param array<string,mixed>|null $cart_item Cart item data.
	 * @return bool
	 */
	public function filter_store_api_quantity_editable($editable, $product, $cart_item) {
		if (is_array($cart_item) && ! empty($cart_item[ Product_Data::PARENT_KEY ])) {
			return false;
		}

		return $editable;
	}

	/**
	 * Register Store API data used by Cart and Checkout blocks.
	 *
	 * @return void
	 */
	public function register_store_api_extensions() {
		if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => 'wc-product-bundler',
				'schema_callback' => array($this, 'get_store_api_cart_item_schema'),
				'data_callback'   => array($this, 'get_store_api_cart_item_data'),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Store API schema for custom bundle cart item data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_store_api_cart_item_schema() {
		return array(
			'is_bundle_child' => array(
				'description' => __('Whether this cart item is a hidden child item of a bundle.', 'wc-product-bundler'),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'is_bundle_parent' => array(
				'description' => __('Whether this cart item is the visible parent bundle line.', 'wc-product-bundler'),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'bundle_summary_markup' => array(
				'description' => __('Rendered bundle summary markup for block-based cart UIs.', 'wc-product-bundler'),
				'type'        => 'string',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Store API data for custom bundle cart item data.
	 *
	 * @param array<string, mixed> $cart_item Cart item data.
	 * @return array<string, bool>
	 */
	public function get_store_api_cart_item_data($cart_item) {
		$is_bundle_parent = is_array($cart_item) && ! empty($cart_item[ Product_Data::CONFIG_META ]) && empty($cart_item[ Product_Data::PARENT_KEY ]);
		$summary_markup   = '';

		if ($is_bundle_parent) {
			$pricing = $this->get_bundle_pricing_details($cart_item);

			if (! empty($pricing['summary_items'])) {
				$summary_markup = $this->get_summary_list_markup($pricing['summary_items'], true);
			}
		}

		return array(
			'is_bundle_child'  => is_array($cart_item) && ! empty($cart_item[ Product_Data::PARENT_KEY ]),
			'is_bundle_parent' => $is_bundle_parent,
			'bundle_summary_markup' => $summary_markup,
		);
	}

	/**
	 * Add a readable bundle component summary under the parent line item.
	 *
	 * @param string $product_name Existing product name markup.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_name($product_name, $cart_item, $cart_item_key) {
		if (empty($cart_item[ Product_Data::CONFIG_META ]) || ! is_array($cart_item[ Product_Data::CONFIG_META ])) {
			return $product_name;
		}

		$pricing = $this->get_bundle_pricing_details($cart_item);

		if (empty($pricing['summary_items'])) {
			return $product_name;
		}

		return $product_name . $this->get_summary_list_markup($pricing['summary_items'], true);
	}

	/**
	 * Remove linked child rows when a parent is removed.
	 *
	 * @param string   $cart_item_key Removed cart item key.
	 * @param \WC_Cart $cart Cart instance.
	 * @return void
	 */
	public function handle_cart_item_removal($cart_item_key, $cart) {
		if ($this->removing_bundle) {
			return;
		}

		$removed = $cart->removed_cart_contents[ $cart_item_key ] ?? null;

		if (! is_array($removed)) {
			return;
		}

		$this->removing_bundle = true;

		if (empty($removed[ Product_Data::PARENT_KEY ])) {
			foreach ($cart->get_cart() as $child_key => $child_item) {
				if (($child_item[ Product_Data::PARENT_KEY ] ?? '') === $cart_item_key) {
					$cart->remove_cart_item($child_key);
				}
			}
		}

		$this->removing_bundle = false;
	}

	/**
	 * Keep child quantities aligned with the parent quantity.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $quantity New quantity.
	 * @param int    $old_quantity Old quantity.
	 * @param object $cart Cart instance.
	 * @return void
	 */
	public function sync_child_quantities($cart_item_key, $quantity, $old_quantity, $cart) {
		if (! $cart instanceof \WC_Cart) {
			return;
		}

		$parent = $cart->get_cart_item($cart_item_key);

		if (empty($parent[ Product_Data::CONFIG_META ])) {
			return;
		}

		foreach ($cart->get_cart() as $child_key => $child_item) {
			if (($child_item[ Product_Data::PARENT_KEY ] ?? '') !== $cart_item_key) {
				continue;
			}

			$base_quantity = isset($child_item['_wcpb_base_quantity']) ? max(1, absint($child_item['_wcpb_base_quantity'])) : 1;
			$cart->set_quantity($child_key, $base_quantity * max(1, $quantity), false);
		}
	}

	/**
	 * Persist bundle relationship metadata in orders.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order object.
	 * @return void
	 */
	public function persist_order_item_meta($item, $cart_item_key, $values, $order) {
		if (! empty($values[ Product_Data::PARENT_KEY ])) {
			$item->add_meta_data(Product_Data::PARENT_KEY, $values[ Product_Data::PARENT_KEY ], true);
			$item->add_meta_data(Product_Data::CHILD_ROLE_META, $values[ Product_Data::CHILD_ROLE_META ] ?? 'included', true);
		}

		if (! empty($values[ Product_Data::CONFIG_META ])) {
			$item->add_meta_data(Product_Data::PARENT_KEY, $cart_item_key, true);
			$item->add_meta_data(Product_Data::CONFIG_META, wp_json_encode($values[ Product_Data::CONFIG_META ]), true);
		}
	}

	/**
	 * Replace parent cart keys with real parent order item ids after checkout.
	 *
	 * @param \WC_Order $order Created order.
	 * @return void
	 */
	public function link_child_order_items($order) {
		if (! $order instanceof \WC_Order) {
			return;
		}

		$parent_items = array();

		foreach ($order->get_items() as $item_id => $item) {
			$config_meta = $item->get_meta(Product_Data::CONFIG_META, true);

			if ($config_meta) {
				$parent_items[ $item->get_meta(Product_Data::PARENT_KEY, true) ] = $item_id;
			}
		}

		foreach ($order->get_items() as $item) {
			$parent_key = $item->get_meta(Product_Data::PARENT_KEY, true);

			if (! $parent_key || ! isset($parent_items[ $parent_key ])) {
				continue;
			}

			$item->update_meta_data(Product_Data::PARENT_ORDER_ITEM_META, $parent_items[ $parent_key ]);
			$item->save();
		}
	}

	/**
	 * Surface child role data in order and cart item details.
	 *
	 * @param array<int, array<string, string>> $item_data Existing item data.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function render_item_data($item_data, $cart_item) {
		if (! empty($cart_item[ Product_Data::PARENT_KEY ])) {
			return $item_data;
		}

		if (empty($cart_item[ Product_Data::CONFIG_META ]) || ! is_array($cart_item[ Product_Data::CONFIG_META ])) {
			return $item_data;
		}

		$pricing = $this->get_bundle_pricing_details($cart_item);

		if (empty($pricing['summary_items'])) {
			return $item_data;
		}

		$item_data[] = array(
			'key'       => __('Bundle contents', 'wc-product-bundler'),
			'value'     => implode(
				', ',
				array_map(function ($summary_item) {
					return wp_strip_all_tags($summary_item['label'] . ' - ' . $this->get_summary_price_text($summary_item));
				}, $pricing['summary_items'])
			),
			'display'   => $this->get_summary_list_markup($pricing['summary_items'], false),
			'className' => 'wcpb-cart-summary-detail',
		);

		return $item_data;
	}

	/**
	 * Hide child order items from thank-you/order-details contexts and other shopper-facing order views.
	 *
	 * @param bool            $visible Existing visibility state.
	 * @param \WC_Order_Item  $item Order item.
	 * @return bool
	 */
	public function filter_order_item_visibility($visible, $item) {
		if (! ($item instanceof \WC_Order_Item_Product)) {
			return $visible;
		}

		if ($this->is_bundle_child_order_item($item)) {
			return false;
		}

		return $visible;
	}

	/**
	 * Append a bundle contents summary to parent order items.
	 *
	 * @param string          $html Existing item meta HTML/text.
	 * @param \WC_Order_Item  $item Order item.
	 * @param array<string,mixed> $args Render arguments.
	 * @return string
	 */
	public function filter_order_item_meta_html($html, $item, $args) {
		if (! ($item instanceof \WC_Order_Item_Product) || $this->is_bundle_child_order_item($item)) {
			return $html;
		}

		$pricing = $this->get_order_item_pricing_details($item);

		if (empty($pricing['summary_items'])) {
			return $html;
		}

		$is_html_context = false !== strpos((string) ($args['separator'] ?? ''), '<')
			|| false !== strpos((string) ($args['label_before'] ?? ''), '<')
			|| false !== strpos((string) ($args['label_after'] ?? ''), '<');

		if ($is_html_context) {
			return $html . $this->get_summary_list_markup($pricing['summary_items'], true);
		}

		$summary_lines = array_map(function ($summary_item) {
			return wp_strip_all_tags($summary_item['label'] . ' - ' . $this->get_summary_price_text($summary_item));
		}, $pricing['summary_items']);

		$prefix = empty($html) ? '' : (string) ($args['separator'] ?? '');

		return $html . $prefix . __('Bundle contents', 'wc-product-bundler') . ': ' . implode('; ', $summary_lines);
	}

	/**
	 * Determine whether a child product is purchasable with enough stock.
	 *
	 * @param \WC_Product|null $product Product object.
	 * @param int              $quantity Required quantity.
	 * @return bool
	 */
	private function is_child_purchasable($product, $quantity) {
		if (! ($product instanceof \WC_Product) || ! $product->is_purchasable()) {
			return false;
		}

		if (! $product->managing_stock()) {
			return $product->is_in_stock();
		}

		return $product->has_enough_stock($quantity);
	}

	/**
	 * Build the selected optional child item list from request data.
	 *
	 * @param int $product_id Bundle product id.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_selected_optional_items($product_id) {
		$selected = array();
		$raw_qty  = isset($_POST['wcpb_optional_qty']) ? wp_unslash($_POST['wcpb_optional_qty']) : array();

		foreach ($this->product_data->get_optional_items($product_id) as $index => $item) {
			$selected_qty = isset($raw_qty[ $index ]) ? absint($raw_qty[ $index ]) : 0;

			if ($selected_qty <= 0) {
				continue;
			}

			$item['default_qty'] = $selected_qty;
			$item                = $this->apply_posted_variation_selection($item, 'optional', $index);
			$selected[]          = $item;
		}

		return $selected;
	}

	/**
	 * Add a child line item to the cart.
	 *
	 * @param array<string, mixed> $item Bundle item definition.
	 * @param int                  $parent_quantity Parent quantity.
	 * @param string               $parent_key Parent cart key.
	 * @param string               $role Item role.
	 * @return void
	 */
	private function add_child_item(array $item, $parent_quantity, $parent_key, $role) {
		$product_id   = absint($item['product_id']);
		$variation_id = ! empty($item['selected_variation_id']) ? absint($item['selected_variation_id']) : absint($item['variation_id']);
		$base_qty     = max(1, absint($item['default_qty']));
		$product      = $this->product_data->get_resolved_product($item);

		if (! ($product instanceof \WC_Product)) {
			return;
		}

		$cart_item_data = array(
			Product_Data::PARENT_KEY       => $parent_key,
			Product_Data::CHILD_ROLE_META  => $role,
			'_wcpb_base_quantity'          => $base_qty,
			'readonly_quantity'            => true,
		);

		$variation_attributes = ! empty($item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ]) && is_array($item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ])
			? $item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ]
			: array();

		if ($product instanceof \WC_Product_Variation) {
			$product_id           = $product->get_parent_id();
			$variation_id         = $product->get_id();
			$variation_attributes = $product->get_variation_attributes();
		}

		WC()->cart->add_to_cart($product_id, $base_qty * max(1, $parent_quantity), $variation_id, $variation_attributes, $cart_item_data);
	}

	/**
	 * Calculate pricing and itemized summary data for a parent bundle cart item.
	 *
	 * @param array<string, mixed> $cart_item Parent cart item.
	 * @return array<string, mixed>
	 */
	private function get_bundle_pricing_details(array $cart_item) {
		$empty = array(
			'bundle_unit_total' => 0.0,
			'summary_items'     => array(),
		);

		if (empty($cart_item[ Product_Data::CONFIG_META ]) || ! is_array($cart_item[ Product_Data::CONFIG_META ]) || empty($cart_item['data']) || ! ($cart_item['data'] instanceof \WC_Product)) {
			return $empty;
		}

		$config           = $cart_item[ Product_Data::CONFIG_META ];
		$product_id       = $cart_item['data']->get_id();
		$included_summary = $this->build_summary_items(is_array($config['included'] ?? null) ? $config['included'] : array(), 'included');
		$optional_summary = $this->build_summary_items(is_array($config['optional'] ?? null) ? $config['optional'] : array(), 'optional');
		$included_minor   = array_sum(array_map(static function ($item) {
			return (int) $item['regular_total_minor'];
		}, $included_summary));
		$pricing_mode     = $this->product_data->get_pricing_mode($product_id);
		$base_price       = $this->product_data->get_base_price($product_id);
		$discount_value   = $this->product_data->get_discount_value($product_id);

		if ('fixed_price' === $pricing_mode) {
			$target_included_minor = '' !== $base_price ? (int) wc_add_number_precision((float) $base_price) : $included_minor;
		} elseif ('percent_discount' === $pricing_mode) {
			$discount_percent      = min(max((float) $discount_value, 0), 100);
			$target_included_minor = max(0, $included_minor - (int) round($included_minor * ($discount_percent / 100)));
		} else {
			$fixed_discount_minor  = (int) wc_add_number_precision(max(0, (float) $discount_value));
			$target_included_minor = max(0, $included_minor - min($included_minor, $fixed_discount_minor));
		}

		$adjustments = $this->allocate_minor_adjustment(
			array_map(static function ($item) {
				return (int) $item['regular_total_minor'];
			}, $included_summary),
			$included_minor - $target_included_minor
		);

		foreach ($included_summary as $index => &$summary_item) {
			$adjustment                    = $adjustments[ $index ] ?? 0;
			$summary_item['adjustment_minor'] = $adjustment;
			$summary_item['discount_minor']   = max(0, $adjustment);
			$summary_item['regular_total']    = (float) wc_remove_number_precision($summary_item['regular_total_minor']);
			$summary_item['final_total_minor'] = $summary_item['regular_total_minor'] - $adjustment;
			$summary_item['final_total']       = (float) wc_remove_number_precision($summary_item['final_total_minor']);
		}
		unset($summary_item);

		foreach ($optional_summary as &$summary_item) {
			$summary_item['adjustment_minor']  = 0;
			$summary_item['discount_minor']    = 0;
			$summary_item['regular_total']     = (float) wc_remove_number_precision($summary_item['regular_total_minor']);
			$summary_item['final_total_minor'] = $summary_item['regular_total_minor'];
			$summary_item['final_total']       = (float) wc_remove_number_precision($summary_item['final_total_minor']);
		}
		unset($summary_item);

		$bundle_unit_total_minor = array_sum(array_map(static function ($item) {
			return (int) $item['final_total_minor'];
		}, array_merge($included_summary, $optional_summary)));

		return array(
			'bundle_unit_total' => (float) wc_remove_number_precision($bundle_unit_total_minor),
			'summary_items'     => array_merge($included_summary, $optional_summary),
		);
	}

	/**
	 * Build itemized summary data for one bundle role.
	 *
	 * @param array<int, array<string, mixed>> $items Bundle items.
	 * @param string                           $role Item role.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_summary_items(array $items, $role) {
		$summary_items = array();

		foreach ($items as $item) {
			$product = $this->product_data->get_resolved_product($item);

			if (! ($product instanceof \WC_Product)) {
				continue;
			}

			$qty   = max(1, (int) ($item['default_qty'] ?? 1));
			$label = $product->get_name() . ' x ' . $qty;

			if (! empty($item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ]) && is_array($item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ])) {
				$formatted_attributes = $this->product_data->format_variation_attributes($item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ], $product);

				if ($formatted_attributes) {
					$label .= ' (' . $formatted_attributes . ')';
				}
			}

			if ('optional' === $role) {
				$label .= ' (' . __('add-on', 'wc-product-bundler') . ')';
			}

			$summary_items[] = array(
				'role'               => $role,
				'label'              => $label,
				'regular_total_minor' => (int) wc_add_number_precision((float) wc_format_decimal($product->get_price()) * $qty),
			);
		}

		return $summary_items;
	}

	/**
	 * Allocate a positive or negative adjustment across line totals proportionally.
	 *
	 * @param array<int, int> $line_totals_minor Line totals in minor units.
	 * @param int             $adjustment_minor Adjustment to allocate.
	 * @return array<int, int>
	 */
	private function allocate_minor_adjustment(array $line_totals_minor, $adjustment_minor) {
		if (0 === $adjustment_minor || empty($line_totals_minor)) {
			return array_fill(0, count($line_totals_minor), 0);
		}

		$total_minor = array_sum($line_totals_minor);

		if ($total_minor <= 0) {
			return array_fill(0, count($line_totals_minor), 0);
		}

		$sign        = $adjustment_minor < 0 ? -1 : 1;
		$remaining   = abs($adjustment_minor);
		$allocations = array_fill(0, count($line_totals_minor), 0);
		$fractionals = array();
		$distributed = 0;

		foreach ($line_totals_minor as $index => $line_total_minor) {
			$raw_share             = ($remaining * $line_total_minor) / $total_minor;
			$allocations[ $index ] = (int) floor($raw_share);
			$fractionals[ $index ] = $raw_share - $allocations[ $index ];
			$distributed          += $allocations[ $index ];
		}

		$remainder = $remaining - $distributed;

		if ($remainder > 0) {
			arsort($fractionals);

			foreach (array_keys($fractionals) as $index) {
				++$allocations[ $index ];
				--$remainder;

				if ($remainder <= 0) {
					break;
				}
			}
		}

		foreach ($allocations as $index => $allocation) {
			$allocations[ $index ] = $allocation * $sign;
		}

		return $allocations;
	}

	/**
	 * Format bundle summary prices for HTML contexts.
	 *
	 * @param array<string, mixed> $summary_item Summary item.
	 * @return string
	 */
	private function get_summary_price_markup(array $summary_item) {
		$final_price = wc_price($summary_item['final_total']);

		if (($summary_item['discount_minor'] ?? 0) > 0) {
			return '<span class="wcpb-cart-summary-price-regular">' . wc_price($summary_item['regular_total']) . '</span><span class="wcpb-cart-summary-price-final is-discounted">' . $final_price . '</span>';
		}

		return '<span class="wcpb-cart-summary-price-final">' . $final_price . '</span>';
	}

	/**
	 * Format bundle summary prices for plain text contexts.
	 *
	 * @param array<string, mixed> $summary_item Summary item.
	 * @return string
	 */
	private function get_summary_price_text(array $summary_item) {
		$final_price = wp_strip_all_tags(wc_price($summary_item['final_total']));

		if (($summary_item['discount_minor'] ?? 0) > 0) {
			return sprintf(
				/* translators: 1: original price, 2: discounted price */
				__('%1$s discounted to %2$s', 'wc-product-bundler'),
				wp_strip_all_tags(wc_price($summary_item['regular_total'])),
				$final_price
			);
		}

		return $final_price;
	}

	/**
	 * Get pricing details for a parent bundle order item.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return array<string, mixed>
	 */
	private function get_order_item_pricing_details($item) {
		$empty = array(
			'bundle_unit_total' => 0.0,
			'summary_items'     => array(),
		);

		if (! ($item instanceof \WC_Order_Item_Product)) {
			return $empty;
		}

		$config_meta = $item->get_meta(Product_Data::CONFIG_META, true);
		$config      = is_string($config_meta) ? json_decode($config_meta, true) : $config_meta;
		$product     = $item->get_product();

		if (! is_array($config) || ! ($product instanceof \WC_Product)) {
			return $empty;
		}

		return $this->get_bundle_pricing_details(
			array(
				Product_Data::CONFIG_META => $config,
				'data'                    => $product,
			)
		);
	}

	/**
	 * Build list-style markup for bundle summary items.
	 *
	 * @param array<int, array<string, mixed>> $summary_items Summary items.
	 * @param bool                             $include_heading Whether to include the "Bundle contents" heading.
	 * @return string
	 */
	private function get_summary_list_markup(array $summary_items, $include_heading) {
		$markup = '<span class="wcpb-cart-summary">';

		if ($include_heading) {
			$markup .= '<strong>' . esc_html__('Bundle contents', 'wc-product-bundler') . '</strong>';
		}

		$markup .= '<span class="wcpb-cart-summary-list">';

		foreach ($summary_items as $summary_item) {
			$markup .= '<span class="wcpb-cart-summary-line">';
			$markup .= '<span class="wcpb-cart-summary-copy">' . esc_html($summary_item['label']) . '</span>';
			$markup .= '<span class="wcpb-cart-summary-amount">' . wp_kses_post($this->get_summary_price_markup($summary_item)) . '</span>';
			$markup .= '</span>';
		}

		$markup .= '</span></span>';

		return $markup;
	}

	/**
	 * Determine whether an order item is a hidden bundle child.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return bool
	 */
	private function is_bundle_child_order_item($item) {
		if (! ($item instanceof \WC_Order_Item_Product)) {
			return false;
		}

		return '' !== (string) $item->get_meta(Product_Data::CHILD_ROLE_META, true)
			|| '' !== (string) $item->get_meta(Product_Data::PARENT_ORDER_ITEM_META, true);
	}

	/**
	 * Determine whether the current request is a Store API request.
	 *
	 * @return bool
	 */
	private function is_store_api_request() {
		if (! defined('REST_REQUEST') || ! REST_REQUEST || empty($_SERVER['REQUEST_URI'])) {
			return false;
		}

		return false !== strpos(wp_unslash($_SERVER['REQUEST_URI']), '/wc/store/');
	}

	/**
	 * Apply posted variation choices to all bundle items of a given role.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param string                           $role Item role.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_configured_items(array $items, $role) {
		$configured = array();

		foreach ($items as $index => $item) {
			$configured[] = $this->apply_posted_variation_selection($item, $role, $index);
		}

		return $configured;
	}

	/**
	 * Apply posted variation selection to a single bundle item.
	 *
	 * @param array<string, mixed> $item Bundle item.
	 * @param string               $role Item role.
	 * @param int|string           $index Item index.
	 * @return array<string, mixed>
	 */
	private function apply_posted_variation_selection(array $item, $role, $index) {
		if (! $this->product_data->item_requires_variation_selection($item)) {
			return $item;
		}

		$selected_variation_id = $this->get_posted_selected_variation_id($role, $index);

		if ($selected_variation_id > 0) {
			$item['selected_variation_id'] = $selected_variation_id;
			$item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ] = $this->get_posted_variation_attributes($role, $index);

			return $item;
		}

		$resolved = $this->product_data->resolve_variation_choice($item, $this->get_posted_variation_attributes($role, $index));

		if (! empty($resolved['variation_id'])) {
			$item['selected_variation_id']               = absint($resolved['variation_id']);
			$item[ Product_Data::CHOSEN_ATTRIBUTES_KEY ] = $resolved['attributes'];
		}

		return $item;
	}

	/**
	 * Resolve the actual child product requested for a bundle item.
	 *
	 * @param array<string, mixed> $item Bundle item.
	 * @param string               $role Item role.
	 * @param int|string|null      $index Item index.
	 * @return \WC_Product|\WP_Error|null
	 */
	private function resolve_requested_child_product(array $item, $role, $index = null) {
		if (! $this->product_data->item_requires_variation_selection($item)) {
			return $this->product_data->get_resolved_product($item);
		}

		$selected_variation_id = $this->get_posted_selected_variation_id($role, $index);

		if ($selected_variation_id > 0) {
			$product = wc_get_product($selected_variation_id);

			if ($product instanceof \WC_Product_Variation && absint($product->get_parent_id()) === absint($item['product_id'])) {
				return $product;
			}
		}

		$resolved = $this->product_data->resolve_variation_choice($item, $this->get_posted_variation_attributes($role, $index));
		$product  = $resolved['product'];

		if (! empty($resolved['missing_fields'])) {
			return new \WP_Error(
				'wcpb_missing_variation',
				sprintf(
					/* translators: 1: product name, 2: attribute labels */
					__('Please choose %2$s for "%1$s".', 'wc-product-bundler'),
					$product instanceof \WC_Product ? $product->get_name() : __('this bundled product', 'wc-product-bundler'),
					implode(', ', $resolved['missing_fields'])
				)
			);
		}

		if (empty($resolved['is_valid']) || ! ($product instanceof \WC_Product_Variation)) {
			return new \WP_Error(
				'wcpb_invalid_variation',
				sprintf(
					/* translators: %s product name */
					__('Please choose a valid variation for "%s".', 'wc-product-bundler'),
					$product instanceof \WC_Product ? $product->get_name() : __('this bundled product', 'wc-product-bundler')
				)
			);
		}

		return $product;
	}

	/**
	 * Get posted variation attributes for a bundle child item.
	 *
	 * @param string          $role Item role.
	 * @param int|string|null $index Item index.
	 * @return array<string, mixed>
	 */
	private function get_posted_variation_attributes($role, $index) {
		$posted = isset($_POST['wcpb_variation']) ? wp_unslash($_POST['wcpb_variation']) : array();

		if (! is_array($posted) || ! isset($posted[ $role ]) || ! is_array($posted[ $role ]) || null === $index || ! isset($posted[ $role ][ $index ]) || ! is_array($posted[ $role ][ $index ])) {
			return array();
		}

		return $posted[ $role ][ $index ];
	}

	/**
	 * Get the posted selected variation id for a bundle child item.
	 *
	 * @param string          $role Item role.
	 * @param int|string|null $index Item index.
	 * @return int
	 */
	private function get_posted_selected_variation_id($role, $index) {
		$posted = isset($_POST['wcpb_selected_variation']) ? wp_unslash($_POST['wcpb_selected_variation']) : array();

		if (! is_array($posted) || ! isset($posted[ $role ]) || ! is_array($posted[ $role ]) || null === $index || ! isset($posted[ $role ][ $index ])) {
			return 0;
		}

		return absint($posted[ $role ][ $index ]);
	}
}
