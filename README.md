# Aspen Membership Add-on Rules

Aspen Membership Add-on Rules is a lightweight WordPress plugin that limits tagged WooCommerce add-on products to customers who either:

1. Have an active membership in a configured WooCommerce Memberships plan; or
2. Have a product that grants access to that plan in the current cart.

The plugin does not grant memberships or maintain its own list of qualifying subscription products. WooCommerce Memberships remains the source of truth for the products that grant access to each plan.

## Requirements

- WordPress with PHP 7.4 or newer.
- WooCommerce.
- WooCommerce Subscriptions.
- WooCommerce Memberships.

All three WooCommerce extensions must be active. If a dependency is unavailable, the rule engine does not start and administrators with the `manage_woocommerce` capability see an error notice instead of a fatal error.

## User guide

### Installation

1. Prefer the release asset named `aspen-addon-manager.zip`. Developers can also generate it with the included build script.
2. Upload the ZIP through **Plugins → Add New → Upload Plugin**, or copy its extracted directory into `wp-content/plugins/`. The ZIP must have exactly one top-level directory with `aspen-addon-manager.php` directly inside it; do not add another wrapper directory. A current GitHub source archive also follows this discoverable layout, although the release ZIP excludes repository-only files.
3. Confirm WooCommerce, WooCommerce Subscriptions, and WooCommerce Memberships are installed and active.
4. Activate **Aspen Membership Add-on Rules**.
5. Open **Settings → Membership Add-on Rules**.

### Before creating a rule

1. In WooCommerce Memberships, configure the membership plan and the products whose purchase grants access to it.
2. Create or select a WooCommerce product tag for the restricted add-ons.
3. Assign that tag to every applicable add-on product. For variable products, assigning the tag to the parent product governs all its variations.
4. Remove any WooCommerce Memberships **Only Members Can Purchase** restriction from those add-ons.

The last step is important. This plugin must own the add-on purchase restriction so it can provisionally allow a non-member whose qualifying membership product is only in the cart. The plugin never changes a WooCommerce `false` purchasability result back to `true`, so an overlapping Memberships restriction cannot be bypassed safely.

### Creating a rule

1. Go to **Settings → Membership Add-on Rules**.
2. Enter an administrator-facing **Rule name**.
3. Select whether the rule is **Enabled**.
4. Select a published **Membership plan**.
5. Select an existing **Add-on product tag**. The number beside each tag is the current count of published products using it.
6. Optionally enter a customer-facing **Restriction message**. If left blank, the plugin uses:

   > This add-on requires an active membership or a qualifying membership product in your cart.

7. Select **Add rule**.

An enabled plan-and-tag relationship may only be configured once. A duplicate can be stored only when it is disabled; attempting to enable it while an identical relationship is enabled results in a validation error.

### Managing rules

The rules table provides the following actions:

- **Edit** updates the rule name, status, membership plan, tag, or message.
- **Enable/Disable** changes whether the relationship is enforced immediately.
- **Delete** permanently removes the relationship after confirmation.

The diagnostics column warns when:

- The selected membership plan is missing or no longer published.
- The plan has no access-granting products.
- The selected product tag was deleted.
- No published products currently use the tag.

The page also reminds administrators to remove overlapping Memberships purchase restrictions. Checking the internals of Memberships restriction rules is intentionally not coupled to this version of the plugin.

### Customer behavior

- An active member can buy a matching add-on without adding a membership product.
- A guest or non-member can add a qualifying membership product first and then add the matching add-on.
- If a non-member removes the last qualifying product, the add-on remains in the cart, an error is displayed, and checkout is blocked until the qualifying product is restored or the add-on is removed.
- If the customer is already an active member, removing a qualifying cart product does not invalidate the add-on.
- When several enabled rules match an add-on, satisfying any one rule is sufficient.
- Stock, price, publication, variation availability, and restrictions imposed by other extensions continue to apply.

Restricted archive products link to their product page instead of exposing an AJAX add-to-cart action. A restricted single-product page displays the configured message and, when available, links to published products that grant plan access.

### Updating membership products

Change access-granting products directly on the WooCommerce Memberships plan. The plugin reads the plan on every request (with request-local caching), so no add-on rule needs to be resaved after that configuration changes.

### Recommended staging checklist

Before production deployment, test:

- An active, expired, and logged-out customer.
- Simple and variable membership products, including plans attached to a parent product or a specific variation.
- Simple add-ons and variations inheriting a tag from their parent.
- Direct add-to-cart URLs and AJAX add-to-cart.
- Classic cart and checkout shortcodes.
- WooCommerce Cart and Checkout blocks.
- Removing and restoring the last qualifying membership product.
- Login during checkout.
- A complete subscription purchase followed by the expected Memberships access grant.
- Out-of-stock, unpriced, and unpublished add-ons to ensure the plugin never makes them purchasable.

## Developer guide

### Architecture

The plugin uses consistently prefixed classes and has no build step or JavaScript dependency:

```text
aspen-addon-manager/
├── aspen-addon-manager.php
├── README.md
└── includes/
    ├── class-admin.php
    ├── class-eligibility.php
    ├── class-notices.php
    ├── class-plugin.php
    ├── class-purchase-restrictions.php
    └── class-rule-repository.php
```

