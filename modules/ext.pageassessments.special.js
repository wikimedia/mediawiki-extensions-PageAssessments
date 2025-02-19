( function () {

	/**
	 * Page title autocompletion.
	 */
	// eslint-disable-next-line no-jquery/no-global-selector
	$( 'input[name="page_title"]' ).suggestions( {
		fetch: function ( userInput, response, maxRows ) {
			const node = this[ 0 ],
				// eslint-disable-next-line no-jquery/no-global-selector
				namespace = OO.ui.infuse( $( '#pageassessments-namespace' ) ),
				api = new mw.Api();
			const apiParams = {
				action: 'opensearch',
				namespace: namespace.getValue(),
				search: userInput,
				limit: maxRows
			};
			const request = api.get( apiParams )
				.done( ( data ) => {
					response( data[ 1 ] );
				} );
			$.data( node, 'request', request );
		},
		cancel: function () {
			const node = this[ 0 ],
				request = $.data( node, 'request' );
			if ( request ) {
				request.abort();
				$.removeData( node, 'request' );
			}
		}
	} );

	/**
	 * Project name autocompletion.
	 */
	// eslint-disable-next-line no-jquery/no-global-selector
	$( 'input[name="project"]' ).suggestions( {
		fetch: function ( userInput, response, maxRows ) {
			const allProjects = mw.config.get( 'wgPageAssessmentProjects' ) || [];

			const matchingProjects = allProjects.filter( ( value ) => value.slice( 0, Math.max( 0, userInput.length ) ).toLocaleLowerCase() === userInput.toLocaleLowerCase() );

			response( matchingProjects.slice( 0, maxRows ) );
		}
	} );

}() );
