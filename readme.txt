=== PayIsland for WooCommerce ===
Contributors: payisland
Tags: woocommerce, payment gateway, payments, nigeria, payisland
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Accept payments on WooCommerce using PayIsland.

== Description ==

PayIsland for WooCommerce lets WooCommerce merchants accept payments through PayIsland using a hosted checkout flow.

This Phase 1 release initializes PayIsland transactions, redirects customers to PayIsland, verifies transaction status on callback or webhook, and updates WooCommerce orders after verification.

Sandbox or live mode is determined by the PayIsland API key. The plugin does not include an environment selector.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate PayIsland for WooCommerce.
3. Ensure WooCommerce is installed and active.
4. Go to WooCommerce > Settings > Payments.
5. Enable PayIsland.
6. Configure your PayIsland secret key, payment item ID, and preferred payment channel.

== Frequently Asked Questions ==

= Does this plugin require Composer? =

No. Phase 1 does not require Composer at runtime.

= How do I choose sandbox or live mode? =

You do not choose it in WordPress. PayIsland determines sandbox or live mode from the API key.

= Which payment channels are supported? =

The Phase 1 settings allow card, bank, and bank-transfer.

= Does the plugin support refunds or subscriptions? =

Not yet. Refunds, subscriptions, split settlement UI, multi-currency settings, and Blocks checkout support are intentionally excluded from Phase 1.

= What callback URL should I configure? =

Use the WooCommerce API callback endpoint:

`?wc-api=payisland_callback`

For webhooks, use:

`?wc-api=payisland_webhook`

= How are webhooks secured? =

If PayIsland provides a webhook secret, add it to the gateway settings. The plugin will verify `X-PayIsland-Signature` using HMAC SHA256 over the raw request body.

== Changelog ==

= 0.1.0 =

* Initial Phase 1 WooCommerce payment gateway foundation.