- `aspen-addon-manager.php` defines plugin metadata and constants, loads the classes, and boots on `plugins_loaded`.
- `Aspen_Membership_Addon_Rules_Plugin` verifies dependencies and wires services.
- `Aspen_Membership_Addon_Rules_Repository` owns all reads and writes for the versioned option.
- `Aspen_Membership_Addon_Rules_Eligibility` contains product matching, active-membership checks, access-product lookup, and OR-based eligibility.
- `Aspen_Membership_Addon_Rules_Purchase_Restrictions` connects the rule engine to classic WooCommerce, Store API, product, and archive hooks.
- `Aspen_Membership_Addon_Rules_Admin` implements the capability-protected CRUD interface and diagnostics.
- `Aspen_Membership_Addon_Rules_Notices` reports missing dependencies safely.

### Stored data

All rules are stored in the single `aspen_membership_addon_rules` option:

```php
array(
    'version' => 1,
    'rules'   => array(
        array(
            'id'                  => 'stable-generated-uuid',
            'name'                => 'Aspen Nexus Foundation Add-ons',
            'enabled'             => true,
            'membership_plan_id'  => 123,
            'product_tag_term_id' => 456,
            'restriction_message' => 'This add-on requires ...',
        ),
    ),
);
```

The tag term ID is stored so a tag rename does not break a rule. Membership access-product IDs are deliberately not persisted.

### Matching and eligibility

For a simple product, matching checks its product ID. For a variation, matching checks both the variation and parent product IDs for the configured `product_tag` term.

For every matching enabled rule, eligibility is true when either:

- `wc_memberships_get_user_active_memberships()` returns an active membership whose plan ID matches; or
- The current `WC_Cart` includes one of the IDs returned by the membership plan's public `get_product_ids()` method.

Cart comparison normalizes the cart item's product ID, variation ID, and variation parent ID. Plan products and membership results are cached only for the current PHP request. Multiple matching rules use OR semantics.

### WooCommerce integration points

The restriction service registers these hooks:

| Hook | Purpose |
| --- | --- |
| `woocommerce_is_purchasable` | Restrict simple and other product types. |
| `woocommerce_variation_is_purchasable` | Restrict individual variations. |
| `woocommerce_add_to_cart_validation` | Validate classic, direct, AJAX, and compatible third-party add-to-cart requests. |
| `woocommerce_check_cart_items` | Re-evaluate the complete classic cart and checkout. |
| `woocommerce_store_api_validate_add_to_cart` | Reject invalid Store API additions. |
| `woocommerce_store_api_validate_cart_item` | Re-evaluate Store API cart items and block checkout. |
| `woocommerce_loop_add_to_cart_link` | Replace an unavailable archive add-to-cart link with a requirements link. |
| `woocommerce_single_product_summary` | Display the restriction message and qualifying product links. |

Purchasability filtering is intentionally one-way: an ineligible governed product changes the incoming result to `false`; an eligible or ungoverned product returns the incoming result unchanged. Do not replace this behavior with an unconditional `true`, and do not call the filtered `WC_Product::is_purchasable()` method from inside these filters.

Store API validation throws `Automattic\WooCommerce\StoreApi\Exceptions\RouteException` when that class is available. Classic validation uses WooCommerce error notices and returns `false` from its validation filter.

### Security and compatibility

- Page access and every mutation require `manage_options`, matching the capability required to access the WordPress Settings menu.
- Create, update, delete, enable, and disable requests require WordPress nonces.
- Input is sanitized before storage and rendered values are escaped.
- Product, taxonomy, membership, and cart operations use WordPress and WooCommerce APIs; there are no direct database or order-table queries.
- The implementation is compatible with HPOS because it does not access order storage.
- Missing plans, tags, products, variations, carts, or plugin dependencies fail closed or safely.
- User-facing strings use the `aspen-membership-addon-rules` text domain.

### Extending the plugin

Keep business logic in the eligibility service rather than duplicating it in hook callbacks. When adding a new request surface, normalize its product to a `WC_Product`, call `get_rules_for_product()`, and use `qualifies_for_any()`.

If the option schema changes, increment its `version` and add an explicit migration before consuming the new structure. Avoid persisting access-granting product IDs; retrieving them from Memberships is required to preserve the source-of-truth behavior.

### Development checks

There is no compilation step or dependency installation. At minimum, lint every PHP file and check patch whitespace before committing:

```bash
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
```

Functional acceptance testing requires a WordPress staging installation with WooCommerce, WooCommerce Subscriptions, and WooCommerce Memberships. Both classic and block-based cart/checkout paths should be exercised because they use different validation hooks.

### Building an installable ZIP

The repository contains development files that should not be deployed as part of the plugin. Build the WordPress-ready archive from the repository root:

```bash
./bin/build-plugin-zip.sh
```

The command creates `build/aspen-addon-manager.zip`. Its archive layout is:

```text
aspen-addon-manager/
├── aspen-addon-manager.php
├── README.md
└── includes/
```

Upload that generated ZIP in WordPress. The build directory is ignored by Git.

## Support boundaries

Version 1 does not automatically add or remove cart products, create or modify Memberships restrictions, grant memberships, change renewals, apply discounts, inspect billing periods, or integrate specially with bundles, composites, force-sells, or product add-on extensions.
