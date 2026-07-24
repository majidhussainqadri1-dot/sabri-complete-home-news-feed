( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState !== 'loading' ) {
			callback();
		} else {
			document.addEventListener( 'DOMContentLoaded', callback );
		}
	}

	ready( function () {
		var selectButton = document.getElementById( 'sabri-news-select-image' );
		var removeButton = document.getElementById( 'sabri-news-remove-image' );
		var imageInput = document.getElementById( 'sabri-news-featured_image_id' );
		var preview = document.getElementById( 'sabri-news-featured-preview' );
		var schedule = document.getElementById( 'sabri-news-schedule_at' );
		var utcOutput = document.getElementById( 'sabri-news-schedule-utc' );
		var frame;

		if ( selectButton && imageInput && preview && window.wp && window.wp.media ) {
			selectButton.addEventListener( 'click', function () {
				if ( frame ) {
					frame.open();
					return;
				}
				frame = window.wp.media( {
					title: window.SabriNewsroomComposer.mediaTitle,
					button: { text: window.SabriNewsroomComposer.mediaButton },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					imageInput.value = String( attachment.id || 0 );
					preview.innerHTML = attachment.sizes && attachment.sizes.medium ? '<img src="' + attachment.sizes.medium.url + '" alt="" />' : '<img src="' + attachment.url + '" alt="" />';
				} );
				frame.open();
			} );
		}

		if ( removeButton && imageInput && preview ) {
			removeButton.addEventListener( 'click', function () {
				imageInput.value = '0';
				preview.innerHTML = '';
			} );
		}

		function updateUtcPreview() {
			if ( ! schedule || ! utcOutput ) {
				return;
			}
			var value = schedule.value.trim();
			if ( ! value ) {
				utcOutput.textContent = '';
				return;
			}
			var parsed = new Date( value );
			utcOutput.textContent = Number.isNaN( parsed.getTime() ) ? 'The schedule value must include an explicit timezone offset.' : 'Normalized UTC: ' + parsed.toISOString();
		}

		if ( schedule ) {
			schedule.addEventListener( 'input', updateUtcPreview );
			updateUtcPreview();
		}
	} );
}() );
