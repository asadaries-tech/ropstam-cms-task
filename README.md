# Senior CMS Developer – Technical Task (Ropstam Solutions)

This repository contains both parts of the task:

| Part | Folder | Status |
| --- | --- | --- |
| WordPress: Dynamic Custom Product Add-on Block | [`wordpress/`](wordpress/) | Complete |
| Shopify: Custom Bundle Builder Section | [`shopify/`](shopify/) | In progress |

**Demo links**

- WordPress: `<add your Playground / InstaWP link here>`
- Shopify: `<add your dev store link + storefront password here>`

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
git clone https://github.com/YOUR-GITHUB-USER/YOUR-REPO.git ropstam-task
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

`wordpress/blueprint.json` installs the plugin straight from this repo, creates 4 sample services (one without a price) and opens the demo page. After pushing, replace `YOUR-GITHUB-USER/YOUR-REPO` in that file and use:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/YOUR-GITHUB-USER/YOUR-REPO/main/wordpress/blueprint.json
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
```

### Implementation notes

**Platform standards**

- Each hook is attached in its class's `init()`. Post-type-specific hooks (`add_meta_boxes_{post_type}`, `save_post_{post_type}`, `manage_{post_type}_posts_columns`) keep the code off unrelated screens.
- Everything uses the `sacalc` prefix (functions, classes, post type, hooks, handles, CSS classes). `sac` was too short for the WPCS PrefixAllGlobals sniff.
- The block is registered from `block.json` (apiVersion 3) and is dynamic (`save` returns `null`), so the list stays current without re-saving pages.
- Activation flushes rewrite rules once (after registering the CPT). Deactivation cleans them up.

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

See [`shopify/`](shopify/) (to be added).
