<?php
/**
 * Plugin Name: JetEngine - Inherit post for listing injections
 * Plugin URI:  #
 * Description: Allow to inherit post from listing item settings for static injections
 * Version:     1.0.0
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

class Jet_Listing_Injection_Inherit_Post {

	private $found_listings   = array();
	private $found_posts      = array();
	private $latest_processed = array();

	public function __construct() {
		add_action( 'jet-engine/listing-injections/item-controls', function( $items_repeater ) {
			$items_repeater->add_control(
				'inherit_post_object',
				array(
					'label'        => __( 'Inherit post object', 'jet-engine' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'description'  => __( 'Inherit post object from listing item context', 'jet-engine' ),
					'default'      => '',
				)
			);

			$items_repeater->add_control(
				'infinity_loop',
				array(
					'label'        => __( 'Infinity loop', 'jet-engine' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'description'  => __( 'Repeat posts from start on count mismatch', 'jet-engine' ),
					'default'      => '',
				)
			);

		} );

		add_filter( 'jet-engine/listing-injections/static-item-post', array( $this, 'maybe_switch_post' ), 10, 4 );

	}

	public function get_listing_post_type( $listing_id = false ) {
		
		if ( ! $listing_id ) {
			return false;
		}

		if ( ! isset( $this->found_listings[ $listing_id ] ) ) {
			$listing_settings = get_post_meta( $listing_id, '_elementor_page_settings', true );
			$source           = ! empty( $listing_settings['listing_source'] ) ? $listing_settings['listing_source'] : 'posts';
			$post_type        = ! empty( $listing_settings['listing_post_type'] ) ? $listing_settings['listing_post_type'] : 'post';

			if ( 'posts' !== $source ) {
				$post_type = false;
			}

			$this->found_listings[ $listing_id ] = $post_type;

		}

		return $this->found_listings[ $listing_id ];

	}

	public function get_next_post( $post_type, $widget, $settings, $infinity_loop = false ) {

		$posts = array();

		if ( isset( $this->found_posts[ $post_type ] ) ) {
			$posts = $this->found_posts[ $post_type ];
		} else {

			$per_page = $widget->get_posts_num( $settings );
			$page = $widget->query_vars['page'];

			$posts = get_posts( array(
				'post_type' => $post_type,
				'posts_per_page' => $per_page,
				'paged' => $page,
				'fields' => 'ids',
			) );

			$this->found_posts[ $post_type ] = $posts;

		}

		$latest_post = isset( $this->latest_processed[ $post_type ] ) ? $this->latest_processed[ $post_type ] : false;
		$next_post = false;

		if ( ! $latest_post ) {
			
			$next_post = ( ! empty( $posts ) ) ? $posts[0] : false;

			if ( ! $next_post ) {
				return false;
			}

		} else {
			
			$latest_index = array_search( $latest_post, $posts );

			if ( false === $latest_index ) {
				return false;
			}

			$next_index = $latest_index + 1;

			if ( ! isset( $posts[ $next_index ] ) ) {
				if ( ! $infinity_loop ) {
					return false;
				} else {
					$next_index = 0;
				}
			}

			$next_post = $posts[ $next_index ];

		}

		$post = get_post( $next_post );

		if ( $post ) {
			$this->latest_processed[ $post_type ] = $next_post;
		}

		return $post;

	}

	public function maybe_switch_post( $post, $item, $settings, $widget ) {
		
		if ( empty( $item['inherit_post_object'] ) ) {
			return $post;
		}

		$listing_id = ! empty( $item['item'] ) ? absint( $item['item'] ) : false;
		$post_type  = $this->get_listing_post_type( $listing_id );

		if ( ! $post_type ) {
			return $post;
		}

		$infinity_loop = isset( $item['infinity_loop'] ) ? $item['infinity_loop'] : false;
		$infinity_loop = filter_var( $infinity_loop, FILTER_VALIDATE_BOOLEAN );

		return $this->get_next_post( $post_type, $widget, $settings, $infinity_loop );

	}


}

new Jet_Listing_Injection_Inherit_Post();
