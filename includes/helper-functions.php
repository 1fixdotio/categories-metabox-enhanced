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
 * The Block Editor sidebar UI only sends one term, but a REST or programmatic
 * caller can still submit multiple — coerce to the last one.
 *
 * @since 0.8.0
 *
 * @param array<int|string>|string $terms    Terms being assigned.
 * @param int                      $object_id Unused.
 * @param string                   $taxonomy Taxonomy slug.
 * @return array<int|string>|string
 */
function of_cme_enforce_single_term( $terms, $object_id, $taxonomy ) {

	if ( ! is_array( $terms ) || count( $terms ) <= 1 ) {
		return $terms;
	}

	if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
		return $terms;
	}

	$options = of_cme_get_taxonomy_options( $taxonomy );
	if ( ! of_cme_is_single_term_type( $options['type'] ) ) {
		return $terms;
	}

	return array( end( $terms ) );
}
add_filter( 'pre_set_object_terms', 'of_cme_enforce_single_term', 10, 3 );
