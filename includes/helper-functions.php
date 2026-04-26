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

	$options = get_option( 'category-metabox-enhanced_' . $taxonomy );
	if ( ! is_array( $options ) || empty( $options['type'] ) ) {
		return $terms;
	}

	if ( ! in_array( $options['type'], array( 'radio', 'select' ), true ) ) {
		return $terms;
	}

	return array( end( $terms ) );
}
add_filter( 'pre_set_object_terms', 'of_cme_enforce_single_term', 10, 3 );
