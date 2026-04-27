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
 * Enforce the single-term invariant after WordPress commits term assignments.
 *
 * WordPress core does not expose a pre-write filter on object terms —
 * `wp_set_object_terms` only fires the `set_object_terms` action after the
 * relationships are written. We hook that action, inspect the committed
 * state, and re-issue `wp_set_object_terms` with corrected IDs when the
 * single-term contract is violated. A static recursion guard ensures the
 * re-issue does not trigger a second pass.
 *
 * Two cases trigger correction:
 *   - >1 terms committed: coerce to the last (matches Taxonomy_Single_Term
 *     classic-editor semantics).
 *   - 0 terms committed AND force_selection on: substitute the resolved
 *     default term, closing the bypass that REST and programmatic callers
 *     would otherwise have on the single-term invariant.
 *
 * Append-mode calls are skipped: a caller that explicitly appends should
 * not have prior terms removed by us.
 *
 * Note: other listeners on `set_object_terms` see the uncorrected state
 * briefly, then a second action fires with the correction. Anything that
 * caches "current terms" in this action will cache wrong then re-cache.
 * This is the trade-off for not having a pre-write hook.
 *
 * @since 0.9.1
 *
 * @param int           $object_id  Object ID receiving the terms.
 * @param array         $terms      Terms originally passed to wp_set_object_terms.
 * @param array         $tt_ids     Term taxonomy IDs that were set.
 * @param string        $taxonomy   Taxonomy slug.
 * @param bool          $append     Whether the call appended rather than replaced.
 * @param array         $old_tt_ids Old term taxonomy IDs before the write.
 */
function of_cme_enforce_single_term( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

	static $reentering = false;
	if ( $reentering ) {
		return;
	}

	if ( $append ) {
		return;
	}

	if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
		return;
	}

	$options = of_cme_get_taxonomy_options( $taxonomy );
	if ( ! of_cme_is_single_term_type( $options['type'] ) ) {
		return;
	}

	$current_ids = wp_get_object_terms(
		$object_id,
		$taxonomy,
		array(
			'fields'                 => 'ids',
			'orderby'                => 'none',
			'hide_empty'             => false,
			'update_term_meta_cache' => false,
		)
	);
	if ( is_wp_error( $current_ids ) ) {
		return;
	}

	$count        = count( $current_ids );
	$new_ids      = $current_ids;
	$needs_change = false;

	if ( $count > 1 ) {
		$new_ids      = array( (int) end( $current_ids ) );
		$needs_change = true;
	} elseif ( 0 === $count && ! empty( $options['force_selection'] ) ) {
		$default = of_cme_resolve_default_term( $taxonomy );
		if ( $default ) {
			$new_ids      = array( $default );
			$needs_change = true;
		}
	}

	if ( $needs_change ) {
		$reentering = true;
		wp_set_object_terms( $object_id, $new_ids, $taxonomy, false );
		$reentering = false;
	}
}
add_action( 'set_object_terms', 'of_cme_enforce_single_term', 10, 6 );

/**
 * Resolve the term to substitute for an empty force_selection submission.
 *
 * Resolution order:
 *   1. default_<tax> option (legacy; populated for built-in `category`).
 *   2. default_term_<tax> option (WP 5.5+ register_taxonomy `default_term`).
 *   3. WP_Taxonomy::default_term slug lookup.
 *   4. First term by name asc.
 *
 * Filterable via 'of_cme_force_selection_default_term'.
 *
 * @since 0.9.0
 *
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID, or 0 if no terms exist for the taxonomy.
 */
function of_cme_resolve_default_term( $taxonomy ) {

	$tax_obj = get_taxonomy( $taxonomy );
	$default = 0;

	// term_exists guards against stale option IDs — wp_set_object_terms
	// silently drops nonexistent terms, which would defeat force_selection.
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
