# Stripe Commerce for Craft CMS

**A lean, tier-aware shopping cart and Stripe Checkout layer for Craft CMS.** Built on top of the official, free [`craftcms/stripe`](https://github.com/craftcms/stripe) plugin, it adds the one thing that plugin deliberately leaves out — a real multi-product cart — plus configurable pricing tiers (e.g. retail vs. wholesale). No Craft Commerce license, no JavaScript framework, no build step.

> **Status: pre-release.** This README is the product spec. The API described here is the contract the implementation will be held to; expect it to firm up at `1.0.0`.

---

## Why this exists

Craft CMS is a superb content platform, and Stripe is a superb payments platform. Connecting them for a small store today means choosing between:

| Option | Problem |
| --- | --- |
| Craft Commerce | $1,199 license + renewals — overkill for a catalog store with simple shipping |
| Official `craftcms/stripe` plugin | Free and excellent, but checkout is per-product ("buy now") — **no cart** |
| Paid cart plugins | Proprietary licenses, annual renewals |
| Custom code | Everyone rebuilds the same 150 lines of session-cart module, badly |

This project is that missing 150 lines, done once, done well, and MIT-licensed: a cart, a checkout handoff, and a pricing-tier gate — nothing more.

## Design principles

1. **Stripe owns the money; Craft owns the content.** Products and prices are authored in Stripe and synced into Craft as elements by the official plugin. Rich content (images, descriptions, categories, custom fields) lives on those elements in Craft. This plugin never stores an amount, computes a total, or touches a card number — Stripe Checkout does all of that.
2. **The client is never trusted with prices.** The browser submits product IDs and quantities only. The server resolves each product to a Stripe price ID based on the visitor's pricing tier. There is no client-side path to a price the server didn't choose.
3. **Zero JavaScript required.** Every cart interaction is a plain HTML form POST rendered by Twig. Sites can progressively enhance with `fetch()` if they want no-reload interactions; nothing here depends on it.
4. **Minimal dependencies.** Exactly two: Craft CMS 5 and `craftcms/stripe` (which brings `stripe/stripe-php`). No frontend packages, no polyfills, no bundler.
5. **Small surface, strong events.** The plugin ships a deliberately small feature set and exposes events at every decision point, so site-specific behavior (shipping logic, tier rules, analytics) lives in your site module, not in forks of this one.

## What it does (scope)

### Session cart
- Server-side cart stored in the PHP session: `[{productId, qty}, …]`.
- Controller actions: `cart/add`, `cart/update`, `cart/remove`, `cart/clear` — all accept ordinary form POSTs with CSRF protection and redirect back (or return JSON when requested with an `Accept: application/json` header).
- Twig variable `craft.stripeCart` exposing `items` (hydrated with the synced product elements), `count`, and `isEmpty` for rendering cart pages, badges, and drawers.
- Configurable cart lifetime rides on Craft's session/remember-me configuration — no separate storage.

### Pricing tiers
- Tiers are defined in `config/stripe-commerce.php`. Each tier maps to a Stripe **price metadata** selector (default: `tier: retail` / `tier: wholesale` on each Price object).
- Every product in Stripe carries one Price per tier. The plugin resolves the active tier's price ID at cart-render and checkout time — server-side, always.
- The default tier applies to all visitors. Additional tiers are unlocked per-session by:
  - **Access code** — a controller action validates a configured passcode and flags the session (works on Craft Solo, which has no front-end users), and/or
  - **User group** — on Craft Pro, membership in a configured user group activates a tier automatically.
- A `resolveTier` event lets site modules implement any other rule (IP allowlist, signed URL, time window).

### Checkout
- `checkout` action converts the session cart into a Stripe Checkout Session with one line item per cart row, using the tier-resolved price IDs, then redirects (hosted mode) or returns the client secret (embedded mode).
- Success/cancel URLs, `ui_mode`, shipping-address collection, and allowed shipping rates are config options passed through to Stripe.
- A `beforeCheckout` event exposes the full session params for last-mile customization — the same extension point pattern as the official plugin.
- On the `checkout.session.completed` webhook (received by the official plugin), the cart is cleared and an `orderCompleted` event fires with the session payload, for receipts, fulfillment hooks, or syncing order records into Craft entries.

### Out of scope (non-goals)

These are features of Stripe or of full commerce platforms — reimplementing them here would add surface without adding value:

- **Order management UI** — the Stripe Dashboard is the order admin (refunds, receipts, exports, tax reports).
- **Promotions/coupons** — enable Stripe promotion codes on the Checkout Session (one config flag).
- **Tax** — Stripe Tax, configured in Stripe.
- **Shipping rate engine** — flat/tiered rates via Stripe shipping rate objects; anything fancier belongs in a site module via `beforeCheckout`.
- **Product/price CRUD** — author in the Stripe Dashboard; the official plugin syncs.
- **Subscriptions** — the official plugin already handles these end to end.
- **Payment processing of any kind** — Stripe Checkout only. No card fields ever render on your server.

## Requirements

- Craft CMS **5.6+** (Solo edition is fully supported; Pro unlocks user-group tiers)
- [`craftcms/stripe`](https://plugins.craftcms.com/stripe) **1.3+**
- PHP **8.2+**
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

That's the entire integration: two forms, one variable. The visitor is redirected to Stripe Checkout with the correct per-tier prices, pays, and lands back on your thank-you page.

## Architecture

```
Browser (plain HTML forms, no JS required)
   │  productId + qty only — never prices
   ▼
CartController ── CartService (PHP session)
   │                    │
   │                    ▼
   │              TierResolver ──▶ resolveTier event
   ▼                    │
CheckoutController ─────┴──▶ price IDs chosen server-side
   │        beforeCheckout event
   ▼
Stripe Checkout Session (Stripe computes all totals)
   │
   ▼
checkout.session.completed webhook ──▶ cart cleared, orderCompleted event

Catalog path (read-only, zero Stripe latency):
Stripe Dashboard ──sync (official plugin)──▶ Craft elements ──▶ Twig templates
```

Key property: **no page render ever calls the Stripe API.** The catalog is local Craft data; Stripe is contacted exactly once per purchase, at the checkout click.

## Roadmap

- `0.1` — Session cart + hosted Checkout + access-code tiers (the MVP described above)
- `0.2` — Embedded Checkout mode, JSON responses for progressive enhancement
- `0.3` — User-group tiers (Craft Pro), `orderCompleted` → optional order entries
- `1.0` — API freeze, test coverage across supported Craft/Stripe plugin versions

## Contributing

Issues and PRs welcome. The bar for new features is deliberately high — anything achievable in a site module via the published events belongs there, not here. Bug reports with failing reproduction steps are the most valuable contribution.

## License

[MIT](LICENSE)
