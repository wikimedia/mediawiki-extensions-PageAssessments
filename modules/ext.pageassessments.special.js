( function () {

	/**
	 * Page title autocompletion.
	 */
	// eslint-disable-next-line no-jquery/no-global-selector
	$( 'input[name="page_title"]' ).suggestions( {
		fetch: function ( userInput, response, maxRows ) {
			var apiParams, request,
				node = this[ 0 ],
				// eslint-disable-next-line no-jquery/no-global-selector
				namespace = OO.ui.infuse( $( '#pageassessments-namespace' ) ),
				api = new mw.Api();
			apiParams = {
				action: 'opensearch',
				namespace: namespace.getValue(),
				search: userInput,
				limit: maxRows
			};
			request = api.get( apiParams )
				.done( function ( data ) {
					response( data[ 1 ] );
				} );
			$.data( node, 'request', request );
		},
		cancel: function () {
			var node = this[ 0 ],
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
			var matchingProjects,
				allProjects = mw.config.get( 'wgPageAssessmentProjects' ) || [];

			matchingProjects = allProjects.filter( function ( value ) {
				return value.substring( 0, userInput.length ).toLocaleLowerCase() === userInput.toLocaleLowerCase();
			} );

			response( matchingProjects.slice( 0, maxRows ) );
		}
	} );

}() );
