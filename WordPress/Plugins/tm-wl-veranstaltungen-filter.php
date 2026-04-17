<?php
/**
 * Plugin Name:       trolleymaker Veranstaltungen Filter (Sortierung Datum korrekt)
 * Description:       REGIONSSPEZIFISCH! Filtert und sortiert den Custom Post Type "veranstaltungen" nach dem ACF-Feld "startzeitpunkt". Vergangene Veranstaltungen werden automatisch ausgeblendet (REST-API & Frontend).
 * Version:           1.0.2
 * Author:            trolleymaker
 * Author URI:        https://simpli-citycard.com/
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function tm_get_current_datetime_for_events() {
    return current_time( 'mysql' );
}

/**
 * Prüft, ob die Query Veranstaltungen betrifft.
 */
function tm_is_veranstaltungen_query( $query ) {
    $post_type = $query->get( 'post_type' );

    if ( $post_type === 'veranstaltungen' ) {
        return true;
    }

    if ( is_array( $post_type ) && in_array( 'veranstaltungen', $post_type, true ) ) {
        return true;
    }

    if ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'veranstaltungen' ) ) {
        return true;
    }

    return false;
}

/**
 * REST-API:
 * Nur zukünftige Veranstaltungen, sortiert nach Startzeitpunkt.
 */
add_filter( 'rest_veranstaltungen_query', function( $args, $request ) {
    $now = tm_get_current_datetime_for_events();

    $meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] )
        ? $args['meta_query']
        : [];

    $meta_query[] = [
        'key'     => 'startzeitpunkt',
        'value'   => $now,
        'compare' => '>=',
        'type'    => 'DATETIME',
    ];

    $args['meta_query'] = $meta_query;
    $args['meta_key']   = 'startzeitpunkt';
    $args['orderby']    = 'meta_value';
    $args['order']      = 'ASC';

    return $args;
}, 10, 2 );

/**
 * Frontend:
 * Vergangene Veranstaltungen ausblenden und chronologisch sortieren.
 */
add_action( 'pre_get_posts', 'tm_filter_veranstaltungen_everywhere' );
function tm_filter_veranstaltungen_everywhere( $query ) {
    if ( is_admin() || $query->get( 'suppress_filters' ) ) {
        return;
    }

    if ( ! tm_is_veranstaltungen_query( $query ) ) {
        return;
    }

    $now = tm_get_current_datetime_for_events();

    $meta_query = $query->get( 'meta_query' );

    if ( ! is_array( $meta_query ) ) {
        $meta_query = [];
    }

    $meta_query[] = [
        'key'     => 'startzeitpunkt',
        'value'   => $now,
        'compare' => '>=',
        'type'    => 'DATETIME',
    ];

    $query->set( 'meta_query', $meta_query );
    $query->set( 'meta_key', 'startzeitpunkt' );
    $query->set( 'orderby', 'meta_value' );
    $query->set( 'order', 'ASC' );
}