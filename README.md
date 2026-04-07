# WC Product Bundler

WC Product Bundler adds a custom WooCommerce `Bundle` product type for selling a fixed set of included products with optional add-ons.

## Features

- Custom WooCommerce bundle product type.
- Included bundle items with default quantities.
- Optional add-ons with minimum and maximum quantity controls.
- Variable child product support, including storefront variation selection before add to cart.
- Fixed bundle pricing, percentage discounts, or fixed discount amounts.
- Storefront layouts for stacked lists and compact bundle cards.
- Tailwind-inspired storefront style variants: default blue, slate neutral, and emerald green.
- Optional "You save" badge when the bundle discount can be calculated.
- Parent and child cart item handling so bundle children stay linked to the bundle.
- Cart, mini-cart, checkout, and order summary support for bundle line item details.

## Requirements

- WordPress
- WooCommerce
- PHP compatible with the active WooCommerce installation

## Installation

1. Place this plugin folder in `wp-content/plugins/wc-product-bundler`.
2. Activate **WC Product Bundler** from the WordPress Plugins screen.
3. Make sure WooCommerce is installed and active.

## Creating a Bundle

1. Create or edit a WooCommerce product.
2. Set the product type to **Bundle**.
3. Configure pricing in the **General** product data tab:
   - **Fixed bundle price**
   - **Percentage discount**
   - **Fixed discount amount**
4. Choose a storefront **Bundle layout**:
   - **Stacked list**
   - **Compact summary**
5. Choose a **Bundle style** variant.
6. Enable **Bundle savings** if you want the storefront card to display `You save` with the calculated discount amount.
7. Open the **Bundled Items** tab.
8. Add required included products and optional add-ons.
9. Publish or update the product.

## Variable Products

If a bundled item is a variable product and no specific variation is selected in the admin, shoppers choose the variation on the bundle product page. The add-to-cart flow validates required variation choices before the bundle is added.

## Styling

The storefront CSS is scoped under the bundle classes and uses Tailwind default design tokens for colors, spacing, borders, focus rings, and small shadows. The bundled styles do not require a Tailwind build pipeline.

Available style classes:

- `wcpb-style-default`
- `wcpb-style-slate`
- `wcpb-style-emerald`

Available layout classes:

- `wcpb-layout-stacked`
- `wcpb-layout-compact`

## Development Checks

Useful syntax checks:

```bash
lando php -l includes/Support/class-product-data.php
lando php -l includes/Admin/class-bundle-product-data.php
lando php -l includes/Frontend/class-bundle-template-manager.php
lando php -l templates/single-product/add-to-cart-bundle.php
node --check assets/js/frontend.js
```

## Notes

Nested bundle products are intentionally blocked in this first version.
