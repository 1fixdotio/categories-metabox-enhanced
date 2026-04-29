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
3. `runPHP` reads the seed files out of the cloned plugin directory: it `copy()`s `mu-demo-notice.php` into `wp-content/mu-plugins/` and `require`s `seed-data.php` to set the radio option, create the categories, and insert the demo posts.
4. The browser opens on `/wp-admin/post-new.php`; the mu-plugin notice explains where to look.

The seed files travel inside the cloned plugin source, so they always match the plugin code being installed — no separate `raw.githubusercontent.com` fetches, no chance of seed and code drifting apart between branches.

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

`pluginData.ref` is the only ref the Blueprint pins. To test changes on a branch:

1. Edit `playground.blueprint.json` and change `"ref": "master"` to `"ref": "<your-branch>"`.
2. Push the branch.
3. Open:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/1fixdotio/categories-metabox-enhanced/<your-branch>/blueprint/playground.blueprint.json
```

Swap `ref` back to `master` before merging — otherwise the live demo will install whichever branch you left in there.

## Branching model and demo availability

PRs merge to `develop`; releases merge `develop` into `master`. The Blueprint manifest (`playground.blueprint.json`) and the "Try it live" link in `README.txt` both target `master`, so the public demo URL only updates when a release lands. Between merge-to-develop and the next release-to-master, the demo continues to reflect the previously released state — by design, so users always land on the version that's actually published, not in-flight work.
