( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var InnerBlocks   = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/facepile', {
		icon: el( 'svg', {
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			}, el ( 'path', {
				d: 'M12 4a8 8 0 0 0-8 8 8 8 0 0 0 6.64 7.883 8 8 0 0 0 .786.096A8 8 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8zm0 1.5a6.5 6.5 0 0 1 6.5 6.5 6.5 6.5 0 0 1-.678 2.875 12.5 9 0 0 0-4.576-.855 3.5 3.5 0 0 0 2.254-3.27 3.5 3.5 0 0 0-3.5-3.5 3.5 3.5 0 0 0-3.5 3.5 3.5 3.5 0 0 0 2.432 3.332 12.5 9 0 0 0-4.59 1.1A6.5 6.5 0 0 1 5.5 12 6.5 6.5 0 0 1 12 5.5z',
			} )
		),
		description: __( 'Contains the blocks to display Webmention “likes,” “reposts,” etc. as a so-called facepile.', 'indieblocks' ),
		edit: ( props ) => {
			var title        = props.attributes.title || '';
			var titleElement = props.attributes.titleElement || '';

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				el( InnerBlocks, {
					template: [
						[ 'core/heading', { content: __( 'Likes, Bookmarks, and Reposts', 'indieblocks' ) } ],
						[ 'indieblocks/facepile-content' ]
					],
					templateLock: false,
				} )
			);
		},
		save: ( props ) => el( 'div', useBlockProps.save(),
			el( InnerBlocks.Content )
		),
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
