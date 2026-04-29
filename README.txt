=== Categories Metabox Enhanced ===

Contributors: 1fixdotio
Donate link: http://1fix.io/
Tags: category, metabox, taxonomy
Requires at least: 5.5
Tested up to: 6.7
Stable tag: 0.9.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Replace the checkboxes with radio buttons or a select drop-down in the built-in Categories metabox and the Block Editor sidebar panel.

== Description ==

Thanks to [Taxonomy_Single_Term](https://github.com/WebDevStudios/Taxonomy_Single_Term), a library created by [WebDevStudios](http://webdevstudios.com/), it made my work much easier on creating this plugin.

With Categories Metabox Enhanced, you can:

* Change the built-in Categories metabox/panel to a single term UI, which means replacing the checkboxes with radio buttons or a select drop-down.
* Apply the single term UI to other hierarchical taxonomies from the plugin's Settings page.
* Configure each hierarchical taxonomy independently — the same install can keep one taxonomy as checkboxes while another is forced to radio.
* Customize the single term UI by setting these options:
 * Option type (checkbox, radio, or select).
 * Priority and position (context) of the metabox placement. *Classic editor only.*
 * Title of the metabox / Block Editor panel.
 * Whether child-terms should be indented.
 * Whether adding new terms from the metabox is enabled.
 * Whether a term selection is required (Force selection). When on, the classic metabox hides the "None" option, the Block Editor sidebar drops the "— Select —" entry once a term is chosen, and an empty save is substituted with a default term server-side so REST and programmatic callers can't bypass it.

The single-term invariant is enforced everywhere posts can be saved: the Classic Editor metabox, the Block Editor sidebar panel, **Quick Edit**, **Bulk Edit**, the REST API, WP-CLI, and any code that calls `wp_set_object_terms()` directly. The classic-editor and Block Editor surfaces enforce in the UI; everything else is enforced on the server via the `set_object_terms` action, which coerces multi-term submissions to the last term and substitutes the default term on empty submissions when Force selection is on.

The substituted default term resolves in this order: the `default_<taxonomy>` option (e.g. `default_category`) → the `default_term_<taxonomy>` option populated by `register_taxonomy()` `default_term` arg (WP 5.5+) → the first term ordered by name. Override per taxonomy with the `of_cme_force_selection_default_term` filter.

The Block Editor sidebar panel is rendered for taxonomies registered with `show_in_rest = true`. Hierarchical taxonomies that opt out of REST fall back to the Classic Editor metabox path.

== Frequently Asked Questions ==

= Does this work in Quick Edit and Bulk Edit? =

Yes. WordPress's Quick Edit and Bulk Edit forms render their own checkbox UI for hierarchical taxonomies and the plugin doesn't override that markup, so those forms still let you tick more than one box. The single-term invariant is preserved server-side: when the form is submitted, the plugin's `set_object_terms` listener detects the multi-term commit and coerces the post to the last selected term. The same behavior applies to REST API writes and any programmatic `wp_set_object_terms()` call.

= Does it work with the Block Editor (Gutenberg)? =

Yes. As of 0.8.0 the plugin ships a native sidebar panel built on `@wordpress/components` (`TreeSelect` for select mode, a radio tree for radio mode) that replaces the default Categories panel. The classic metabox is suppressed per-post when the Block Editor is in use, so you don't see a duplicate UI when the Classic Editor plugin lets users switch editors per post.

= Does it support custom hierarchical taxonomies? =

Yes. Every taxonomy registered with `hierarchical = true` shows up in the Settings page with its own configuration block. Non-hierarchical taxonomies (tags-style) are out of scope.

= How do I change the default term used when Force selection substitutes one in? =

Set the `default_<taxonomy>` option (e.g. the built-in "Default Post Category" under Settings → Writing for `category`), or the `default_term` argument on `register_taxonomy()` for custom taxonomies. To override per call, use the `of_cme_force_selection_default_term` filter — it receives the resolved term ID and the taxonomy slug.

== Installation ==

1. Upload the `category-metabox-enhanced` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. The checkboxes in Categories metabox are replaced with radio buttons
2. The checkboxes in Categories metabox are replaced with a select drop-down
3. A settings page for this plugin

== Changelog ==

= 0.9.1 =
* Fix server-side single-term enforcement, which was registered against `pre_set_object_terms` — a hook that does not exist in WordPress core — and never executed in 0.9.0. The handler now hooks `set_object_terms` and re-issues `wp_set_object_terms` with a corrected list when the single-term contract is violated, so REST and programmatic callers are now actually enforced. The classic-editor metabox path was unaffected; it relies on UI-level enforcement.

= 0.9.0 =
* Add a "Force selection" per-taxonomy setting (defaults on) replacing the previously hardcoded classic-editor behavior. When on, the Block Editor sidebar suppresses "— Select —" once a term is chosen, and the server-side handler substitutes a default term for empty submissions on radio/select taxonomies — closing the bypass that REST and programmatic callers had on the single-term invariant. (The 0.9.0 release wired this handler against the wrong hook name; see 0.9.1.)
* The substituted term resolves in this order: `default_<taxonomy>` option → `default_term` registered with the taxonomy (WP 5.5+) → first term by name asc. Filterable via `of_cme_force_selection_default_term`. Mirrors the classic library's `process_default()` pattern.

= 0.8.0 =
* Replace the legacy Block Editor integration with a native sidebar panel built on `@wordpress/components` (`TreeSelect` / radio tree) and `PluginDocumentSettingPanel`.
* Add server-side single-term enforcement so REST and programmatic saves can't bypass the radio/select invariant. (Released against the wrong hook name; superseded by 0.9.1.)
* The classic-editor metabox path is unchanged; it now skips post types that use the Block Editor to avoid duplicate UI.

= 0.7.1 =
* Update the Taxonomy_Single_Term library and some cosmetic fixes.

= 0.7.0 =
* Support the single term UI in Gutenberg sidebar Categories/Taxonomies panels.

= 0.6.1 =
* Remove git submodule from this plugin.

= 0.6.0 =
* Indent the child-terms in select options.
* Remove unused CSS and JS files.

= 0.5.0 =
* The first version.
