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

function amit_services_shortcode() {
    $query = new WP_Query( array(
        'post_type'      => 'service',
        'posts_per_page' => -1,
    ) );

    $html = '<ul>';

    while ( $query->have_posts() ) {
        $query->the_post();
        $html .= '<li>' . esc_html( get_the_title() ) . '</li>';
    }
    $html .= '</ul>';
    wp_reset_postdata();
    return $html;
}
add_shortcode( 'amit_services', 'amit_services_shortcode' );

function amit_services_admin_menu() {
    add_menu_page(
        'Service Info',            // 1. title in the browser tab
        'Service Info',            // 2. text in the sidebar
        'manage_options',          // 3. who may open it (admins)
        'amit-service-info',       // 4. unique slug (the URL part)
        'amit_services_admin_page',// 5. function that draws the page
        'dashicons-info'           // 6. icon
    );
}
add_action( 'admin_menu', 'amit_services_admin_menu' );

function amit_services_admin_page() {
    $count = wp_count_posts( 'service' )->publish;

    echo '<div class="wrap">';
    echo '<h1>Service Info</h1>';
    echo '<p>Welcome to the services plugin.</p>';
    echo '<p>Total services: ' . esc_html( $count ) . '</p>';
    echo '</div>';
}

function amit_api_posts_shortcode() {
    $posts = get_transient( 'amit_api_posts' );

    if ( false === $posts ) {
        $response = wp_remote_get( 'https://jsonplaceholder.typicode.com/posts?_limit=3' );

        if ( is_wp_error( $response ) ) {
            return '<p>Error fetching API data.</p>';
        }

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return '<p>API returned an error.</p>';
        }

        $body  = wp_remote_retrieve_body( $response );
        $posts = json_decode( $body, true );

        // Save only after both checks passed and we have real data
        set_transient( 'amit_api_posts', $posts, HOUR_IN_SECONDS );
    }
  
    $html = '<ul>';
    foreach ( $posts as $post ) {
        $html .= '<li>' . esc_html( $post['title'] ) . '</li>';
    }
    $html .= '</ul>';

    return $html;
}
add_shortcode( 'amit_api_posts', 'amit_api_posts_shortcode' );