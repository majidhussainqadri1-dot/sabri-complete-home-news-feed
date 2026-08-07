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
		var workflowTarget = document.getElementById( 'sabri-news-target_state' );
		var composerForm = workflowTarget ? workflowTarget.closest( 'form' ) : null;
		var initialStateInput = composerForm ? composerForm.querySelector( 'input[name="initial_state"]' ) : null;
		var initialWorkflowState = initialStateInput ? initialStateInput.value : ( workflowTarget ? workflowTarget.value : '' );
		var frame;

		function replacePreviewImage( url ) {
			if ( ! preview ) {
				return;
			}
			while ( preview.firstChild ) {
				preview.removeChild( preview.firstChild );
			}
			if ( ! url ) {
				return;
			}
			var image = document.createElement( 'img' );
			image.src = String( url );
			image.alt = '';
			preview.appendChild( image );
		}

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
					var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
					replacePreviewImage( previewUrl );
				} );
				frame.open();
			} );
		}

		if ( removeButton && imageInput && preview ) {
			removeButton.addEventListener( 'click', function () {
				imageInput.value = '0';
				replacePreviewImage( '' );
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

		if ( composerForm && workflowTarget ) {
			composerForm.addEventListener( 'submit', function ( event ) {
				var oldConfirmation = composerForm.querySelector( 'input[name="transition_confirmed"]' );
				if ( oldConfirmation ) {
					oldConfirmation.remove();
				}
				if ( workflowTarget.value && workflowTarget.value !== initialWorkflowState ) {
					var baseMessage = window.SabriNewsroomComposer && window.SabriNewsroomComposer.transitionConfirmation ? window.SabriNewsroomComposer.transitionConfirmation : 'Confirm this Editorial News workflow change.';
					var confirmed = window.confirm( baseMessage + '\n\n' + initialWorkflowState + ' → ' + workflowTarget.value );
					if ( ! confirmed ) {
						event.preventDefault();
						workflowTarget.focus();
						return;
					}
					var confirmation = document.createElement( 'input' );
					confirmation.type = 'hidden';
					confirmation.name = 'transition_confirmed';
					confirmation.value = '1';
					composerForm.appendChild( confirmation );
				}
			} );
		}
	} );
}() );
