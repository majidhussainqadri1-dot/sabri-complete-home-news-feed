( function () {
	'use strict';

	function addPublishedOption( selectId, label ) {
		var select = document.getElementById( selectId );
		var option;
		if ( ! select || select.querySelector( 'option[value="published"]' ) ) {
			return;
		}
		option = document.createElement( 'option' );
		option.value = 'published';
		option.textContent = label;
		select.appendChild( option );
	}

	function ready() {
		addPublishedOption( 'sabri-news-target_state', 'Published — visible on public News' );
		addPublishedOption( 'sabri-news-bulk-target', 'Publish publicly' );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
}() );
