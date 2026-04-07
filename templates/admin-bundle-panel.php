<?php
/**
 * Admin bundle panel template.
 *
 * @var array<int, array<string, mixed>> $included_items
 * @var array<int, array<string, mixed>> $optional_items
 *
 * @package WCProductBundler
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<div id="wcpb_bundle_options" class="panel woocommerce_options_panel hidden">
	<div class="options_group wcpb-panel">
		<p class="form-field">
			<label><?php esc_html_e('Included products', 'wc-product-bundler'); ?></label>
			<span class="description"><?php esc_html_e('Required products that always ship as part of the bundle.', 'wc-product-bundler'); ?></span>
		</p>
		<div class="wcpb-item-group" data-role="included">
			<div class="wcpb-item-list">
				<?php foreach ($included_items as $index => $item) : ?>
					<?php include WCPB_PATH . 'templates/admin-item-row.php'; ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button wcpb-add-row" data-role="included"><?php esc_html_e('Add included product', 'wc-product-bundler'); ?></button>
			</p>
		</div>
	</div>

	<div class="options_group wcpb-panel">
		<p class="form-field">
			<label><?php esc_html_e('Optional add-ons', 'wc-product-bundler'); ?></label>
			<span class="description"><?php esc_html_e('Products customers may choose before adding the bundle to cart.', 'wc-product-bundler'); ?></span>
		</p>
		<div class="wcpb-item-group" data-role="optional">
			<div class="wcpb-item-list">
				<?php foreach ($optional_items as $index => $item) : ?>
					<?php include WCPB_PATH . 'templates/admin-item-row.php'; ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button wcpb-add-row" data-role="optional"><?php esc_html_e('Add optional add-on', 'wc-product-bundler'); ?></button>
			</p>
		</div>
	</div>
</div>

<script type="text/html" id="tmpl-wcpb-item-row">
	<?php
	$item  = array(
		'product_id'    => 0,
		'variation_id'  => 0,
		'default_qty'   => 1,
		'min_qty'       => 0,
		'max_qty'       => 0,
		'show_summary'  => 'yes',
		'selected_text' => '',
		'type'          => 'included',
	);
	$index = '{{{data.index}}}';
	include WCPB_PATH . 'templates/admin-item-row.php';
	?>
</script>
