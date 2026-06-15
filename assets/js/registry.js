/**
 * Asset Registry front-end browser.
 *
 * Vanilla, dependency-free. Mounts into #asset-registry-app and hydrates from
 * the read-only REST API exposed by the plugin. All REST values are treated as
 * untrusted: the DOM is built with createElement + textContent, never innerHTML.
 */
( function () {
	'use strict';

	var MOUNT_ID = 'asset-registry-app';

	/**
	 * Reads the configuration injected by wp_localize_script, with safe
	 * fallbacks so a partial payload cannot crash the UI.
	 *
	 * @return {object|null} The normalized config, or null if unavailable.
	 */
	function readConfig() {
		if ( typeof window.AssetRegistryData !== 'object' || window.AssetRegistryData === null ) {
			return null;
		}

		var raw = window.AssetRegistryData;
		var i18n = ( raw.i18n && typeof raw.i18n === 'object' ) ? raw.i18n : {};

		return {
			restUrl: typeof raw.restUrl === 'string' ? raw.restUrl : '',
			nonce: typeof raw.nonce === 'string' ? raw.nonce : '',
			canView: raw.canView === true || raw.canView === '1' || raw.canView === 1,
			perPage: ( Number( raw.perPage ) > 0 ) ? Math.floor( Number( raw.perPage ) ) : 12,
			statuses: ( raw.statuses && typeof raw.statuses === 'object' ) ? raw.statuses : {},
			categories: ( raw.categories && typeof raw.categories === 'object' ) ? raw.categories : {},
			i18n: {
				search: i18n.search || 'Search assets',
				allStatuses: i18n.allStatuses || 'All statuses',
				allCategories: i18n.allCategories || 'All categories',
				noResults: i18n.noResults || 'No assets found.',
				loading: i18n.loading || 'Loading...',
				details: i18n.details || 'Details',
				restricted: i18n.restricted || 'Sign in to view full asset details.',
				downloadPdf: i18n.downloadPdf || 'Download PDF',
				downloadFile: i18n.downloadFile || 'Download attachment',
				error: i18n.error || 'Something went wrong. Please try again.',
				close: i18n.close || 'Close',
				prev: i18n.prev || 'Previous',
				next: i18n.next || 'Next',
				pageOf: i18n.pageOf || 'Page %1$s of %2$s'
			}
		};
	}

	/**
	 * Creates an element with optional class and text content. Text is set via
	 * textContent so untrusted strings can never be parsed as markup.
	 *
	 * @param {string} tag       Tag name.
	 * @param {string} className Space-separated class list (optional).
	 * @param {string} text      Text content (optional).
	 * @return {HTMLElement} The created element.
	 */
	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null && text !== '' ) {
			node.textContent = String( text );
		}
		return node;
	}

	/**
	 * Maps a slug to its human label using a slug=>label map, falling back to
	 * the raw slug if the map has no entry.
	 *
	 * @param {object} map  Slug=>label map.
	 * @param {string} slug Candidate slug.
	 * @return {string} The label, or the slug if unmapped.
	 */
	function label( map, slug ) {
		if ( slug && Object.prototype.hasOwnProperty.call( map, slug ) ) {
			return map[ slug ];
		}
		return slug ? String( slug ) : '';
	}

	/**
	 * Simple sprintf for the numbered placeholders used in i18n strings.
	 *
	 * @param {string} template Template containing %1$s, %2$s, ...
	 * @param {Array}  args     Replacement values.
	 * @return {string} The interpolated string.
	 */
	function format( template, args ) {
		return template.replace( /%(\d+)\$s/g, function ( match, index ) {
			var value = args[ Number( index ) - 1 ];
			return value === undefined ? match : String( value );
		} );
	}

	/**
	 * Debounces a function call by the given delay.
	 *
	 * @param {Function} fn    The function to debounce.
	 * @param {number}   delay Delay in milliseconds.
	 * @return {Function} The debounced wrapper.
	 */
	function debounce( fn, delay ) {
		var timer = null;
		return function () {
			var context = this;
			var args = arguments;
			if ( timer ) {
				window.clearTimeout( timer );
			}
			timer = window.setTimeout( function () {
				timer = null;
				fn.apply( context, args );
			}, delay );
		};
	}

	/**
	 * Formats a numeric value to two decimal places, tolerating string input.
	 *
	 * @param {*} value Raw value field.
	 * @return {string} The formatted value, or an empty string when not numeric.
	 */
	function formatValue( value ) {
		if ( value === null || value === undefined || value === '' ) {
			return '';
		}
		var num = Number( value );
		if ( ! Number.isFinite( num ) ) {
			return '';
		}
		return num.toFixed( 2 );
	}

	/**
	 * The application controller. Owns the mount element, current filter/page
	 * state, and all rendering.
	 *
	 * @param {HTMLElement} root   The mount container.
	 * @param {object}      config The normalized config.
	 */
	function App( root, config ) {
		this.root = root;
		this.config = config;
		this.i18n = config.i18n;

		this.state = {
			search: '',
			category: '',
			status: '',
			page: 1,
			totalPages: 1,
			loading: false
		};

		this.nodes = {};
		this.requestToken = 0;
		this.detailToken = 0;
		this.modal = null;
		this.lastFocused = null;

		this.build();
		this.fetchList();
	}

	/**
	 * Builds the static chrome (filter bar, results region, pagination) once.
	 */
	App.prototype.build = function () {
		var self = this;
		var i18n = this.i18n;

		this.root.textContent = '';

		// Filter bar.
		var bar = el( 'div', 'asset-registry__filters' );

		var searchWrap = el( 'div', 'asset-registry__filter asset-registry__filter--search' );
		var search = el( 'input', 'asset-registry__search' );
		search.type = 'search';
		search.placeholder = i18n.search;
		search.setAttribute( 'aria-label', i18n.search );
		var debouncedSearch = debounce( function () {
			self.state.search = search.value.trim();
			self.state.page = 1;
			self.fetchList();
		}, 300 );
		search.addEventListener( 'input', debouncedSearch );
		searchWrap.appendChild( search );
		bar.appendChild( searchWrap );

		var categorySelect = this.buildSelect(
			i18n.allCategories,
			this.config.categories,
			function ( value ) {
				self.state.category = value;
				self.state.page = 1;
				self.fetchList();
			}
		);
		categorySelect.setAttribute( 'aria-label', i18n.allCategories );
		bar.appendChild( this.wrapFilter( categorySelect ) );

		var statusSelect = this.buildSelect(
			i18n.allStatuses,
			this.config.statuses,
			function ( value ) {
				self.state.status = value;
				self.state.page = 1;
				self.fetchList();
			}
		);
		statusSelect.setAttribute( 'aria-label', i18n.allStatuses );
		bar.appendChild( this.wrapFilter( statusSelect ) );

		this.root.appendChild( bar );

		// Live region for status/loading messages.
		var status = el( 'div', 'asset-registry__status' );
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		this.root.appendChild( status );

		// Results grid.
		var results = el( 'div', 'asset-registry__results' );
		this.root.appendChild( results );

		// Pagination.
		var pagination = el( 'nav', 'asset-registry__pagination' );
		pagination.setAttribute( 'aria-label', 'Pagination' );

		var prev = el( 'button', 'asset-registry__page-btn asset-registry__page-btn--prev', i18n.prev );
		prev.type = 'button';
		prev.addEventListener( 'click', function () {
			if ( self.state.page > 1 ) {
				self.state.page -= 1;
				self.fetchList();
			}
		} );

		var pageLabel = el( 'span', 'asset-registry__page-label' );

		var next = el( 'button', 'asset-registry__page-btn asset-registry__page-btn--next', i18n.next );
		next.type = 'button';
		next.addEventListener( 'click', function () {
			if ( self.state.page < self.state.totalPages ) {
				self.state.page += 1;
				self.fetchList();
			}
		} );

		pagination.appendChild( prev );
		pagination.appendChild( pageLabel );
		pagination.appendChild( next );
		this.root.appendChild( pagination );

		this.nodes = {
			search: search,
			status: status,
			results: results,
			pagination: pagination,
			prev: prev,
			next: next,
			pageLabel: pageLabel
		};
	};

	/**
	 * Wraps a control in a filter cell.
	 *
	 * @param {HTMLElement} control The control to wrap.
	 * @return {HTMLElement} The wrapper.
	 */
	App.prototype.wrapFilter = function ( control ) {
		var wrap = el( 'div', 'asset-registry__filter' );
		wrap.appendChild( control );
		return wrap;
	};

	/**
	 * Builds a select with an empty-value "all" option followed by the map.
	 *
	 * @param {string}   allLabel Label for the empty-value option.
	 * @param {object}   map      Slug=>label option map.
	 * @param {Function} onChange Receives the selected value on change.
	 * @return {HTMLSelectElement} The configured select.
	 */
	App.prototype.buildSelect = function ( allLabel, map, onChange ) {
		var select = el( 'select', 'asset-registry__select' );

		var all = el( 'option', null, allLabel );
		all.value = '';
		select.appendChild( all );

		Object.keys( map ).forEach( function ( slug ) {
			var option = el( 'option', null, map[ slug ] );
			option.value = slug;
			select.appendChild( option );
		} );

		select.addEventListener( 'change', function () {
			onChange( select.value );
		} );

		return select;
	};

	/**
	 * Builds the list query string from the current state.
	 *
	 * @return {string} The encoded query string (without leading "?").
	 */
	App.prototype.buildQuery = function () {
		var params = new URLSearchParams();
		params.set( 'page', String( this.state.page ) );
		params.set( 'per_page', String( this.config.perPage ) );
		if ( this.state.search ) {
			params.set( 'search', this.state.search );
		}
		if ( this.state.category ) {
			params.set( 'category', this.state.category );
		}
		if ( this.state.status ) {
			params.set( 'status', this.state.status );
		}
		return params.toString();
	};

	/**
	 * Standard request headers, including the REST nonce for logged-in users.
	 *
	 * @return {object} Header map.
	 */
	App.prototype.headers = function () {
		var headers = { Accept: 'application/json' };
		if ( this.config.nonce ) {
			headers[ 'X-WP-Nonce' ] = this.config.nonce;
		}
		return headers;
	};

	/**
	 * Fetches and renders the current list page. Uses a monotonic request
	 * token so a slow earlier response cannot overwrite a newer one.
	 */
	App.prototype.fetchList = function () {
		var self = this;
		var token = ++this.requestToken;

		this.state.loading = true;
		this.setStatus( this.i18n.loading );
		this.nodes.results.setAttribute( 'aria-busy', 'true' );

		var url = this.config.restUrl + 'assets?' + this.buildQuery();

		window.fetch( url, { headers: this.headers(), credentials: 'same-origin' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				var totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 );
				self.state.totalPages = Number.isFinite( totalPages ) && totalPages > 0 ? totalPages : 1;
				return response.json();
			} )
			.then( function ( items ) {
				if ( token !== self.requestToken ) {
					return;
				}
				self.state.loading = false;
				self.renderList( Array.isArray( items ) ? items : [] );
				self.renderPagination();
			} )
			.catch( function () {
				if ( token !== self.requestToken ) {
					return;
				}
				self.state.loading = false;
				self.nodes.results.setAttribute( 'aria-busy', 'false' );
				self.renderError();
				self.renderPagination();
			} );
	};

	/**
	 * Writes a message into the polite live region.
	 *
	 * @param {string} message The message, or empty to clear.
	 */
	App.prototype.setStatus = function ( message ) {
		this.nodes.status.textContent = message || '';
	};

	/**
	 * Renders the result grid (or the empty state) from a list of items.
	 *
	 * @param {Array} items The REST item array.
	 */
	App.prototype.renderList = function ( items ) {
		var self = this;
		var results = this.nodes.results;
		results.textContent = '';
		results.setAttribute( 'aria-busy', 'false' );

		if ( items.length === 0 ) {
			this.setStatus( '' );
			var empty = el( 'div', 'asset-registry__empty', this.i18n.noResults );
			results.appendChild( empty );
			return;
		}

		this.setStatus( '' );

		var grid = el( 'div', 'asset-registry__grid' );
		items.forEach( function ( item ) {
			grid.appendChild( self.renderCard( item ) );
		} );
		results.appendChild( grid );
	};

	/**
	 * Renders one asset card. The whole card is an activatable button.
	 *
	 * @param {object} item The REST item.
	 * @return {HTMLElement} The card element.
	 */
	App.prototype.renderCard = function ( item ) {
		var self = this;
		var card = el( 'button', 'asset-registry__card' );
		card.type = 'button';

		var head = el( 'div', 'asset-registry__card-head' );
		head.appendChild( el( 'h3', 'asset-registry__card-title', item.name ) );

		var statusSlug = item.status ? String( item.status ) : 'unknown';
		var badge = el(
			'span',
			'asset-registry__badge asset-registry__badge--' + statusSlug,
			label( this.config.statuses, item.status )
		);
		head.appendChild( badge );
		card.appendChild( head );

		card.appendChild(
			el( 'div', 'asset-registry__card-category', label( this.config.categories, item.category ) )
		);

		// Authorized users get a compact secondary line on the card.
		if ( this.config.canView ) {
			var metaParts = [];
			if ( item.location ) {
				metaParts.push( String( item.location ) );
			}
			var value = formatValue( item.value );
			if ( value ) {
				metaParts.push( value );
			}
			if ( metaParts.length ) {
				card.appendChild(
					el( 'div', 'asset-registry__card-meta', metaParts.join( ' · ' ) )
				);
			}
		}

		var cta = el( 'span', 'asset-registry__card-cta', this.i18n.details );
		cta.setAttribute( 'aria-hidden', 'true' );
		card.appendChild( cta );

		var name = item.name ? String( item.name ) : '';
		card.setAttribute( 'aria-label', this.i18n.details + ': ' + name );

		card.addEventListener( 'click', function () {
			self.openDetail( item, card );
		} );

		return card;
	};

	/**
	 * Renders the inline error state into the results region.
	 */
	App.prototype.renderError = function () {
		this.setStatus( '' );
		this.nodes.results.textContent = '';
		var error = el( 'div', 'asset-registry__error', this.i18n.error );
		error.setAttribute( 'role', 'alert' );
		this.nodes.results.appendChild( error );
	};

	/**
	 * Updates the pagination controls from the current page/total state.
	 */
	App.prototype.renderPagination = function () {
		var total = this.state.totalPages;
		var page = this.state.page;

		if ( total <= 1 ) {
			this.nodes.pagination.hidden = true;
			return;
		}

		this.nodes.pagination.hidden = false;
		this.nodes.pageLabel.textContent = format( this.i18n.pageOf, [ page, total ] );

		this.nodes.prev.disabled = page <= 1;
		this.nodes.next.disabled = page >= total;
	};

	/**
	 * Opens the detail modal for an item, fetching the full record first.
	 *
	 * @param {object}      item    The list item (carries the id).
	 * @param {HTMLElement} trigger The element to return focus to on close.
	 */
	App.prototype.openDetail = function ( item, trigger ) {
		var self = this;
		var id = parseInt( item.id, 10 );
		if ( ! Number.isFinite( id ) ) {
			return;
		}

		this.lastFocused = trigger || document.activeElement;
		var token = ++this.detailToken;
		var modal = this.ensureModal();
		this.showModalLoading();

		// True only while this exact open is still the active, visible modal.
		var isCurrent = function () {
			return modal.open && token === self.detailToken;
		};

		window.fetch( this.config.restUrl + 'assets/' + id, {
			headers: this.headers(),
			credentials: 'same-origin'
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( full ) {
				if ( ! isCurrent() ) {
					return;
				}
				self.renderDetail( full && typeof full === 'object' ? full : item );
			} )
			.catch( function () {
				if ( ! isCurrent() ) {
					return;
				}
				self.renderDetailError();
			} );
	};

	/**
	 * Lazily builds the modal dialog and its handlers once.
	 *
	 * @return {object} The modal state record.
	 */
	App.prototype.ensureModal = function () {
		if ( this.modal ) {
			this.openModal();
			return this.modal;
		}

		var self = this;

		var overlay = el( 'div', 'asset-registry-modal' );
		overlay.setAttribute( 'role', 'presentation' );

		var dialog = el( 'div', 'asset-registry-modal__dialog' );
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );

		var titleId = 'asset-registry-modal-title';
		dialog.setAttribute( 'aria-labelledby', titleId );

		var header = el( 'div', 'asset-registry-modal__header' );
		var title = el( 'h2', 'asset-registry-modal__title' );
		title.id = titleId;
		var close = el( 'button', 'asset-registry-modal__close' );
		close.type = 'button';
		close.setAttribute( 'aria-label', this.i18n.close );
		close.textContent = '×';
		close.addEventListener( 'click', function () {
			self.closeModal();
		} );
		header.appendChild( title );
		header.appendChild( close );

		var body = el( 'div', 'asset-registry-modal__body' );

		dialog.appendChild( header );
		dialog.appendChild( body );
		overlay.appendChild( dialog );

		// Backdrop click closes; clicks inside the dialog do not bubble out.
		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				self.closeModal();
			}
		} );

		// Escape to close, Tab to trap focus within the dialog.
		overlay.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				event.stopPropagation();
				self.closeModal();
				return;
			}
			if ( event.key === 'Tab' ) {
				self.trapFocus( event, dialog );
			}
		} );

		document.body.appendChild( overlay );

		this.modal = {
			overlay: overlay,
			dialog: dialog,
			title: title,
			body: body,
			close: close,
			open: false
		};

		this.openModal();
		return this.modal;
	};

	/**
	 * Reveals the modal and moves focus into it.
	 */
	App.prototype.openModal = function () {
		var modal = this.modal;
		// Idempotent: if already open, do not re-capture the padding (that would
		// overwrite the saved original with the compensated value).
		if ( modal.open ) {
			return;
		}
		// Compensate for the scrollbar width BEFORE locking body scroll so the
		// page content does not shift sideways when the scrollbar disappears.
		var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
		this.previousBodyPaddingRight = document.body.style.paddingRight;
		if ( scrollbarWidth > 0 ) {
			document.body.style.paddingRight = scrollbarWidth + 'px';
		}
		modal.overlay.classList.add( 'is-open' );
		modal.open = true;
		document.body.classList.add( 'asset-registry-modal-open' );
		// Defer focus so the dialog is painted before focusing.
		window.setTimeout( function () {
			modal.close.focus();
		}, 0 );
	};

	/**
	 * Hides the modal and restores focus to the triggering element.
	 */
	App.prototype.closeModal = function () {
		if ( ! this.modal || ! this.modal.open ) {
			return;
		}
		this.modal.overlay.classList.remove( 'is-open' );
		this.modal.open = false;
		document.body.classList.remove( 'asset-registry-modal-open' );
		// Restore the original right padding once the scrollbar returns.
		document.body.style.paddingRight = this.previousBodyPaddingRight || '';
		if ( this.lastFocused && typeof this.lastFocused.focus === 'function' ) {
			this.lastFocused.focus();
		}
		this.lastFocused = null;
	};

	/**
	 * Keeps Tab focus cycling within the dialog's focusable elements.
	 *
	 * @param {KeyboardEvent} event  The keydown event.
	 * @param {HTMLElement}   dialog The dialog container.
	 */
	App.prototype.trapFocus = function ( event, dialog ) {
		var focusable = dialog.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		var visible = [];
		for ( var i = 0; i < focusable.length; i++ ) {
			if ( ! focusable[ i ].disabled && focusable[ i ].offsetParent !== null ) {
				visible.push( focusable[ i ] );
			}
		}
		if ( visible.length === 0 ) {
			event.preventDefault();
			return;
		}
		var first = visible[ 0 ];
		var last = visible[ visible.length - 1 ];
		var active = document.activeElement;

		if ( event.shiftKey && active === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && active === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	/**
	 * Shows a loading message in the modal body.
	 */
	App.prototype.showModalLoading = function () {
		this.modal.title.textContent = this.i18n.loading;
		this.modal.body.textContent = '';
		this.modal.body.appendChild( el( 'p', 'asset-registry-modal__loading', this.i18n.loading ) );
	};

	/**
	 * Shows an error message in the modal body.
	 */
	App.prototype.renderDetailError = function () {
		this.modal.title.textContent = this.i18n.error;
		this.modal.body.textContent = '';
		var error = el( 'p', 'asset-registry-modal__error', this.i18n.error );
		error.setAttribute( 'role', 'alert' );
		this.modal.body.appendChild( error );
	};

	/**
	 * Renders the full asset record into the modal. Authorized users see all
	 * labeled fields; anonymous users see the public subset plus a notice.
	 *
	 * @param {object} item The detail item from the REST API.
	 */
	App.prototype.renderDetail = function ( item ) {
		var modal = this.modal;
		modal.title.textContent = item.name ? String( item.name ) : '';
		modal.body.textContent = '';

		var dl = el( 'dl', 'asset-registry-modal__fields' );

		var rows = [
			[ 'Asset Tag', item.asset_tag ],
			[ 'Category', label( this.config.categories, item.category ) ],
			[ 'Status', label( this.config.statuses, item.status ) ],
			[ 'Location', item.location ],
			[ 'Assigned To', item.assigned_to ],
			[ 'Purchase Date', item.purchase_date ],
			[ 'Value', formatValue( item.value ) ],
			[ 'Notes', item.notes ]
		];

		rows.forEach( function ( row ) {
			var value = row[ 1 ];
			if ( value === undefined || value === null || value === '' ) {
				return;
			}
			dl.appendChild( el( 'dt', 'asset-registry-modal__term', row[ 0 ] ) );
			dl.appendChild( el( 'dd', 'asset-registry-modal__desc', value ) );
		} );

		modal.body.appendChild( dl );

		// Gated download links, present only for authorized viewers whose payload
		// carries the per-asset nonced URLs. Built via createElement and
		// setAttribute so untrusted URLs are never parsed as markup.
		var actions = el( 'div', 'asset-registry-modal__actions' );

		if ( typeof item.pdf_url === 'string' && item.pdf_url !== '' ) {
			actions.appendChild( this.downloadLink( item.pdf_url, this.i18n.downloadPdf ) );
		}

		if ( typeof item.file_url === 'string' && item.file_url !== '' ) {
			actions.appendChild( this.downloadLink( item.file_url, this.i18n.downloadFile ) );
		}

		if ( actions.childNodes.length > 0 ) {
			modal.body.appendChild( actions );
		}

		// Anonymous detail carries only name/category/status: show the notice.
		if ( ! this.config.canView ) {
			var note = el( 'p', 'asset-registry-modal__restricted', this.i18n.restricted );
			modal.body.appendChild( note );
		}
	};

	/**
	 * Builds a safe download anchor that opens in a new tab without leaking the
	 * opener. The URL is applied via setAttribute and the label via textContent.
	 *
	 * @param {string} url   The download URL.
	 * @param {string} text  The link label.
	 * @return {HTMLAnchorElement} The configured anchor.
	 */
	App.prototype.downloadLink = function ( url, text ) {
		var link = el( 'a', 'asset-registry-modal__action', text );
		link.setAttribute( 'href', url );
		link.setAttribute( 'target', '_blank' );
		link.setAttribute( 'rel', 'noopener' );
		return link;
	};

	/**
	 * Boots the app when the DOM is ready.
	 */
	function boot() {
		var root = document.getElementById( MOUNT_ID );
		if ( ! root ) {
			return;
		}
		var config = readConfig();
		if ( ! config || ! config.restUrl ) {
			return;
		}
		/* eslint-disable no-new */
		new App( root, config );
		/* eslint-enable no-new */
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
