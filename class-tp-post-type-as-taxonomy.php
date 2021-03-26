<?php
/**
 * Plugin Name: Post type as taxonomy
 * Description: Project a post type as a taxonomy.
 */

class TP_Post_Type_As_Taxonomy {

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 */
	protected static $instance = null;

	/**
	 * Returns static instance of class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Post types.
	 *
	 * @var array
	 */
	private $post_types = [];

	/**
	 * Inits the class and registers the init call.
	 *
	 * @return self
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ], 11 );
		add_action( 'save_post', [ $this, 'update' ] );
		add_action( 'untrashed_post', [ $this, 'update' ] );
		add_action( 'trashed_post', [ $this, 'delete' ] );
	}

	/**
	 * Which post types want to be ordered
	 *
	 * @return void
	 */
	public function init() {
		$post_types = get_post_types();

		if ( ! $post_types ) {
			return;
		}

		foreach ( $post_types as $post_type ) {
			$post_type = get_post_type_object( $post_type );

			if ( isset( $post_type->as_taxonomy ) ) {
				$this->post_types[ $post_type->name ] = $post_type->as_taxonomy;
			}
		}

	}

	/**
	 * Add or update a term in our taxonomy
	 *
	 * @param int $post_id The posts ID that has to be linked to our taxonomy.
	 */
	public function update( $post_id ) {
		if ( ! is_array( $this->post_types ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( ! isset( $this->post_types[ $post->post_type ] ) ) {
			return;
		}

		$taxonomy = $this->post_types[ $post->post_type ];

		$term_id = get_post_meta( $post->ID, 'term_id', true );

		if ( $term_id ) {

			// This post is already linked to a term, let's see if it still exists.
			$term = get_term( $term_id, $taxonomy );

			if ( ! $term ) {
				return;
			}

			// It exists, update optional changes.
			wp_update_term(
				$term_id,
				$taxonomy,
				[
					'name' => $post->post_title,
					'slug' => $post->post_name,
				]
			);
			return;
	
		}

		// If the code made it here, either the term link didn't exist or still has to be created.
		// Either way, create a new term link!
		$term = wp_insert_term(
			$post->post_title,
			$taxonomy,
			[
				'slug' => $post->post_name,
			]
		);

		if ( ! is_wp_error( $term ) ) {
			update_post_meta( $post->ID, 'term_id', $term['term_id'] );
		} elseif ( isset( $term->error_data['term_exists'] ) ) {
			update_post_meta( $post->ID, 'term_id', $term->error_data['term_exists'] );
		}

	}

	/**
	 * Delete a term
	 *
	 * @param int $post_id The posts ID which taxonomy term has to be removed.
	 */
	public function delete( $post_id ) {
		if ( ! is_array( $this->post_types ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! isset( $this->post_types[ $post->post_type ] ) ) {
			return;
		}

		$taxonomy = $this->post_types[ $post->post_type ];
		$term_id = get_post_meta( $post->ID, 'term_id', true );

		if ( ! $term_id ) {
			return;
		}

		// The post is linked to a term, delete it!
		$term = get_term( $term_id, $taxonomy );

		if ( $term ) {
			// The linked term still exists, now really delete.
			wp_delete_term( $term_id, $taxonomy );
		}
	}

	/**
	 * Convenience functions
	 *
	 * Retrieve the term or part of it that is linked to a certain post.
	 * This function can be used to easily get the term ID or slug to filter on posts which this post is linked to (using WP_Query).
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $taxonomy The taxonomy where the term should be in.
	 * @param string $part Optional. The part of the term to return.
	 */
	public function get_term( $post_id, $taxonomy, $part = '' ) {
		if ( ! $post_id && ! $taxonomy ) {
			return;
		}

		$term = get_term( get_post_meta( $post_id, 'term_id', true ), $taxonomy );

		if ( ! $term && is_wp_error( $term ) ) {
			return $term;
		}

		if ( $part ) {
			return $term->$part;
		}
	}

	/**
	 * Retrieve the terms or part of the terms which are linked to a certain post.
	 * This function can be used to get all terms (or maybe just the ID's) which can be used to filter
	 * and retrieve all posts which are linked to the current post (using WP_Query).
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $taxonomy The taxonomy of which to collect the.
	 * @param string $part Optional. The part of the terms to return.
	 */
	public function get_terms( $post_id, $taxonomy, $part = '' ) {
		if ( ! $post_id && ! $taxonomy ) {
			return;
		}

		$terms = wp_get_post_terms( $post_id, $taxonomy );

		if ( ! $terms ) {
			return;
		}
		
		// Return complete objects.
		if ( ! $part ) {
			return $terms;
		}

		// Return part of the terms.
		$terms_part = [];
		foreach ( $terms as $term ) {
			$terms_part[] = $term->$part;
		}

		return $terms_part;
	}

	/**
	 * Retrieve the post that is linked to a term
	 *
	 * @param object $term The linked term.
	 */
	public function get_post( $term ) {
		$query = new WP_Query(
			[
				'post_type'   => 'any',
				'meta_key'    => 'term_id',
				'meta_value'  => $term->term_id,
				'posts_per_page' => 1,
			]
		);

		$posts = $query->posts;

		if ( is_object( $posts[0] ) ) {
			return $posts[0];
		}
	}
}
