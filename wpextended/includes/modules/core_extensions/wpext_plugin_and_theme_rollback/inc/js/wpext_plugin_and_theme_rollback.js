/**
 *  Theme Specific WP Extended Rollback
 *
 *  @description: Adds a wpext rollback option to themes
 *  @copyright: http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
var wpext_vars;
jQuery.noConflict();

(function( $ ) {

	jQuery.fn.contentChange = function( callback ) {
		var elms = jQuery( this );
		elms.each(
			function( i ) {
				var elm = jQuery( this );
				elm.data( 'lastContents', elm.html() );
				window.watchContentChange = window.watchContentChange ? window.watchContentChange : [];
				window.watchContentChange.push( { 'element': elm, 'callback': callback } );
			}
		);
		return elms;
	};
	setInterval( function() {
		if ( window.watchContentChange ) {
			for ( i in window.watchContentChange ) {
				if ( window.watchContentChange[ i ].element.data( 'lastContents' ) !== window.watchContentChange[ i ].element.html() ) {
					window.watchContentChange[ i ].callback.apply( window.watchContentChange[ i ].element );
					window.watchContentChange[ i ].element.data( 'lastContents', window.watchContentChange[ i ].element.html() );
				}

			}
		}
	}, 150 );

	// On DOM Ready
	$( function() {
		var themes;

		themes = wp.themes = wp.themes || {};
		themes.data = typeof _wpThemeSettings !== 'undefined' ? _wpThemeSettings : '';

		// Is only one theme active?
		if( themes.data.themes.length === 1 ) {
			// Show the rollback button.
			wpext_theme_rollback(themes.data.themes[0].id);
		}

		// On clicking a theme template
		$( '.theme-overlay' ).contentChange( function( e ) {

			// get theme name that was clicked
			var clicked_theme = wpext_get_parameter_by_name( 'theme' );

			// check that rollback button hasn't been placed
			if ( is_rollback_btn_there() ) {
				// button is there, bail
				return false;
			}

			// pass off to rollback function
			wpext_theme_rollback( clicked_theme );

		} );

		/**
		 * Check to see if Rollback button is in place
		 *
		 * @returns {boolean}
		 */
		function is_rollback_btn_there() {

			if ( $( '.wpext-theme-rollback' ).length > 0 ) {
				return true;
			}
			return false;

		}
 
		function wpext_theme_rollback( theme ) {
			var theme_data = wpext_get_theme_data( theme );
			// ensure this theme can be rolled back (not premium, etc)
			if ( theme_data !== null && typeof theme_data.hasRollback !== 'undefined' && theme_data.hasRollback !== false ) {

				var active_theme = $( '.theme-overlay' ).hasClass( 'active' );

				var rollback_btn_html = '<a href="' + encodeURI( 'admin.php?page=wp-extended-rollback&type=theme&theme_file=' + theme + '&current_version=' + theme_data.version + '&rollback_name=' + theme_data.name + '&_wpnonce=' + wpext_vars.nonce ) + '" style="position:absolute;right: ' + (active_theme === true ? '5px' : '80px') + '; bottom: 10px;" class="button-secondary wpext-theme-rollback">' + wpext_vars.text_rollback_label + '</a>';

				$( '.theme-wrap' ).find( '.theme-actions' ).append( rollback_btn_html );

			} else {
				// Can't roll back this theme, display the notice.
				$( '.theme-wrap' ).find( '.theme-actions' ).append( '<span class="no-rollback" style="position: absolute;left: 23px;bottom: 16px;font-size: 12px;font-style: italic;color: rgb(181, 181, 181);">' + wpext_vars.text_not_rollbackable + '</span>' );
			}

		}

		/** 
		 * Get Theme Data
		 *
		 * @description Loops through the wp.themes.data.themes object, finds a match, and returns the data
		 * @param theme
		 * @returns {*}
		 */
		function wpext_get_theme_data( theme ) {
			var theme_data = wp.themes.data.themes;
			// Loop through complete theme data to find this current theme's data
			for ( var i = 0, len = theme_data.length; i < len; i ++ ) {
				if ( theme_data[ i ].id === theme ) {
					return theme_data[ i ]; // Return as soon as the object is found
				}
			}
			return null; // The object was not found
		}

		 
		function wpext_get_parameter_by_name( name ) {
			name = name.replace( /[\[]/, '\\[' ).replace( /[\]]/, '\\]' );
			var regex = new RegExp( '[\\?&]' + name + '=([^&#]*)' ),
				results = regex.exec( location.search );
			return results === null ? '' : decodeURIComponent( results[ 1 ].replace( /\+/g, ' ' ) );
		}

		/**
		 * Get Parameter from Focused Theme
		 *
		 * @returns {*}
		 */
		function wpext_get_parameter_from_focused_theme() {
			var focused_theme = wp.themes.focusedTheme;

			var name = $( focused_theme ).find( '.theme-name' ).attr( 'id' );

			if ( typeof name !== 'undefined' ) {
				name = name.replace( '-name', '' );
			} else {
				return false;
			}

			return name;
		}

		/**
		 * Theme Rollback Button Clicked
		 *
		 * Send them over to WP Extended rollback.
		 */
		$( 'body' ).on( 'click', '.wpext-theme-rollback', function( e ) {
			window.location = $( this ).attr( 'href' );
		} );

	} );

})( jQuery );
