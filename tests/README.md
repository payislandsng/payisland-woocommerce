# Testing Notes

This Phase 1 plugin does not include a full WordPress/WooCommerce automated test harness.

Minimum checks before packaging:

- Run `php -l payisland-woocommerce.php`
- Run `find includes -name "*.php" -print0 | xargs -0 -n1 php -l`
- Activate the plugin with WooCommerce inactive and confirm no fatal error occurs
- Activate WooCommerce, enable PayIsland, and configure `secret_key` and `payment_item_id`
- Place a test order and confirm redirect to PayIsland authorization URL
- Return through `?wc-api=payisland_callback&reference=<reference>` and confirm the plugin verifies before completing the order
- Send a webhook to `?wc-api=payisland_webhook` and confirm fulfillment is idempotent
