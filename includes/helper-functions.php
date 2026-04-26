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

	if ( count( $terms ) > 1 ) {
		return array( end( $terms ) );
	}

	if ( count( $terms ) === 0 && ! empty( $options['force_selection'] ) ) {
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
			return array( (int) $first[0] );
		}
	}

	return $terms;
}
add_filter( 'pre_set_object_terms', 'of_cme_enforce_single_term', 10, 3 );
