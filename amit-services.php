<?php
/**
 * Plugin Name: Amit Services
 * Description: Service post type and service type taxonomy.
 * Version: 1.0.0
 * Author: Amit Shukla
 *
 * @package AmitServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Service post type and the Service Type taxonomy.
 */
function amit_services_register() {
	register_post_type(
		'service',
		array(
			'labels'       => array(
				'name'          => 'Services',
				'singular_name' => 'Service',
			),
			'public'       => true,
			'has_archive'  => true,
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-hammer',
		)
	);

	register_taxonomy(
		'service_type',
		'service',
		array(
			'labels'       => array(
				'name'          => 'Service Types',
				'singular_name' => 'Service Type',
			),
			'hierarchical' => true,
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'amit_services_register' );

/**
 * Run on activation: register the post type, then rebuild the URL list.
 */
function amit_services_activate() {
	amit_services_register(); // Register the post type first.
	flush_rewrite_rules(); // Then rebuild the URL list.
}
register_activation_hook( __FILE__, 'amit_services_activate' );

/**
 * Run on deactivation: remove the Service URLs from the list.
 */
function amit_services_deactivate() {
	flush_rewrite_rules(); // Remove the Service URLs from the list.
}
register_deactivation_hook( __FILE__, 'amit_services_deactivate' );

/**
 * Add a call-to-action note below a single service.
 *
 * @param string $content The post content.
 * @return string The content, with the note added on service pages.
 */
function amit_services_add_note( $content ) {
	if ( is_singular( 'service' ) ) {
		$content .= '<p>Call us for a quote.</p>';
	}
	return $content;
}
add_filter( 'the_content', 'amit_services_add_note', 20, 1 );

/**
 * Shortcode [amit_services]: list all service titles.
 *
 * @return string The HTML list.
 */
function amit_services_shortcode() {
	$query = new WP_Query(
		array(
			'post_type'      => 'service',
			'posts_per_page' => -1,
		)
	);

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

/**
 * Add the "Service Info" page to the admin menu.
 */
function amit_services_admin_menu() {
	add_menu_page(
		'Service Info', // 1. Title in the browser tab.
		'Service Info', // 2. Text in the sidebar.
		'manage_options', // 3. Who may open it (admins).
		'amit-service-info', // 4. Unique slug (the URL part).
		'amit_services_admin_page', // 5. Function that draws the page.
		'dashicons-info' // 6. Icon.
	);
}
add_action( 'admin_menu', 'amit_services_admin_menu' );

/**
 * Draw the "Service Info" admin page.
 */
function amit_services_admin_page() {
	$count = wp_count_posts( 'service' )->publish;

	echo '<div class="wrap">';
	echo '<h1>Service Info</h1>';
	echo '<p>Welcome to the services plugin.</p>';
	echo '<p>Total services: ' . esc_html( $count ) . '</p>';
	echo '</div>';
}

/**
 * Shortcode [amit_api_posts]: show posts from an API, cached for one hour.
 *
 * @return string The HTML list, or an error message.
 */
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

		// Save only after both checks passed and we have real data.
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

add_action( 'rest_api_init', 'amit_register_routes' );

/**
 * Register the GET and POST routes for /amit/v1/services.
 */
function amit_register_routes() {
	register_rest_route(
		'amit/v1',
		'/services',
		array(
			'methods'             => 'GET',
			'callback'            => 'amit_services_callback',
			'permission_callback' => '__return_true',
			'args'                => array(
				'type' => array(
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	register_rest_route(
		'amit/v1',
		'/services',
		array(
			'methods'             => 'POST',
			'callback'            => 'amit_create_service',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'title' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}

/**
 * GET callback: return services, optionally filtered by ?type=.
 *
 * @param WP_REST_Request $request The REST request.
 * @return array List of services.
 */
function amit_services_callback( $request ) {
	$type = $request->get_param( 'type' );

	$args = array(
		'post_type'      => 'service',
		'posts_per_page' => -1,
	);

	if ( ! empty( $type ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'service_type',
				'field'    => 'slug',
				'terms'    => sanitize_text_field( $type ),
			),
		);
	}

	$query = new WP_Query( $args );

	$data = array();

	while ( $query->have_posts() ) {
		$query->the_post();
		$data[] = array(
			'id'    => get_the_ID(),
			'title' => get_the_title(),
			'link'  => get_permalink(),
		);
	}

	wp_reset_postdata();

	return $data;
}

/**
 * POST callback: create a draft service from the title.
 *
 * @param WP_REST_Request $request The REST request.
 * @return array The new post ID and title.
 */
function amit_create_service( $request ) {
	$title = $request->get_param( 'title' );

	$id = wp_insert_post(
		array(
			'post_type'   => 'service',
			'post_title'  => $title,
			'post_status' => 'draft',
		)
	);

	return array(
		'id'    => $id,
		'title' => $title,
	);
}

$x=1;