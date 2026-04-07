<?php
/**
 * Single admin item row.
 *
 * @var array<string, mixed> $item
 * @var int|string           $index
 *
 * @package WCProductBundler
 */

if (! defined('ABSPATH')) {
	exit;
}

$role       = isset($item['type']) && 'optional' === $item['type'] ? 'optional' : 'included';
$name_root  = 'included' === $role ? 'wcpb_bundle_items' : 'wcpb_optional_items';
$input_name = $name_root . '[' . $index . ']';
?>
<div class="wcpb-item-row" data-role="<?php echo esc_attr($role); ?>" data-index="<?php echo esc_attr((string) $index); ?>">
	<div class="wcpb-item-header">
		<div class="wcpb-item-main">
			<label class="wcpb-field-label" for="<?php echo esc_attr('wcpb-product-' . $role . '-' . $index); ?>"><?php esc_html_e('Product', 'wc-product-bundler'); ?></label>
			<select
				id="<?php echo esc_attr('wcpb-product-' . $role . '-' . $index); ?>"
				class="wc-product-search wcpb-product-search"
				name="<?php echo esc_attr($input_name); ?>[product_id]"
				data-placeholder="<?php esc_attr_e('Search for a product or variation…', 'wc-product-bundler'); ?>"
				data-action="woocommerce_json_search_products_and_variations"
				data-allow_clear="true"
				data-exclude="0"
				data-limit="1">
				<?php if (! empty($item['product_id']) && ! empty($item['selected_text'])) : ?>
					<option value="<?php echo esc_attr((string) $item['product_id']); ?>" selected="selected"><?php echo esc_html((string) $item['selected_text']); ?></option>
				<?php endif; ?>
			</select>
			<input type="hidden" name="<?php echo esc_attr($input_name); ?>[variation_id]" value="<?php echo esc_attr((string) ($item['variation_id'] ?? 0)); ?>" class="wcpb-variation-id" />
		</div>
		<div class="wcpb-item-actions">
			<button type="button" class="button-link-delete wcpb-remove-row"><?php esc_html_e('Remove', 'wc-product-bundler'); ?></button>
		</div>
	</div>

	<div class="wcpb-item-settings" role="group" aria-label="<?php esc_attr_e('Bundle item settings', 'wc-product-bundler'); ?>">
		<label class="wcpb-number-field">
			<span class="wcpb-field-label"><?php esc_html_e('Default qty', 'wc-product-bundler'); ?></span>
			<input type="number" min="0" step="1" name="<?php echo esc_attr($input_name); ?>[default_qty]" value="<?php echo esc_attr((string) ($item['default_qty'] ?? 1)); ?>" />
		</label>
		<label class="wcpb-number-field">
			<span class="wcpb-field-label"><?php esc_html_e('Min qty', 'wc-product-bundler'); ?></span>
			<input type="number" min="0" step="1" name="<?php echo esc_attr($input_name); ?>[min_qty]" value="<?php echo esc_attr((string) ($item['min_qty'] ?? 0)); ?>" />
		</label>
		<label class="wcpb-number-field">
			<span class="wcpb-field-label"><?php esc_html_e('Max qty', 'wc-product-bundler'); ?></span>
			<input type="number" min="0" step="1" name="<?php echo esc_attr($input_name); ?>[max_qty]" value="<?php echo esc_attr((string) ($item['max_qty'] ?? 0)); ?>" />
		</label>
		<label class="wcpb-checkbox">
			<input type="checkbox" name="<?php echo esc_attr($input_name); ?>[show_summary]" value="1" <?php checked('yes', $item['show_summary'] ?? 'yes'); ?> />
			<span class="wcpb-checkbox-copy">
				<strong><?php esc_html_e('Show in summary', 'wc-product-bundler'); ?></strong>
				<small><?php esc_html_e('Display this item in the storefront bundle summary.', 'wc-product-bundler'); ?></small>
			</span>
		</label>
	</div>
</div>
