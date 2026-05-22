# PayIsland for WooCommerce

PayIsland for WooCommerce is the official WooCommerce payment gateway plugin for accepting payments through PayIsland.

This Phase 1 release provides a clean hosted-checkout payment flow:

- WooCommerce gateway registration
- Modern WooCommerce Checkout Block support
- Classic `[woocommerce_checkout]` shortcode checkout support
- PayIsland transaction initialization
- Redirect to PayIsland authorization URL
- Customer return callback handling
- Webhook endpoint structure with optional HMAC SHA256 signature verification
- Verification-before-fulfillment using the PayIsland transaction status endpoint
- WooCommerce order notes, order meta, and optional debug logs

It intentionally does not include subscriptions, refunds, split settlement UI, multi-currency controls, or advanced admin dashboards.

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
   - **Webhook URL**: the public POST endpoint PayIsland should call for payment notifications. The default is the plugin webhook endpoint.
   - **Order Status After Success**: keep WooCommerce default behavior or choose a specific status.
   - **Webhook Secret**: optional, if PayIsland provides a webhook signing secret.
   - **Debug Logging**: optional WooCommerce logs for requests and callbacks.

Sandbox and live mode are not selected in the plugin. PayIsland determines sandbox or live mode from the API key.

PayIsland supports both the modern WooCommerce Checkout Block and the classic `[woocommerce_checkout]` shortcode checkout. The plugin uses PayIsland hosted checkout, so customers are redirected to PayIsland after placing the order instead of entering card details in an embedded form on your site.

If PayIsland does not appear in the Checkout Block or classic checkout payment methods, confirm that the gateway is enabled and that the required PayIsland settings are configured under **WooCommerce > Settings > Payments > PayIsland**.

## Payment Flow

1. The customer chooses **Pay with PayIsland** at checkout.
2. The plugin creates a merchant/client transaction reference such as `WC-123-1716380000`.
3. The plugin sends a `POST /api/v1/transactions/in/initialize` request to PayIsland.
4. The initialize payload includes the configured webhook URL as `callback_url`, `customer_info.phone_number`, and the WooCommerce order total as a major-unit string amount.
5. PayIsland returns an authorization URL and PayIsland transaction reference.
6. The plugin stores:
   - `_payisland_reference` for the PayIsland-generated reference, such as `PISL...` or `PIST...`
   - `_payisland_client_reference` for the WooCommerce-generated client reference
   - `_payisland_authorization_url`
7. The customer is redirected to PayIsland.
8. On callback or webhook, the plugin verifies the transaction through `GET /api/v1/transactions/in/check-transaction-status/{reference}` using the PayIsland-generated reference before updating the WooCommerce order.

## API Endpoint Alignment

The plugin follows the PayIsland quickstart endpoints:

- Initialize transaction: `POST /api/v1/transactions/in/initialize`
- Verify transaction: `GET /api/v1/transactions/in/check-transaction-status/{reference}`

PayIsland’s initialize response returns `data.reference`, which is the reference used for verification, webhook lookup, and support. The plugin stores the WooCommerce-generated `transaction_reference` separately as `_payisland_client_reference`.

Amounts are sent to PayIsland as strings in the major currency unit, for example `"7000"` for ₦7,000. Whole amounts such as `7000.00` are sent without trailing decimals.

PayIsland webhook URL setting:

```text
?wc-api=payisland_webhook
```

The webhook URL can be changed in **WooCommerce > Settings > Payments > PayIsland** if a store needs to expose a different public endpoint.

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

## Local Docker Testing

This repository includes a Docker Compose environment for testing the plugin without installing PHP on the host.

Start WordPress and MySQL:

```bash
docker compose up -d
```

Then open:

```text
http://localhost:8080
```

Complete the WordPress setup wizard in the browser. The Compose file uses local-only throwaway database credentials and mounts this repository into:

```text
/var/www/html/wp-content/plugins/payisland-woocommerce
```

### Install WooCommerce

1. Log in to WordPress admin at `http://localhost:8080/wp-admin`.
2. Go to **Plugins > Add New**.
3. Search for **WooCommerce**.
4. Install and activate WooCommerce.
5. Complete the WooCommerce setup wizard with any local test store details.

### Activate PayIsland

1. Go to **Plugins > Installed Plugins**.
2. Activate **PayIsland for WooCommerce**.
3. Go to **WooCommerce > Settings > Payments**.
4. Enable **PayIsland**.

### Configure the Gateway

In **WooCommerce > Settings > Payments > PayIsland**, configure:

- **Secret Key**: use a PayIsland sandbox or test key. Do not commit real keys.
- **Payment Item ID**: use the PayIsland payment item ID for testing.
- **Payment Channel**: choose `card`, `bank`, or `bank-transfer`.
- **Webhook Secret**: optional, only if PayIsland provides a test webhook signing secret.
- **Debug Logging**: enable while testing if you need WooCommerce logs.

Sandbox and live mode are still controlled by the PayIsland API key. There is no plugin environment selector.

### Manual Test Checklist

- Confirm WordPress loads at `http://localhost:8080`.
- Confirm WooCommerce installs and activates successfully.
- Confirm **PayIsland for WooCommerce** activates without fatal errors.
- Confirm **PayIsland** appears under **WooCommerce > Settings > Payments**.
- Configure a test secret key and payment item ID.
- Create a simple WooCommerce product.
- Place an order using PayIsland at checkout.
- Confirm checkout redirects to the PayIsland authorization URL.
- Confirm the order stores `_payisland_reference` and `_payisland_authorization_url`.
- Test the callback URL with a known reference: `http://localhost:8080/?wc-api=payisland_callback&reference=<reference>`.
- Confirm callback handling verifies the transaction before completing, pending, or failing the order.
- Send a webhook request to `http://localhost:8080/?wc-api=payisland_webhook`.
- Confirm webhook handling verifies the transaction before fulfillment and is safe to retry.

Stop the environment:

```bash
docker compose down
```

Remove the local database volume if you want a fresh WordPress install:

```bash
docker compose down -v
```

## License

MIT. See `LICENSE`.
