<?php
/**
 * Server-side rendering of the `core/comments` block.
 *
 * @package WordPress
 */

/**
 * Renders the `core/comments` block on the server.
 *
 * This render callback is mainly for rendering a dynamic, legacy version of
 * this block (the old `core/post-comments`). It uses the `comments_template()`
 * function to generate the output, in the same way as classic PHP themes.
 *
 * As this callback will always run during SSR, first we need to check whether
 * the block is in legacy mode. If not, the HTML generated in the editor is
 * returned instead.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 * @return string Returns the filtered post comments for the current post wrapped inside "p" tags.
 */
function render_block_core_comments( $attributes, $content, $block ) {
	global $post;

	$post_id = $block->context['postId'];
	if ( ! isset( $post_id ) ) {
		return '';
	}

	$comment_args = array(
		'post_id' => $post_id,
		'count'   => true,
		'status'  => 'approve',
	);
	// Return early if there are no comments and comments are closed.
	if ( ! comments_open( $post_id ) && get_comments( $comment_args ) === 0 ) {
		return '';
	}

	// If this isn't the legacy block, we need to render the static version of this block.
	$is_legacy = 'core/post-comments' === $block->name || ! empty( $attributes['legacy'] );
	if ( ! $is_legacy ) {
		return $block->render( array( 'dynamic' => false ) );
	}

	$post_before = $post;
	$post        = get_post( $post_id );
	setup_postdata( $post );

	ob_start();

	/*
	 * There's a deprecation warning generated by WP Core.
	 * Ideally this deprecation is removed from Core.
	 * In the meantime, this removes it from the output.
	 */
	add_filter( 'deprecated_file_trigger_error', '__return_false' );
	comments_template();
	remove_filter( 'deprecated_file_trigger_error', '__return_false' );

	$output = ob_get_clean();
	$post   = $post_before;

	$classnames = array();
	// Adds the old class name for styles' backwards compatibility.
	if ( isset( $attributes['legacy'] ) ) {
		$classnames[] = 'wp-block-post-comments';
	}
	if ( isset( $attributes['textAlign'] ) ) {
		$classnames[] = 'text-align-' . $attributes['textAlign'];
	}

	$wrapper_attributes = get_block_wrapper_attributes(
		array( 'class' => implode( ' ', $classnames ) )
	);

	/*
	 * Enqueues scripts and styles required only for the legacy version. That is
	 * why they are not defined in `block.json`.
	 */
	wp_enqueue_script( 'comment-reply' );
	enqueue_legacy_post_comments_block_styles( $block->name );

	return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $output );
}

/**
 * Registers the `core/comments` block on the server.
 */
function register_block_core_comments() {
	register_block_type_from_metadata(
		__DIR__ . '/comments',
		array(
			'render_callback'   => 'render_block_core_comments',
			'skip_inner_blocks' => true,
		)
	);
}
add_action( 'init', 'register_block_core_comments' );

/**
 * Use the button block classes for the form-submit button.
 *
 * @param array $fields The default comment form arguments.
 *
 * @return array Returns the modified fields.
 */
function comments_block_form_defaults( $fields ) {
	if ( wp_is_block_theme() ) {
		$fields['submit_button'] = '<input name="%1$s" type="submit" id="%2$s" class="%3$s wp-block-button__link ' . WP_Theme_JSON_Gutenberg::get_element_class_name( 'button' ) . '" value="%4$s" />';
		$fields['submit_field']  = '<p class="form-submit wp-block-button">%1$s %2$s</p>';
	}

	return $fields;
}
add_filter( 'comment_form_defaults', 'comments_block_form_defaults' );

/**
 * Enqueues styles from the legacy `core/post-comments` block. These styles are
 * required only by the block's fallback.
 *
 * @param string $block_name Name of the new block type.
 */
function enqueue_legacy_post_comments_block_styles( $block_name ) {
	static $are_styles_enqueued = false;

	if ( ! $are_styles_enqueued ) {
		$handles = array(
			'wp-block-post-comments',
			'wp-block-buttons',
			'wp-block-button',
		);
		foreach ( $handles as $handle ) {
			wp_enqueue_block_style( $block_name, array( 'handle' => $handle ) );
		}
		$are_styles_enqueued = true;
	}
}

/**
 * Ensures backwards compatibility for any users running the Gutenberg plugin
 * who have used Post Comments before it was merged into Comments Query Loop.
 *
 * The same approach was followed when core/query-loop was renamed to
 * core/post-template.
 *
 * @see https://github.com/WordPress/gutenberg/pull/41807
 * @see https://github.com/WordPress/gutenberg/pull/32514
 */
function register_legacy_post_comments_block() {
	$registry = WP_Block_Type_Registry::get_instance();

	/*
	 * Remove the old `post-comments` block if it was already registered, as it
	 * is about to be replaced by the type defined below.
	 */
	if ( $registry->is_registered( 'core/post-comments' ) ) {
		unregister_block_type( 'core/post-comments' );
	}

	// Recreate the legacy block metadata.
	$metadata = array(
		'name'              => 'core/post-comments',
		'category'          => 'theme',
		'attributes'        => array(
			'textAlign' => array(
				'type' => 'string',
			),
		),
		'uses_context'      => array(
			'postId',
			'postType',
		),
		'supports'          => array(
			'html'       => false,
			'align'      => array( 'wide', 'full' ),
			'typography' => array(
				'fontSize'                      => true,
				'lineHeight'                    => true,
				'__experimentalFontStyle'       => true,
				'__experimentalFontWeight'      => true,
				'__experimentalLetterSpacing'   => true,
				'__experimentalTextTransform'   => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true,
				),
			),
			'color'      => array(
				'gradients'                     => true,
				'link'                          => true,
				'__experimentalDefaultControls' => array(
					'background' => true,
					'text'       => true,
				),
			),
			'inserter'   => false,
		),
		'style'             => array(
			'wp-block-post-comments',
			'wp-block-buttons',
			'wp-block-button',
		),
		'editorStyle'       => 'wp-block-post-comments-editor',
		'render_callback'   => 'render_block_core_comments',
		'skip_inner_blocks' => true,
	);

	/*
	 * Filters the metadata object, the same way it's done inside
	 * `register_block_type_from_metadata()`. This applies some default filters,
	 * like `_wp_multiple_block_styles`, which is required in this case because
	 * the block has multiple styles.
	 */
	$metadata = apply_filters( 'block_type_metadata', $metadata );

	register_block_type( 'core/post-comments', $metadata );
}
add_action( 'init', 'register_legacy_post_comments_block', 21 );
