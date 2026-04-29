# Playground Demo

A one-click, browser-only demo of Categories Metabox Enhanced powered by [WordPress Playground](https://wordpress.github.io/wordpress-playground/).

## Try it

<!-- markdownlint-disable-next-line MD034 -->
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/1fixdotio/categories-metabox-enhanced/master/blueprint/playground.blueprint.json

Clicking the link boots WordPress in your browser with the plugin pre-installed, the `category` taxonomy pre-configured as a radio single-term UI, and a couple of seeded posts ready to play with.

## What the demo shows

- The **Block Editor sidebar** on `post-new.php` (the landing page) and any post-edit screen renders the Categories panel as a radio tree instead of the default checkbox tree. You can pick one — and only one.
- The **Classic Editor metabox** (visible if you install the Classic Editor plugin) renders radio buttons in place of checkboxes.
- **Quick Edit** and **Bulk Edit** on `edit.php` still show WordPress's default checkbox UI — the plugin doesn't override that markup — but the server-side `set_object_terms` listener coerces any multi-term commit to the last selected term, so the single-term invariant holds.
- The **Settings page** (`Settings → Categories Metabox Enhanced`) lists every hierarchical taxonomy with its own configuration block. Switch Category between `radio`, `select`, and `checkbox` to see each variant.

## Files

- `playground.blueprint.json` — the Blueprint; the Playground URL above fetches this.
- `seed/seed-data.php` — runs inside Playground's PHP. Sets the radio option, creates four extra categories, and inserts two seed posts.
- `seed/mu-demo-notice.php` — mu-plugin that prints a context-aware admin notice on post-edit, posts-list, and the settings page. Loaded only inside the demo.

## How it works

Boot sequence when someone opens the Playground link:

1. Playground boots PHP + WordPress in the browser (WASM).
2. `installPlugin` clones the plugin source from `master` (via the `git:directory` resource) and activates it. The Block Editor sidebar bundle is read from the committed `admin/js/block-editor/build/` artifacts — no build step runs inside Playground.
3. `writeFile` drops `mu-demo-notice.php` into `wp-content/mu-plugins/`.
4. `writeFile` drops `seed-data.php` into `/tmp/`.
5. `runPHP` loads WordPress and runs the seed script.
6. The browser opens on `/wp-admin/post-new.php`; the mu-plugin notice explains where to look.

Because the seed script and the mu-plugin notice are fetched from the repo's raw URLs, the Blueprint only fully reflects the live source after the seed files are pushed to `master`. The plugin itself is also fetched from `master` via `git:directory`.

## Why `git:directory` and not a release zip

The MSE sibling plugin (which this scaffolding is patterned after) installs from a pinned GitHub release zip built by `10up/action-wordpress-plugin-deploy`. CME doesn't ship that workflow yet, and the wordpress.org listing is pinned at 0.7.1 — missing every 0.8/0.9 feature (Block Editor sidebar panel, force_selection, server-side enforcement). Installing from `git:directory` against `master` is the only way the demo reflects what the readme actually describes.

If a release pipeline is added later, swap the `pluginData` block to:

```json
"pluginData": {
  "resource": "url",
  "url": "https://github.com/1fixdotio/categories-metabox-enhanced/releases/download/<TAG>/categories-metabox-enhanced.zip"
},
```

…and the demo gains version-pinning. Until then, every push to `master` updates the demo.

## Testing on a branch

The Blueprint hardcodes `master` in three places:

1. `pluginData.ref` — the git ref Playground clones the plugin from.
2. The `raw.githubusercontent.com/.../master/blueprint/seed/mu-demo-notice.php` URL.
3. The `raw.githubusercontent.com/.../master/blueprint/seed/seed-data.php` URL.

To test changes on a branch, swap all three `master` occurrences in `playground.blueprint.json` to your branch name, push, and open:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/1fixdotio/categories-metabox-enhanced/<branch>/blueprint/playground.blueprint.json
```

Swap them back to `master` before merging — otherwise the live demo will install whichever branch you left in there.
