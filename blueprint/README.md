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
2. `installPlugin` downloads the plugin source from GitHub's auto-generated source-archive zip (`/archive/<branch>.zip`) and activates it. The Block Editor sidebar bundle is read from the committed `admin/js/block-editor/build/` artifacts inside the archive — no build step runs inside Playground.
3. `runPHP` finds the installed plugin directory via `glob()` (GitHub archives extract to `categories-metabox-enhanced-<branch>/`, not the bare `categories-metabox-enhanced/` a wp.org zip would use), `copy()`s `mu-demo-notice.php` into `wp-content/mu-plugins/`, and `require`s `seed-data.php` to set the radio option, create the categories, and insert the demo posts.
4. The browser opens on `/wp-admin/post-new.php`; the mu-plugin notice explains where to look.

The seed files travel inside the same archive zip as the plugin code, so they always match the plugin code being installed — no separate `raw.githubusercontent.com` fetches, no chance of seed and code drifting apart between branches.

## Why a source-archive zip and not a release zip or wordpress.org slug

The MSE sibling plugin (which this scaffolding is patterned after) installs from a pinned GitHub release zip built by `10up/action-wordpress-plugin-deploy`. CME doesn't ship that workflow yet, and the wordpress.org listing is pinned at 0.7.1 — missing every 0.8/0.9 feature (Block Editor sidebar panel, force_selection, server-side enforcement). The GitHub source-archive zip is the only resource that:

- Reflects what the readme actually describes (current `master`, not stale `0.7.1`).
- Requires no release pipeline or deploy workflow.
- Is observable end-to-end (a real zip with a documented `installPlugin` path, unlike the `git:directory` resource we tried first that failed with opaque "PHP.run() failed with exit code 255" errors and no logs).

If a release pipeline is added later, swap the `pluginData` block to:

```json
"pluginData": {
  "resource": "url",
  "url": "https://github.com/1fixdotio/categories-metabox-enhanced/releases/download/<TAG>/categories-metabox-enhanced.zip"
},
```

…and the demo gains version-pinning, plus the install directory becomes the bare `categories-metabox-enhanced/` (no `<branch>` suffix). Until then, every push to `master` updates the demo.

## Testing on a branch

The branch name appears in the `pluginData.url` (e.g. `archive/master.zip`). To test changes on a branch:

1. Edit `playground.blueprint.json` and change the `archive/master.zip` segment of `pluginData.url` to `archive/<your-branch>.zip`.
2. Push the branch.
3. Open:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/1fixdotio/categories-metabox-enhanced/<your-branch>/blueprint/playground.blueprint.json
```

Revert the URL to `archive/master.zip` before merging — otherwise the live demo will install whichever branch you left in there. The `glob()` discovery in `runPHP` works regardless of which branch the archive was generated from, so no other edits are needed for branch testing.

## Branching model and demo availability

PRs merge to `develop`; releases merge `develop` into `master`. The Blueprint manifest (`playground.blueprint.json`) and the "Try it live" link in `README.txt` both target `master`, so the public demo URL only updates when a release lands. Between merge-to-develop and the next release-to-master, the demo continues to reflect the previously released state — by design, so users always land on the version that's actually published, not in-flight work.
