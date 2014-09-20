<?php
/**
* Get supported taxonomies
*
* @since 0.4.0
*/
function of_cme_supported_taxonomies() {

        $taxes = get_taxonomies();
        $results = array();

        foreach ( $taxes as $tax ) {
                if ( is_taxonomy_hierarchical( $tax ) ) {
                        $results[] = $tax;
                }
        }

        return $results;
}
