Post type as taxonomy
=====================

Project a post type as a taxonomy. Made for WordPress.

## Usage

### Step 1
Register a post type and link it to a taxonomy using the ```as_taxonomy``` attribute
	
	'supports'    => array( 'title', 'editor', 'excerpt', 'revisions' ),
	'as_taxonomy' => $taxonomy,

### Step 2
Also register the taxonomy

	$args = array(
		'hierarchical' => true,
		'label'        => 'Taxonomy name',
	);
	register_taxonomy( $taxonomy, $post_type, $args );
