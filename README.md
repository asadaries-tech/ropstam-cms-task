# Senior CMS Developer – Technical Task (Ropstam Solutions)

This repository contains both parts of the task:

| Part | Folder | Status |
| --- | --- | --- |
| WordPress: Dynamic Custom Product Add-on Block | [`wordpress/`](wordpress/) | Complete |
| Shopify: Custom Bundle Builder Section | [`shopify/`](shopify/) | Complete |

**Demo links**

- WordPress: https://digitnity.com/services/
- Shopify: https://ropstam-zpotsimr.myshopify.com/ (storefront password: `bayqua`)

---

## WordPress – Service Add-on Calculator

A lightweight plugin (no build step, no Composer/npm dependencies at runtime) that:

1. Registers a **Services** custom post type (`sacalc_service`) with the `base_price` and `delivery_time_days` meta fields.
2. Provides a dynamic **"Latest Services" block** (`sacalc/latest-services`) and a `[sacalc_latest_services]` shortcode. Both show the **3 most recently updated** services.
3. Adds a **vanilla JS calculator** to each card. Visitors toggle optional add-ons such as *Priority Turnaround +$50* and the estimated price updates live.

### Requirement coverage

| Brief | Implementation |
| --- | --- |
| a. CPT + `base_price`, `delivery_time_days` | `includes/class-sacalc-post-type.php`: `register_post_type()` and `register_post_meta()` with `type`, `sanitize_callback`, `auth_callback` and `show_in_rest`. Edited through a meta box (`includes/class-sacalc-meta-box.php`) that works in both the block and classic editors. |
| b. Block fetching the 3 most recently updated services | `blocks/latest-services/block.json` (apiVersion 3, dynamic) and `render.php`, which use `SACALC_Renderer::get_services()`: `WP_Query` with `orderby => modified`, `posts_per_page => 3`, `no_found_rows => true`. |
| c. Client-side calculator (vanilla JS) | `assets/js/view.js` has no dependencies and one delegated listener per block. It works in integer cents, so there are no float errors. |
| d. Prepared queries / `WP_Query`, escaping, `wp_enqueue_script` with deps | There is no raw SQL anywhere. Every output goes through `esc_html` / `esc_attr` / `esc_url`. Every asset is registered with `wp_register_script` / `wp_register_style` with its dependency array listed, and loaded only through `wp_enqueue_script` / `wp_enqueue_style` (or block.json handles). PHPCS with the `WordPress` ruleset passes with 0 errors and 0 warnings. |

### Folder structure

```
wordpress/
├── blueprint.json                      # One-click WordPress Playground demo
└── service-addon-calculator/
    ├── service-addon-calculator.php    # Plugin header, bootstrap, activation hooks
    ├── includes/
    │   ├── class-sacalc-post-type.php  # CPT, meta registration, admin columns
    │   ├── class-sacalc-meta-box.php   # "Service details" meta box (nonce + caps)
    │   ├── class-sacalc-addons.php     # Filterable, validated add-on list
    │   ├── class-sacalc-assets.php     # Script/style registration + conditional enqueue
    │   ├── class-sacalc-renderer.php   # WP_Query + escaped markup (shared by block & shortcode)
    │   └── class-sacalc-block.php      # register_block_type() + shortcode
    ├── blocks/latest-services/
    │   ├── block.json
    │   └── render.php
    ├── assets/
    │   ├── js/view.js                  # Front-end calculator
    │   ├── js/editor.js                # Block registration (ServerSideRender preview)
    │   └── css/style.css
    ├── phpcs.xml.dist
    └── readme.txt
```

### Setup

**Requirements:** WordPress 6.3+ and PHP 7.4+.

**Option A: upload the zip**

1. Zip the `wordpress/service-addon-calculator` folder, or download it from Releases.
2. In WP Admin, go to **Plugins → Add New → Upload Plugin**, choose the zip, then **Activate**.

**Option B: clone into a local site**

```bash
cd wp-content/plugins
git clone https://github.com/asadaries-tech/ropstam-cms-task.git ropstam-task
ln -s ropstam-task/wordpress/service-addon-calculator service-addon-calculator
wp plugin activate service-addon-calculator
```

**Add content**

1. Go to **Services → Add New** and fill in the *Service details* box (Base price, Delivery time).
2. Edit any page, insert the **Latest Services** block (Widgets category) and publish. In classic themes or page builders, use the `[sacalc_latest_services]` shortcode.

