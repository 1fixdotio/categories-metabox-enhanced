<?php
/**
 * Get supported taxonomies
 *
 * @since 0.4.0
 */
function of_cme_supported_taxonomies() {

	$taxes   = get_taxonomies();
	$results = array();

	foreach ( $taxes as $tax ) {
		if ( is_taxonomy_hierarchical( $tax ) ) {
			$results[] = $tax;
		}
	}

	return $results;
}

/**
 * Get default settings
 *
 * @since 0.5.0
 */
function of_cme_get_defaults() {

	$defaults = array(
		'type'            => 'checkbox',
		'context'         => 'side',
		'priority'        => 'default',
		'metabox_title'   => '',
		'indented'        => 1,
		'allow_new_terms' => 1,
		'force_selection' => 1,
	);

	return $defaults;
}

/**
 * Get the parsed settings for a taxonomy, with defaults applied.
 *
 * @since 0.8.0
 *
 * @param string $taxonomy Taxonomy slug.
 * @return array<string, mixed>
 */
function of_cme_get_taxonomy_options( $taxonomy ) {
	return wp_parse_args(
		get_option( 'category-metabox-enhanced_' . $taxonomy ),
		of_cme_get_defaults()
	);
}

/**
 * Whether the configured option type renders the single-term UI.
 *
 * @since 0.8.0
 *
 * @param string $type Option type.
 * @return bool
 */
function of_cme_is_single_term_type( $type ) {
	return in_array( $type, array( 'radio', 'select' ), true );
}

/**
 * Enforce the single-term invariant for taxonomies configured as radio/select.
 *
 * Two cases:
 *   - >1 terms: coerce to the last one (UI sends one, but REST/programmatic
 *     callers can still submit multiple).
 *   - 0 terms with force_selection on: substitute the first available term.
 *     This is the only place a UI-bypassing caller (REST, wp-cli) can be
 *     stopped from creating an empty assignment for a taxonomy that's
 *     configured to require one.
 *
 * @since 0.8.0
 *
 * @param array<int|string>|string $terms     Terms being assigned.
 * @param int                      $object_id Unused.
 * @param string                   $taxonomy  Taxonomy slug.
 * @return array<int|string>|string
 */
function of_cme_enforce_single_term( $terms, $object_id, $taxonomy ) {

	if ( ! is_array( $terms ) ) {
		return $terms;
	}

	if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
		return $terms;
	}

	$options = of_cme_get_taxonomy_options( $taxonomy );
	if ( ! of_cme_is_single_term_type( $options['type'] ) ) {
		return $terms;
	}

	// Drop the "no selection" sentinel before counting. The classic library's
	// Clear affordance submits ['0'] (Taxonomy_Single_Term renders a hidden
	// value=0 radio) and REST/programmatic callers can do the same. Term ID 0
	// is never a real term, so filtering preemptively keeps the >1 / 0 / 1
	// branches below honest — without this, [0] sailed through as count==1
	// and bypassed the force_selection substitution.
	//
	// Gate on is_numeric so slug-based callers (wp_set_object_terms accepts
	// term names/slugs as strings, and this filter fires before WP resolves
	// them to IDs) aren't silently dropped — (int) 'my-category' is 0 in PHP.
	$filtered = array_values(
		array_filter(
			$terms,
			static function ( $term ) {
				return ! is_numeric( $term ) || 0 !== (int) $term;
			}
		)
	);

	if ( count( $filtered ) > 1 ) {
		return array( end( $filtered ) );
	}

	if ( count( $filtered ) === 0 && ! empty( $options['force_selection'] ) ) {
		$default = of_cme_resolve_default_term( $taxonomy );
		if ( $default ) {
			return array( $default );
		}
	}

	return $filtered;
}
add_filter( 'pre_set_object_terms', 'of_cme_enforce_single_term', 10, 3 );

/**
 * Resolve the term to substitute for an empty force_selection submission.
 *
 * Mirrors how the classic Taxonomy_Single_Term library picks its pre-checked
 * default (process_default → get_option('default_<slug>')) and honors the
 * 'default_term' arg passed to register_taxonomy (WP_Taxonomy::default_term,
 * WP 5.5+). Falls back to the first term by name asc if neither is set, so
 * custom hierarchical taxonomies that haven't configured a default still
 * end up with a real term.
 *
 * Filterable via 'of_cme_force_selection_default_term' so site owners can
 * override per-taxonomy without touching this code.
 *
 * @since 0.9.0
 *
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID, or 0 if no terms exist for the taxonomy.
 */
function of_cme_resolve_default_term( $taxonomy ) {

	$tax_obj = get_taxonomy( $taxonomy );
	$default = 0;

	// Verify the option still points at a live term — stale IDs from deleted
	// terms would let wp_set_object_terms drop the assignment silently and
	// break the force_selection invariant we're trying to uphold.
	//
	// 'default_<tax>' is the legacy key (still used for the built-in
	// `category` taxonomy via the default_category option). WP 5.5+ stores
	// the auto-created default term for taxonomies registered with the
	// 'default_term' arg under 'default_term_<tax>' via
	// _wp_register_default_term(). Both keys can coexist; check the legacy
	// one first for backward compat.
	foreach ( array( 'default_' . $taxonomy, 'default_term_' . $taxonomy ) as $option_key ) {
		$option_id = (int) get_option( $option_key );
		if ( $option_id && term_exists( $option_id, $taxonomy ) ) {
			$default = $option_id;
			break;
		}
	}

	if ( ! $default && $tax_obj && ! empty( $tax_obj->default_term ) ) {
		$slug = is_array( $tax_obj->default_term )
			? ( isset( $tax_obj->default_term['slug'] ) ? $tax_obj->default_term['slug'] : '' )
			: $tax_obj->default_term;
		if ( $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof WP_Term ) {
				$default = (int) $term->term_id;
			}
		}
	}

	if ( ! $default ) {
		$first = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 1,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $first ) && ! empty( $first ) ) {
			$default = (int) $first[0];
		}
	}

	return (int) apply_filters( 'of_cme_force_selection_default_term', $default, $taxonomy );
}
