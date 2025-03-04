/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import {
	AlignmentControl,
	BlockControls,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import HeadingLevelDropdown from '../heading/heading-level-dropdown';

export default function Edit( {
	attributes: { textAlign, showPostTitle, showCommentsCount, level },
	setAttributes,
	context: { postType, postId },
} ) {
	const TagName = 'h' + level;
	const [ commentsCount, setCommentsCount ] = useState();
	const [ rawTitle ] = useEntityProp( 'postType', postType, 'title', postId );
	const isSiteEditor = typeof postId === 'undefined';
	const blockProps = useBlockProps( {
		className: classnames( {
			[ `text-align-${ textAlign }` ]: textAlign,
		} ),
	} );

	useEffect( () => {
		if ( isSiteEditor ) {
			setCommentsCount( 3 );
			return;
		}
		const currentPostId = postId;
		apiFetch( {
			path: addQueryArgs( '/wp/v2/comments', {
				post: postId,
				_fields: 'id',
			} ),
			method: 'HEAD',
			parse: false,
		} )
			.then( ( res ) => {
				// Stale requests will have the `currentPostId` of an older closure.
				if ( currentPostId === postId ) {
					setCommentsCount(
						parseInt( res.headers.get( 'X-WP-Total' ) )
					);
				}
			} )
			.catch( () => {
				setCommentsCount( 0 );
			} );
	}, [ postId ] );

	const blockControls = (
		<BlockControls group="block">
			<AlignmentControl
				value={ textAlign }
				onChange={ ( newAlign ) =>
					setAttributes( { textAlign: newAlign } )
				}
			/>
			<HeadingLevelDropdown
				selectedLevel={ level }
				onChange={ ( newLevel ) =>
					setAttributes( { level: newLevel } )
				}
			/>
		</BlockControls>
	);

	const inspectorControls = (
		<InspectorControls>
			<PanelBody title={ __( 'Settings' ) }>
				<ToggleControl
					label={ __( 'Show post title' ) }
					checked={ showPostTitle }
					onChange={ ( value ) =>
						setAttributes( { showPostTitle: value } )
					}
				/>
				<ToggleControl
					label={ __( 'Show comments count' ) }
					checked={ showCommentsCount }
					onChange={ ( value ) =>
						setAttributes( { showCommentsCount: value } )
					}
				/>
			</PanelBody>
		</InspectorControls>
	);

	const postTitle = isSiteEditor ? __( '"Post Title"' ) : `"${ rawTitle }"`;

	let placeholder;
	if ( showCommentsCount && commentsCount !== undefined ) {
		if ( showPostTitle ) {
			if ( commentsCount === 1 ) {
				/* translators: %s: Post title. */
				placeholder = sprintf( __( 'One response to %s' ), postTitle );
			} else {
				placeholder = sprintf(
					/* translators: 1: Number of comments, 2: Post title. */
					_n(
						'%1$s response to %2$s',
						'%1$s responses to %2$s',
						commentsCount
					),
					commentsCount,
					postTitle
				);
			}
		} else if ( commentsCount === 1 ) {
			placeholder = __( 'One response' );
		} else {
			placeholder = sprintf(
				/* translators: %s: Number of comments. */
				_n( '%s response', '%s responses', commentsCount ),
				commentsCount
			);
		}
	} else if ( showPostTitle ) {
		if ( commentsCount === 1 ) {
			/* translators: %s: Post title. */
			placeholder = sprintf( __( 'Response to %s' ), postTitle );
		} else {
			/* translators: %s: Post title. */
			placeholder = sprintf( __( 'Responses to %s' ), postTitle );
		}
	} else if ( commentsCount === 1 ) {
		placeholder = __( 'Response' );
	} else {
		placeholder = __( 'Responses' );
	}

	return (
		<>
			{ blockControls }
			{ inspectorControls }
			<TagName { ...blockProps }>{ placeholder }</TagName>
		</>
	);
}
