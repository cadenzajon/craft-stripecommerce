# Stripe Commerce for Craft CMS

A session cart and Stripe Checkout layer for Craft CMS, with configurable pricing tiers (for example retail and wholesale). It builds on the official free [`craftcms/stripe`](https://github.com/craftcms/stripe) plugin, which syncs products and prices but does not provide a cart. This plugin adds the cart. It requires no Craft Commerce license, no JavaScript framework, and no build step.

> **Status: pre-release.** This README is the product spec. The API described here is the contract the implementation will follow. Expect changes until `1.0.0`.

## Why this exists

Options for a small store on Craft today:

| Option | Problem |
| --- | --- |
| Craft Commerce | $1,199 license plus renewals; more than a catalog store with simple shipping needs |
| Official `craftcms/stripe` plugin | Free, but checkout is per-product; no cart |
| Paid cart plugins | Proprietary licenses, annual renewals |
| Custom code | Every site rewrites the same session-cart module |

This project is that session-cart module as a reusable MIT-licensed plugin: a cart, a checkout handoff, and a pricing-tier gate.

## Design principles

1. Stripe holds prices and processes payments; Craft holds content. Products and prices are authored in Stripe and synced into Craft as elements by the official plugin. Images, descriptions, categories, and custom fields live on those elements in Craft. This plugin never stores an amount, computes a total, or handles a card number.
2. The client is not trusted with prices. The browser submits product IDs and quantities. The server resolves each product to a Stripe price ID based on the visitor's pricing tier.
3. No JavaScript required. Every cart interaction is an HTML form POST rendered by Twig. Sites can add `fetch()` for no-reload interactions, but nothing depends on it.
4. Two dependencies: Craft CMS 5 and `craftcms/stripe` (which brings `stripe/stripe-php`). No frontend packages.
5. Small feature set, events at each decision point. Site-specific behavior (shipping logic, tier rules, analytics) belongs in your site module.

## Scope

### Session cart

- Server-side cart stored in the PHP session as `[{productId, qty}, …]`.
- Controller actions `cart/add`, `cart/update`, `cart/remove`, and `cart/clear`. All accept form POSTs with CSRF protection and redirect back, or return JSON when the request sends `Accept: application/json`.
- Twig variable `craft.stripeCart` with `items` (hydrated with the synced product elements), `count`, and `isEmpty`.
- Cart lifetime follows Craft's session and remember-me configuration. No separate storage.

### Pricing tiers

- Tiers are defined in `config/stripe-commerce.php`. Each tier maps to a Stripe price metadata value (default key `tier`, values such as `retail` and `wholesale`).
- Each product in Stripe carries one price per tier. The plugin resolves the active tier's price ID at cart-render and checkout time, on the server.
- The default tier applies to all visitors. Other tiers activate per session by:
  - Access code: a controller action validates a configured passcode and flags the session. Works on Craft Solo, which has no front-end users.
  - User group: on Craft Pro, membership in a configured group activates a tier.
- A `resolveTier` event supports other rules (IP allowlist, signed URL, time window).

### Checkout

- The `checkout` action converts the session cart into a Stripe Checkout Session, one line item per cart row, using the tier-resolved price IDs. It then redirects (hosted mode) or returns the client secret (embedded mode).
- Success and cancel URLs, `ui_mode`, shipping-address collection, and shipping rates are config options passed to Stripe.
- A `beforeCheckout` event exposes the full session params before they are sent, following the same pattern as the official plugin.
- On the `checkout.session.completed` webhook (received by the official plugin), the cart is cleared and an `orderCompleted` event fires with the session payload.

### Non-goals

Stripe or full commerce platforms already cover these; reimplementing them would add surface without value:

- Order management UI. The Stripe Dashboard handles refunds, receipts, exports, and tax reports.
- Promotions and coupons. Enable Stripe promotion codes with one config flag.
- Tax. Use Stripe Tax, configured in Stripe.
- Shipping rate engine. Flat and tiered rates come from Stripe shipping rate objects. Anything beyond that belongs in a site module via `beforeCheckout`.
- Product and price CRUD. Author in the Stripe Dashboard; the official plugin syncs.
- Subscriptions. The official plugin handles these.
- Payment processing. Stripe Checkout only. No card fields render on your server.

## Requirements

- Craft CMS 5.6+ (Solo works; Pro adds user-group tiers)
- [`craftcms/stripe`](https://plugins.craftcms.com/stripe) 1.3+
- PHP 8.2+
- A Stripe account

## Quick start (target developer experience)

```bash
composer require cadenzajon/craft-stripecommerce
php craft plugin/install stripe-commerce
```

```php
// config/stripe-commerce.php
return [
    'tiers' => [
        'retail'    => ['default' => true],
        'wholesale' => ['accessCode' => getenv('WHOLESALE_CODE')],
    ],
    'priceTierMetadataKey' => 'tier',
    'checkout' => [
        'successUrl' => 'shop/thanks?session={CHECKOUT_SESSION_ID}',
        'cancelUrl'  => 'shop/cart',
        'shippingCountries' => ['US', 'CA'],
        'allowPromotionCodes' => true,
    ],
];
```

```twig
{# Product page: add to cart #}
<form method="post">
  {{ csrfInput() }}
  {{ actionInput('stripe-commerce/cart/add') }}
  {{ hiddenInput('productId', product.id) }}
  <input type="number" name="qty" value="1" min="1">
  <button>Add to cart</button>
</form>

{# Anywhere: cart badge #}
<a href="/cart">Cart ({{ craft.stripeCart.count }})</a>

{# Cart page #}
{% for item in craft.stripeCart.items %}
  {{ item.product.title }} × {{ item.qty }} — {{ item.price.data|unitAmount }}
{% endfor %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('stripe-commerce/checkout') }}
  <button>Check out</button>
</form>
```

Stripe Checkout shows the tier-resolved prices, takes payment, and sends the visitor back to your thank-you page.

## Architecture

```
Browser (HTML forms, no JS required)
   │  productId + qty only, never prices
   ▼
CartController ── CartService (PHP session)
   │                    │
   │                    ▼
   │              TierResolver ──▶ resolveTier event
   ▼                    │
CheckoutController ─────┴──▶ price IDs chosen server-side
   │        beforeCheckout event
   ▼
Stripe Checkout Session (Stripe computes totals)
   │
   ▼
checkout.session.completed webhook ──▶ cart cleared, orderCompleted event

Catalog path (read-only, no Stripe calls):
Stripe Dashboard ──sync (official plugin)──▶ Craft elements ──▶ Twig templates
```

No page render calls the Stripe API, because the catalog is local Craft data. The server contacts Stripe once per purchase, at the checkout click.

## Roadmap

- `0.1` Session cart, hosted Checkout, access-code tiers
- `0.2` Embedded Checkout mode, JSON responses for progressive enhancement
- `0.3` User-group tiers (Craft Pro), optional order entries from `orderCompleted`
- `1.0` API freeze, test coverage across supported Craft and plugin versions

## Contributing

Issues and PRs welcome. If a feature can be built in a site module through the published events, it will not be added here. Bug reports with reproduction steps are the most useful contribution.

## License

[MIT](LICENSE)