Sample data via WP-CLI:

```bash
wp post create --post_type=sacalc_service --post_status=publish --post_title='Logo Design' \
  --post_excerpt='Custom logo with three initial concepts.' \
  --meta_input='{"base_price":149,"delivery_time_days":5}'
wp post create --post_type=page --post_status=publish --post_title='Our Services' \
  --post_content='<!-- wp:sacalc/latest-services {"align":"wide"} /-->'
```

### Live demo (WordPress Playground)

`wordpress/blueprint.json` installs the plugin straight from this repo, creates 4 sample services (one without a price) and opens the demo page. After pushing, replace `asadaries-tech/ropstam-cms-task` in that file and use:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/asadaries-tech/ropstam-cms-task/main/wordpress/blueprint.json
```

Each visitor gets a fresh, disposable site. For a persistent demo, upload the zip to a free InstaWP or TasteWP site.

### Developer hooks

```php
// Change the add-ons (validated: id, label, numeric price; duplicates and bad rows are dropped).
add_filter( 'sacalc_service_addons', function ( $addons ) {
	$addons[] = array( 'id' => 'rush-support', 'label' => 'Rush Support', 'price' => 75 );
	return $addons;
} );

// Change the currency symbol.
add_filter( 'sacalc_currency_symbol', fn() => '£' );

