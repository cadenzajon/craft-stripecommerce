# Release Notes for Stripe Cart

## 0.1.0 - 2026-07-23

Initial release.

### Added
- Session cart with `cart/add`, `cart/update`, `cart/remove`, and `cart/clear` actions.
- Stripe hosted Checkout handoff, built on the free [`craftcms/stripe`](https://plugins.craftcms.com/stripe) plugin.
- `craft stripe-cart/sync` command to pull the Stripe catalog into Craft.
- `craft stripe-cart/webhooks/subscribe` for one-time webhook setup, plus `status` and `unsubscribe`.
- Optional pricing tiers (for example retail and wholesale) with access-code and user-group activation.
- Pass-through of Stripe shipping rates via the `checkout.shippingOptions` setting.
- `craft.stripeCart` Twig variable, and `beforeCheckout`, `orderCompleted`, and `resolveTier` events.
