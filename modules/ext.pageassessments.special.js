( function ( $, mw, OO ) {

	/**
	 * Page title autocompletion.
	 */
	$( 'input[name="page_title"]' ).suggestions( {
		fetch: function ( userInput, response, maxRows ) {
			var apiParams, request,
				node = this[ 0 ],
				namespace = OO.ui.infuse( 'pageassessments-namespace' ),
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
	$( 'input[name="project"]' ).suggestions( {
		fetch: function ( userInput, response, maxRows ) {
			var projects = [];
			$.each( mw.config.get( 'wgPageAssessmentProjects' ), function ( index, value ) {
				if ( value.substring( 0, userInput.length ).toLocaleLowerCase() === userInput.toLocaleLowerCase() ) {
					projects.push( value );
				}
			} );
			response( projects.slice( 0, maxRows ) );
		}
	} );

} )( jQuery, mediaWiki, OO );