// Enable an archive (only if no page uses the slug).
add_filter( 'sacalc_post_type_args', function ( $args ) {
	$args['has_archive'] = 'all-services';
	return $args;
} );
```

### Implementation notes

**Platform standards**

- Each hook is attached in its class's `init()`. Post-type-specific hooks (`add_meta_boxes_{post_type}`, `save_post_{post_type}`, `manage_{post_type}_posts_columns`) keep the code off unrelated screens.
- Everything uses the `sacalc` prefix (functions, classes, post type, hooks, handles, CSS classes). `sac` was too short for the WPCS PrefixAllGlobals sniff.
- The block is registered from `block.json` (apiVersion 3) and is dynamic (`save` returns `null`), so the list stays current without re-saving pages.
- Activation flushes rewrite rules once (after registering the CPT), and so does the first load after an update (a version check, because zip updates don't re-run the activation hook). Deactivation cleans them up.
- Single services live at `/service/{slug}/`. There is deliberately no `/services/` archive, so the plugin can never take over a page called "Services" (see the edge cases below).

**Security**

- Meta writes are sanitized in `register_post_meta()`, so the meta box, REST API and `update_post_meta()` share one rule set. The REST `auth_callback` requires `edit_post`.
- The meta box save checks the nonce, autosave, revision and `current_user_can( 'edit_post' )`, and uses `wp_unslash()` and `sanitize_text_field()` on all input.
- Output is escaped late with `esc_html()`, `esc_attr()` and `esc_url()`. Prices reach JS only as escaped integer data attributes, not inline JSON.

**Performance**

- One `WP_Query` with `no_found_rows` and no term cache. Post meta is primed in a single query, so the cards add no N+1 queries.
- `view.js` (about 4 KB unminified, zero dependencies, `defer`, in footer) and the stylesheet load **only on pages that render the block or shortcode**.
- One delegated click listener per block. Each toggle changes only the clicked button's `aria-pressed` and the total's text, and only if the text changed.
- There is no build step: the editor script uses core's `wp.*` packages declared as dependencies.

**Edge cases**

- **No price set:** the card shows "On request" and "Contact us for a quote", with no calculator. A missing price is never treated as $0.
- **No delivery time:** shows "To be confirmed".
- **Invalid input:** negative prices are clamped to 0, days to at least 1, and non-numeric meta box input clears the field. The REST API rejects wrong types through the schema.
- **No services:** a friendly empty-state message.
- **URL collisions:** no post type archive, so a page with the slug `services` keeps working, including in Elementor's editor preview. (Found on a live Hostinger + Elementor site: the archive had taken over the page's URL.) Sites that want an archive can enable it with the `sacalc_post_type_args` filter.
- **JavaScript disabled:** the add-on toggles stay hidden (they would do nothing) and the base price shows as the total.
- **Several blocks on one page:** IDs come from `wp_unique_id()` and each block initializes independently.
- **Locale formatting:** the JS uses the site's decimal and thousands separators, so live totals match PHP's `number_format_i18n()`.

**Accessibility**

- Semantic `article` / `h3` / `dl` / `fieldset` + `legend` / `output` markup. Lists use `role="list"` so Safari keeps list semantics after `list-style: none`.
- Toggles are native `<button type="button">` elements with `aria-pressed`, so Tab, Enter and Space work with no extra JS.
- The total is an `<output aria-live="polite">`, so screen readers announce the new price.
- There is a visible `:focus-visible` ring, 44px minimum targets, a ✓ shown on pressed toggles (not colour alone), and support for `prefers-reduced-motion` and `forced-colors`.

### Testing done

- `phpcs` with the WordPress ruleset (WPCS 3) passes with 0 errors and 0 warnings. Run it with `phpcs` inside the plugin folder.
- Tested on WordPress 6.8 / PHP 8.3 with the Twenty Twenty-Five theme:
  - ordering by last-modified date
  - escaping of a `<script>` title and an `<img onerror>` excerpt
  - REST meta auth (401 when logged out) and type validation
  - meta box nonce rejection
  - the calculator with mouse and keyboard (Tab / Space / Enter)
  - two independent cards
  - a 375px mobile layout with no overflow
  - the no-JS fallback
  - the shortcode
  - the editor preview with no console errors

### Assumptions & limitations

- The brief doesn't say where add-ons come from, so they are global (shared by all services) and set by the `sacalc_service_addons` filter. Per-service add-ons would need a repeater meta field.
- The meta keys are named exactly `base_price` and `delivery_time_days` as the brief specifies, instead of prefixing them.
- The calculator gives an **estimate** for display only. There is no checkout or order storage.
- Uninstalling keeps the Services content on purpose, so a site owner's data is never deleted by removing the plugin.
- The currency symbol is always placed before the amount.

---

## Shopify – Bundle Builder Section

An Online Store 2.0 section that works in any 2.0 theme (Dawn, Horizon, etc.) and has no dependencies. Customers choose **one product from each of up to 3 groups**, see the bundle price update live, and add all items to the cart in **one `/cart/add.js` request**.

### Requirement coverage

| Brief | Implementation |
| --- | --- |
| a. `bundle-builder.liquid` with schema letting merchants pick up to 3 collections or product lists | `sections/bundle-builder.liquid`: `"max_blocks": 3`. Each **Product group** block has a `collection` setting and a `product_list` setting (the product list wins if both are set), plus a group title and how many products to show. |
| b. Customers pick 1 item per group to build a 3-pack | Each group is a `<fieldset>` of native radio buttons (`snippets/bundle-builder-card.liquid`). Products with variants get a variant `<select>`. |
| c. Live bundle price; add all 3 to cart via Ajax with line item properties linking them | `assets/bundle-builder.js`: the total is recalculated on every change. One `POST /cart/add.js` sends `{ items: [...] }` with `quantity: 1` per item, and every line gets `properties: { Bundle: "<name>", _bundle_id: "<uuid>" }`. The underscore keeps `_bundle_id` hidden from customers at checkout. |
| d. Stock/sold-out validation and loading states without reload; clean Liquid and vanilla JS | Sold-out products and variants are disabled and labelled. A group that is entirely sold out blocks the bundle with a message. The button shows a spinner, `aria-busy` and "Adding…", and double submits are ignored. Shopify errors (e.g. 422 "not enough stock"), network failures and timeouts (15s) all show a message. There are no libraries: a single custom element, about 12 KB unminified. |

### Files

```
shopify/
├── sections/bundle-builder.liquid       # Markup + schema (settings, blocks, presets)
├── snippets/bundle-builder-card.liquid  # One selectable product (radio + variant select)
└── assets/
    ├── bundle-builder.js                # <bundle-builder> custom element
    └── bundle-builder.css               # Scoped, mobile-first styles
```

### Setup

**Requirements:** a Shopify dev store, [Shopify CLI](https://shopify.dev/docs/api/shopify-cli) (Node.js 22.12+) and Git.

```bash
# 1. Install the CLI
npm install -g @shopify/cli@latest

# 2. Pull your dev store's current theme into a folder
mkdir my-theme && cd my-theme
shopify theme pull --store YOUR-STORE.myshopify.com

# 3. Copy the 4 files in (keep the folder structure)
cp -r ../ropstam-task/shopify/sections ../ropstam-task/shopify/snippets ../ropstam-task/shopify/assets .

# 4. Preview locally (hot reload)
shopify theme dev --store YOUR-STORE.myshopify.com

