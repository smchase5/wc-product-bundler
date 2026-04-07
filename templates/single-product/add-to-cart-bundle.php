<?php
/**
 * Bundle add-to-cart template.
 *
 * @var WC_Product                    $product
 * @var array<int, array<string,mixed>> $included_items
 * @var array<int, array<string,mixed>> $optional_items
 * @var string                        $layout_style
 * @var string                        $style_variant
 * @var bool                          $show_savings
 * @var float                         $savings_amount
 * @var \WCPB\Support\Product_Data    $product_data
 *
 * @package WCProductBundler
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! $product->is_purchasable()) {
	return;
}

echo wc_get_stock_html($product);

if (! $product->is_in_stock()) {
	return;
}

$layout_style  = isset($layout_style) && in_array($layout_style, array('stacked', 'compact'), true) ? $layout_style : 'stacked';
$style_variant = isset($style_variant) && in_array($style_variant, array('default', 'slate', 'emerald'), true) ? $style_variant : 'default';
$show_savings  = ! empty($show_savings);
$savings_amount = isset($savings_amount) ? (float) $savings_amount : 0.0;
?>
<?php do_action('woocommerce_before_add_to_cart_form'); ?>

<form class="cart wcpb-bundle-form wcpb-layout-<?php echo esc_attr($layout_style); ?> wcpb-style-<?php echo esc_attr($style_variant); ?>" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype="multipart/form-data">
	<div class="wcpb-bundle-details woocommerce-product-details__short-description">
		<?php if ($show_savings && $savings_amount > 0) : ?>
			<div class="wcpb-savings-badge">
				<span class="wcpb-savings-label"><?php esc_html_e('You save', 'wc-product-bundler'); ?></span>
				<span class="wcpb-savings-amount"><?php echo wp_kses_post(wc_price($savings_amount)); ?></span>
			</div>
		<?php endif; ?>

		<section class="wcpb-section">
			<h3><?php esc_html_e('Included in this bundle', 'wc-product-bundler'); ?></h3>
			<ul class="wcpb-item-summary">
				<?php foreach ($included_items as $index => $item) : ?>
					<?php $child = $product_data->get_resolved_product($item); ?>
					<?php if (! ($child instanceof WC_Product)) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<li class="wcpb-summary-item">
						<div class="wcpb-summary-copy">
							<span class="wcpb-summary-name"><?php echo esc_html($child->get_name()); ?></span>
							<span class="wcpb-summary-qty">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d quantity */
										__('Qty: %d', 'wc-product-bundler'),
										max(1, (int) $item['default_qty'])
									)
								);
								?>
							</span>
							<?php if ($product_data->item_requires_variation_selection($item)) : ?>
								<?php $variable_product = $product_data->get_variable_parent_product($item); ?>
								<?php if ($variable_product instanceof WC_Product_Variable) : ?>
									<div class="wcpb-child-variations" data-required="yes" data-product-variations="<?php echo esc_attr(wc_esc_json(wp_json_encode($variable_product->get_available_variations()))); ?>">
										<input type="hidden" class="wcpb-selected-variation-id" name="wcpb_selected_variation[included][<?php echo esc_attr((string) $index); ?>]" value="" />
										<?php foreach ($variable_product->get_variation_attributes() as $attribute_name => $options) : ?>
											<div class="wcpb-child-variation-field">
												<label for="<?php echo esc_attr('wcpb-' . sanitize_title($attribute_name) . '-included-' . $index); ?>"><?php echo esc_html(wc_attribute_label($attribute_name, $variable_product)); ?></label>
												<?php
												wc_dropdown_variation_attribute_options(
													array(
														'options'   => $options,
														'attribute' => $attribute_name,
														'product'   => $variable_product,
														'name'      => 'wcpb_variation[included][' . $index . '][' . esc_attr($attribute_name) . ']',
														'id'        => 'wcpb-' . sanitize_title($attribute_name) . '-included-' . $index,
														'class'     => 'wcpb-variation-select',
													)
												);
												?>
											</div>
										<?php endforeach; ?>
										<p class="wcpb-variation-status" aria-live="polite">
											<span class="wcpb-variation-status-pending"><?php esc_html_e('Choose product options to continue.', 'wc-product-bundler'); ?></span>
											<span class="wcpb-variation-status-success"><?php esc_html_e('Options selected.', 'wc-product-bundler'); ?></span>
											<span class="wcpb-variation-status-error"><?php esc_html_e('This combination is unavailable.', 'wc-product-bundler'); ?></span>
										</p>
									</div>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<?php if (! empty($optional_items)) : ?>
			<section class="wcpb-section">
				<h3><?php esc_html_e('Optional add-ons', 'wc-product-bundler'); ?></h3>
				<ul class="wcpb-optional-items">
					<?php foreach ($optional_items as $index => $item) : ?>
						<?php $child = $product_data->get_resolved_product($item); ?>
						<?php if (! ($child instanceof WC_Product)) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<li class="wcpb-optional-item">
							<div class="wcpb-optional-copy">
								<span class="wcpb-optional-name"><?php echo esc_html($child->get_name()); ?></span>
								<?php if ($child->get_short_description()) : ?>
									<span class="wcpb-optional-description"><?php echo esc_html(wp_strip_all_tags($child->get_short_description())); ?></span>
								<?php endif; ?>
								<?php if ($product_data->item_requires_variation_selection($item)) : ?>
									<?php $variable_product = $product_data->get_variable_parent_product($item); ?>
									<?php if ($variable_product instanceof WC_Product_Variable) : ?>
										<div class="wcpb-child-variations" data-required="no" data-product-variations="<?php echo esc_attr(wc_esc_json(wp_json_encode($variable_product->get_available_variations()))); ?>">
											<input type="hidden" class="wcpb-selected-variation-id" name="wcpb_selected_variation[optional][<?php echo esc_attr((string) $index); ?>]" value="" />
											<?php foreach ($variable_product->get_variation_attributes() as $attribute_name => $options) : ?>
												<div class="wcpb-child-variation-field">
													<label for="<?php echo esc_attr('wcpb-' . sanitize_title($attribute_name) . '-optional-' . $index); ?>"><?php echo esc_html(wc_attribute_label($attribute_name, $variable_product)); ?></label>
													<?php
													wc_dropdown_variation_attribute_options(
														array(
															'options'   => $options,
															'attribute' => $attribute_name,
															'product'   => $variable_product,
															'name'      => 'wcpb_variation[optional][' . $index . '][' . esc_attr($attribute_name) . ']',
															'id'        => 'wcpb-' . sanitize_title($attribute_name) . '-optional-' . $index,
															'class'     => 'wcpb-variation-select',
														)
													);
													?>
												</div>
											<?php endforeach; ?>
											<p class="wcpb-variation-status" aria-live="polite">
												<span class="wcpb-variation-status-pending"><?php esc_html_e('Choose product options when adding this add-on.', 'wc-product-bundler'); ?></span>
												<span class="wcpb-variation-status-success"><?php esc_html_e('Options selected.', 'wc-product-bundler'); ?></span>
												<span class="wcpb-variation-status-error"><?php esc_html_e('This combination is unavailable.', 'wc-product-bundler'); ?></span>
											</p>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
							<label class="wcpb-optional-quantity">
								<span class="screen-reader-text"><?php echo esc_html(sprintf(__('Quantity for %s', 'wc-product-bundler'), $child->get_name())); ?></span>
								<input
									type="number"
									class="input-text qty text"
									name="wcpb_optional_qty[<?php echo esc_attr((string) $index); ?>]"
									min="<?php echo esc_attr((string) ($item['min_qty'] ?? 0)); ?>"
									<?php if (! empty($item['max_qty'])) : ?>
										max="<?php echo esc_attr((string) $item['max_qty']); ?>"
									<?php endif; ?>
									step="1"
									value="0"
								/>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</div>

	<?php do_action('woocommerce_before_add_to_cart_button'); ?>
	<?php do_action('woocommerce_before_add_to_cart_quantity'); ?>

	<?php
	woocommerce_quantity_input(
		array(
			'min_value'   => $product->get_min_purchase_quantity(),
			'max_value'   => $product->get_max_purchase_quantity(),
			'input_value' => isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : $product->get_min_purchase_quantity(),
		)
	);
	?>

	<?php do_action('woocommerce_after_add_to_cart_quantity'); ?>

	<button type="submit" name="add-to-cart" value="<?php echo esc_attr((string) $product->get_id()); ?>" class="single_add_to_cart_button button alt wcpb-add-to-cart<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>">
		<?php echo esc_html($product->single_add_to_cart_text()); ?>
	</button>

	<?php do_action('woocommerce_after_add_to_cart_button'); ?>
</form>

<?php do_action('woocommerce_after_add_to_cart_form'); ?>
