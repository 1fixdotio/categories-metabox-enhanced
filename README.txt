=== Categories Metabox Enhanced ===

Contributors: 1fixdotio
Donate link: http://1fix.io/
Tags: category, metabox, taxonomy
Requires at least: 3.5
Tested up to: 6.7
Stable tag: 0.9.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Replace the checkboxes with radio buttons or a select drop-down in the built-in Categories metabox and the Block Editor sidebar panel.

== Description ==

Thanks to [Taxonomy_Single_Term](https://github.com/WebDevStudios/Taxonomy_Single_Term), a library created by [WebDevStudios](http://webdevstudios.com/), it made my work much easier on creating this plugin.

With Categories Metabox Enhanced, you can:

* Change the built-in Categories metabox/panel to a single term UI, which means replacing the checkboxes with radio buttons or a select drop-down.
* Apply the single term UI to other hierarchical taxonomies in the plugin's Settings page.
* Customized the single term UI by setting these options:
 * Priority and position of the metabox placement.
 * Title of the metabox.
 * If child-terms should be indenting.
 * If adding of new terms from the metabox is enable.
 * If a term selection is required (Force selection). When on, the classic metabox hides the "None" option, the Block Editor sidebar drops the "— Select —" entry once a term is chosen, and an empty save is substituted with a default term server-side so REST and programmatic callers can't bypass it.

The substituted default term resolves in this order: the `default_<taxonomy>` option (e.g. `default_category`) → the `default_term_<taxonomy>` option populated by `register_taxonomy()` `default_term` arg (WP 5.5+) → the first term ordered by name. Override per taxonomy with the `of_cme_force_selection_default_term` filter.

== Installation ==

1. Upload the `category-metabox-enhanced` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. The checkboxes in Categories metabox are replaced with radio buttons
2. The checkboxes in Categories metabox are replaced with a select drop-down
3. A settings page for this plugin

== Changelog ==

= 0.9.0 =
* Add a "Force selection" per-taxonomy setting (defaults on) replacing the previously hardcoded classic-editor behavior. When on, the Block Editor sidebar suppresses "— Select —" once a term is chosen, and the server-side `pre_set_object_terms` filter substitutes a default term for empty submissions on radio/select taxonomies — closing the bypass that REST and programmatic callers had on the single-term invariant.
* The substituted term resolves in this order: `default_<taxonomy>` option → `default_term` registered with the taxonomy (WP 5.5+) → first term by name asc. Filterable via `of_cme_force_selection_default_term`. Mirrors the classic library's `process_default()` pattern.

= 0.8.0 =
* Replace the legacy Block Editor integration with a native sidebar panel built on `@wordpress/components` (`TreeSelect` / radio tree) and `PluginDocumentSettingPanel`.
* Add server-side single-term enforcement on `pre_set_object_terms` so REST and programmatic saves can't bypass the radio/select invariant.
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
