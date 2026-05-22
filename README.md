# PayIsland for WooCommerce

PayIsland for WooCommerce is the official WooCommerce payment gateway plugin for accepting payments through PayIsland.

This Phase 1 release provides a clean hosted-checkout payment flow:

- WooCommerce gateway registration
- PayIsland transaction initialization
- Redirect to PayIsland authorization URL
- Customer return callback handling
- Webhook endpoint structure with optional HMAC SHA256 signature verification
- Verification-before-fulfillment using the PayIsland transaction status endpoint
- WooCommerce order notes, order meta, and optional debug logs

It intentionally does not include subscriptions, refunds, split settlement UI, multi-currency controls, WooCommerce Blocks checkout support, or advanced admin dashboards.

## Requirements

- WordPress
- WooCommerce
- PHP 7.4 or newer
- A PayIsland secret key
- A PayIsland payment item ID

The plugin does not require Composer at runtime.

## Installation from GitHub ZIP

1. Download the repository as a ZIP file from GitHub.
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP file and activate **PayIsland for WooCommerce**.
4. Ensure WooCommerce is installed and active.

## WooCommerce Configuration

1. Go to **WooCommerce > Settings > Payments**.
2. Enable **PayIsland**.
3. Open the PayIsland settings screen.
4. Configure:
   - **Secret Key**: your PayIsland API secret key.
   - **Payment Item ID**: the PayIsland payment item ID for incoming transactions.
   - **Payment Channel**: `card`, `bank`, or `bank-transfer`.
   - **Order Status After Success**: keep WooCommerce default behavior or choose a specific status.
   - **Webhook Secret**: optional, if PayIsland provides a webhook signing secret.
   - **Debug Logging**: optional WooCommerce logs for requests and callbacks.

Sandbox and live mode are not selected in the plugin. PayIsland determines sandbox or live mode from the API key.

## Payment Flow

1. The customer chooses **Pay with PayIsland** at checkout.
2. The plugin creates a transaction reference such as `WC-123-1716380000`.
3. The plugin sends a `POST /api/v1/transactions/in/initialize` request to PayIsland.
4. PayIsland returns an authorization URL.
5. The plugin stores:
   - `_payisland_reference`
   - `_payisland_authorization_url`
6. The customer is redirected to PayIsland.
7. On callback or webhook, the plugin verifies the transaction through `GET /api/v1/transactions/in/check-transaction-status/{reference}` before updating the WooCommerce order.

## Callback and Webhook Notes

Customer return callback:

```text
?wc-api=payisland_callback
```

Webhook endpoint:

```text
?wc-api=payisland_webhook
```

The callback supports these query parameters:

- `reference`
- `transaction_reference`
- `trxref`

The webhook handler reads the raw JSON body, extracts the reference, verifies the transaction with PayIsland, then updates the WooCommerce order idempotently.

If a webhook secret is configured, the plugin verifies `X-PayIsland-Signature` as an HMAC SHA256 signature over the raw request body. Leave the webhook secret empty until PayIsland provides a signing secret.

## Payment Status Handling

Success statuses:

- `paid`
- `successful`
- `success`

Pending statuses:

- `pending`
- `unpaid`

Failed statuses:

- `failed`
- `cancelled`
- `canceled`
- `expired`
- `reversed`

Some 3DS or bank authorization flows may return a pending state before final confirmation. In that case, the plugin leaves the order awaiting confirmation and can later complete it through webhook verification.

## Development Notes

The plugin uses WordPress HTTP APIs:

- `wp_remote_post`
- `wp_remote_get`

Default PayIsland API headers:

```text
Content-Type: application/json
Accept: application/json
User-Agent: payisland-woocommerce
Authorization: Bearer <secretKey>
```

Minimum local syntax checks:

```bash
php -l payisland-woocommerce.php
find includes -name "*.php" -print0 | xargs -0 -n1 php -l
```

Manual testing checklist is available in `tests/README.md`.

## License

MIT. See `LICENSE`.