# 5. Upload as a new theme, then publish it from Online Store > Themes
shopify theme push --unpublished --store YOUR-STORE.myshopify.com
```

**Add the section:** go to **Online Store → Themes → Customize** and open a template (e.g. the home page). Click **Add section → Bundle builder**, then in each of the 3 **Product group** blocks choose a collection or pick products. Save.

**Recommended test data:** 3 collections, at least one product with variants, one product with inventory set to 0 (to show "Sold out"), and one sold-out variant.

### Implementation notes

**Platform standards**

- Uses the section schema features you'd expect: `max_blocks`, typed settings (`collection`, `product_list`, `color_scheme`, `range`), `presets` so it appears under **Add section**, and `disabled_on` header/footer groups.
- `{{ block.shopify_attributes }}` is on each group, so clicking a group in the theme editor selects its block.
- Uses the standard Liquid filters: `money`, `image_url` + `image_tag` (responsive `widths`/`sizes`, lazy loading), `asset_url`, `stylesheet_tag`, `escape`, `placeholder_svg_tag`, and `routes.cart_add_url` / `routes.cart_url` (so it works on translated store paths).
- Prices are formatted in JS with the shop's own `shop.money_format`, so live totals match Liquid's `money` output.
- Shopify **Theme Check** passes with 0 offenses.

**Performance**

- The JS is `defer`red, with no dependencies and one delegated `change` listener and one `submit` listener per section.
- DOM writes only touch text that changed, plus a couple of attributes. There's no re-rendering of cards.
- It's a custom element, so it sets itself up (and cleans up) when the theme editor re-renders the section, without needing `shopify:section:load` handlers.

**Edge cases**

- Sold-out products are disabled with a "Sold out" badge. Sold-out variants are disabled options. If a whole group is sold out, the customer is told why the bundle can't be completed.
- Customers can deselect a choice by clicking, tapping or pressing Space on the selected item again (a hint tells them). Native radios can't be unchecked, so this is added in JS.
- Submitting before all groups are chosen shows a message and moves focus to the first group that's missing a choice.
- 422 responses show Shopify's own message (e.g. "You can only add 2 of this item to the cart"). Network errors and timeouts show a friendly fallback, and the button always recovers.
- Groups with no collection or products are skipped on the storefront and flagged in the theme editor.
- Product titles are escaped, the layout is mobile first (2-column cards, with a sticky summary on desktop), and there's no horizontal scroll at 375px.

**Accessibility**

- Native radios inside a `<fieldset>`/`<legend>` give arrow-key navigation and announced checked state for free. Variant `<select>`s have labels.
- The total is an `<output aria-live="polite">`, the status line is `aria-live`, errors use `role="alert"` and success uses `role="status"`.
- The button uses `aria-disabled` rather than `disabled`, so it stays focusable and explains what's missing. It has `aria-busy` while adding.
- Visible focus rings, a non-colour ✓ for the selected card (aria-hidden, so it isn't read as part of the name), and support for `prefers-reduced-motion` and `forced-colors`.

**After adding to cart**

The request also asks the Section Rendering API for `cart-icon-bubble`, so the cart count updates in Dawn-style themes. It also fires Dawn's `cartUpdate` pub/sub event when that exists, and always dispatches a `bundle-builder:added` DOM event (with `bundleId` and `items`) for other themes to hook into. A "View cart" link appears after a successful add.

### Testing done

- **Theme Check** (`@shopify/theme-check-node`): 0 offenses.
- The section was rendered with mock products through LiquidJS and tested in Chrome with a mocked `/cart/add.js`:
  - live totals, including variant price changes
  - sold-out products, variants and whole groups
  - keyboard selection (Space, arrow keys)
  - validation with focus moved to the missing group
  - one request carrying all 3 items with a shared `_bundle_id`
  - loading state and double-click protection
  - 422 and network-failure messages
  - cart bubble refresh
  - no horizontal scroll at 375px

### Assumptions & limitations

- **Bundle price = sum of the selected variants' prices.** No discount is applied. A real bundle discount needs an automatic discount or a Shopify Function, which is outside this section's scope.
- The items stay as 3 separate cart lines linked by `_bundle_id`. Removing one line doesn't remove the others.
- Quantity is 1 per item. Collections show their first 2–12 products (merchant setting), with no pagination.
- UI text is English. Merchant-facing text (heading, description, button, bundle name) is editable in the theme editor. The remaining short labels are in the Liquid/JS rather than locale files, so the section can be dropped into any theme without editing `locales/*.json`.
