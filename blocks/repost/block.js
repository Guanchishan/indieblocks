( function ( blocks, element, blockEditor, components, data, apiFetch, i18n, IndieBlocks ) {
	var createBlock = blocks.createBlock;

	var el          = element.createElement;
	var interpolate = element.createInterpolateElement;
	var useEffect   = element.useEffect;

	var InnerBlocks   = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;

	var CheckboxControl = components.CheckboxControl;
	var TextControl     = components.TextControl;

	var useSelect = data.useSelect;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	/**
	 * Returns a "repost context" `div`.
	 */
	function hCite( attributes, innerBlocks = null ) {
		return el( 'div', { className: 'u-repost-of h-cite' },
			[
				el( 'p', {}, // Adding paragraphs this time around.
					el( 'i', {}, // Could've been `span`, with a `className` or something, but works well enough.
						( ! attributes.author || 'undefined' === attributes.author )
							? interpolate(
							/* translators: %s: Link to the "page" being reposted. */
							sprintf( __( 'Reposted %s.', 'indieblocks' ), '<a>' + ( attributes.title || attributes.url ) + '</a>' ),
								{
									a: el( 'a', {
										className: attributes.title && attributes.url !== attributes.title
											? 'u-url p-name' // No title means no `p-name`.
											: 'u-url',
										href: attributes.url,
									} ),
								}
							)
							: interpolate(
								/* translators: %1$s: Link to the "page" being reposted. %2$s: Author of the "page" being reposted. */
								sprintf( __( 'Reposted %1$s by %2$s.', 'indieblocks' ), '<a>' + ( attributes.title || attributes.url ) + '</a>', '<span>' + attributes.author + '</span>' ),
								{
									a: el( 'a', {
										className: attributes.title && attributes.url !== attributes.title
											? 'u-url p-name'
											: 'u-url',
										href: attributes.url,
									} ),
									span: el( 'span', { className: 'p-author' } ),
								}
							)
					)
				),
				innerBlocks && ! attributes.empty
					? el( 'blockquote', { className: 'wp-block-quote e-content' },
						el( innerBlocks )
					)
					: null,
			]
		);
	}

	blocks.registerBlockType( 'indieblocks/repost', {
		description: __( 'Use the Repost block to “reblog” another (short) post verbatim while still giving credit.', 'indieblocks' ),
		edit: ( props ) => {
			var url          = props.attributes.url;
			var customTitle  = props.attributes.customTitle;
			var title        = props.attributes.title || ''; // May not be present in the saved HTML, so we need a fallback value even when `block.json` contains a default.
			var customAuthor = props.attributes.customAuthor;
			var author       = props.attributes.author || '';

			function updateEmpty( empty ) {
				props.setAttributes( { empty } );
			}

			var parentClientId = useSelect( ( select ) => select( 'core/block-editor' ).getBlockHierarchyRootClientId( props.clientId ) );
			var innerBlocks    = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks( parentClientId ) );

			// To determine whether `.e-content` and `InnerBlocks.Content`
			// should be saved (and echoed).
			useEffect( () => {
				var empty = true;

				if ( innerBlocks.length > 1 ) {
					// More than one child block.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[0] && 'undefined' !== typeof innerBlocks[0].attributes.content && innerBlocks[0].attributes.content.length ) {
					// A non-empty paragraph or heading. Empty paragraphs are
					// almost unavoidable, so it's important to get this right.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[0] && 'undefined' !== typeof innerBlocks[0].attributes.href && innerBlocks[0].attributes.href.length ) {
					// A non-empty image.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[0] && innerBlocks[0].innerBlocks.length ) {
					// A quote or gallery, empty or not.
					empty = false;
				}

				updateEmpty( empty );
			}, [ innerBlocks, updateEmpty ] );

			var placeholderProps = {
				icon: 'update-alt',
				label: __( 'Repost', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ! url || 'undefined' === url ) {
				placeholderProps.instructions = __( 'Add a URL and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' );
			}

			var titleProps = {
				label: __( 'Title', 'indieblocks' ),
				value: title,
				onChange: ( value ) => { props.setAttributes( { title: value } ) },
			};

			if ( ! customTitle ) {
				titleProps.readOnly = 'readonly';
			}

			var authorProps = {
				label: __( 'Author', 'indieblocks' ),
				value: author,
				onChange: ( value ) => { props.setAttributes( { author: value } ) },
			};

			if ( ! customAuthor ) {
				authorProps.readOnly = 'readonly';
			}

			return el( 'div', useBlockProps(),
				[
					el( blockEditor.BlockControls ),
					( props.isSelected || ! url || 'undefined' === url )
						? el( components.Placeholder, placeholderProps,
							[
								el( blockEditor.InspectorControls, { key: 'inspector' },
									el( components.PanelBody, {
											title: __( 'Title and Author' ),
											initialOpen: true,
										},
										el( TextControl, titleProps ),
										el( CheckboxControl, {
											label: __( 'Customize title', 'indieblocks' ),
											checked: customTitle,
											onChange: ( value ) => { props.setAttributes( { customTitle: value } ) },
										} ),
										el( TextControl, authorProps ),
										el( CheckboxControl, {
											label: __( 'Customize author', 'indieblocks' ),
											checked: customAuthor,
											onChange: ( value ) => { props.setAttributes( { customAuthor: value } ) },
										} ),
									),
								),
								el( TextControl, {
									label: __( 'URL', 'indieblocks' ),
									value: url,
									onChange: ( value ) => { props.setAttributes( { url: value } ) },
									onKeyDown: ( event ) => {
										if ( 13 === event.keyCode ) {
											IndieBlocks.updateMeta( props, apiFetch );
										}
									},
									onBlur: () => { IndieBlocks.updateMeta( props, apiFetch ) },
								} ),
							]
						)
						: hCite( props.attributes ),
					el( 'blockquote', { className: 'wp-block-quote e-content' },
						el( InnerBlocks, {
							template: [ [ 'core/paragraph' ] ],
							templateLock: false,
						} ), // Always **show** (editable) `InnerBlocks`.
					),
				]
			);
		},
		save: ( props ) => el( 'div', useBlockProps.save(),
			( ! props.attributes.url || 'undefined' === props.attributes.url )
				? null // Can't do much without a URL.
				: hCite( props.attributes, InnerBlocks.Content )
		),
		transforms: {
			to: [
				{
					type: 'block',
					blocks: [ 'core/group' ],
					transform: ( attributes, innerBlocks ) => {
						return createBlock(
							'core/group',
							attributes,
							[
								createBlock( 'core/html', { content: element.renderToString( hCite( attributes ) ) } ),
								createBlock( 'core/quote', { className: 'e-content' }, innerBlocks ),
							]
						);
					},
				},
			],
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.apiFetch, window.wp.i18n, window.IndieBlocks );
