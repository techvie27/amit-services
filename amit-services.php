<?php
/**
 * Plugin Name: Amit Services
 * Description: Service post type and service type taxonomy.
 * Version: 1.0.0
 * Author: Amit Shukla
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function amit_services_register() {
     register_post_type( 'service', array(
        'labels'      => array(
            'name'          => 'Services',
            'singular_name' => 'Service',
        ),
        'public'      => true,
        'has_archive' => true,
        'supports'    => array( 'title', 'editor', 'thumbnail' ),
        'menu_icon'   => 'dashicons-hammer',
    ) );

     register_taxonomy( 'service_type', 'service', array(
        'labels'       => array(
            'name'          => 'Service Types',
            'singular_name' => 'Service Type',
        ),
        'hierarchical' => true,
        'show_in_rest' => true,
    ) );
}
add_action( 'init', 'amit_services_register' );

function amit_services_activate() {
    amit_services_register();   // 1. register the post type first
    flush_rewrite_rules();      // 2. then rebuild the URL list
}
register_activation_hook( __FILE__, 'amit_services_activate' );

function amit_services_deactivate() {
    flush_rewrite_rules();      // remove the Service URLs from the list
}
register_deactivation_hook( __FILE__, 'amit_services_deactivate' );

function amit_services_add_note( $content ) {
    if ( is_singular( 'service' ) ) {
        $content .= '<p>Call us for a quote.</p>';
    }
    return $content;
}
add_filter( 'the_content', 'amit_services_add_note', 20, 1 );