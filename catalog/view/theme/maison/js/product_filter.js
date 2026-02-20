/**
 * Product Filter - Optional AJAX layer
 * Intercepts filter link clicks, fetches page via AJAX, updates products block without full reload.
 * Fallback: without JS, links work normally (full page load).
 */
(function () {
	'use strict';

	var targetEl = document.getElementById('category-products-grid');
	var headerEl = document.getElementById('category-products-header');
	if (!targetEl) return;

	// All filter links and filter select changes
	function init() {
		var aside = document.querySelector('aside');
		if (!aside) return;

		// Intercept clicks on filter links (discrete values)
		// All links inside aside are filter links, even when removing filters (URL may not contain filter_)
		aside.addEventListener('click', function (e) {
			// Handle toggle filter links specifically
			var toggleLink = e.target.closest('a.toggle-filter-link');
			if (toggleLink && toggleLink.href) {
				var href = toggleLink.getAttribute('href');
				if (href && href !== '#') {
					e.preventDefault();
					e.stopPropagation();
					loadUrl(href);
					return false;
				}
			}
			
			var a = e.target.closest('a');
			if (a && a.href && aside.contains(a)) {
				var href = a.getAttribute('href');
				if (!href) return;
				
				// Skip anchors and javascript links
				if (href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) return;
				
				// Check if it's an external link
				var isExternal = false;
				try {
					if (href.indexOf('http://') === 0 || href.indexOf('https://') === 0) {
						var linkHost = new URL(href).hostname;
						var currentHost = window.location.hostname;
						isExternal = linkHost !== currentHost;
					}
				} catch (e) {
					// If URL parsing fails, assume it's relative (internal)
					isExternal = false;
				}
				
				// Intercept all internal links inside aside (they are all filter links)
				if (!isExternal) {
					e.preventDefault();
					e.stopPropagation();
					loadUrl(href);
					return false;
				}
			}
		});

		// Intercept filter select change - use event delegation for dynamically added selects
		aside.addEventListener('change', function (e) {
			if (e.target.classList.contains('product-filter-select')) {
				var url = e.target.value;
				if (url) {
					e.preventDefault();
					e.stopPropagation();
					loadUrl(url);
					return false;
				}
			}
		});

		// Intercept filter form submit (price) - use event delegation
		aside.addEventListener('submit', function (e) {
			if (e.target.classList.contains('product-filter-form')) {
				e.preventDefault();
				e.stopPropagation();
				var form = e.target;
				var qs = new URLSearchParams(new FormData(form)).toString();
				var action = form.getAttribute('action') || form.action || '';
				var sep = action.indexOf('?') >= 0 ? '&' : '?';
				loadUrl(action + sep + qs);
				return false;
			}
		});

		// Handle back/forward
		window.addEventListener('popstate', function () {
			if (window.location.pathname.indexOf('product/category') !== -1 || window.location.search.indexOf('path=') !== -1) {
				loadUrl(window.location.href, true);
			}
		});

		// Initialize price filter with debounce
		initPriceFilter();
	}

	/**
	 * Initialize price filter with auto-apply on input change (debounced)
	 */
	function initPriceFilter() {
		var forms = document.querySelectorAll('.product-filter-form');
		if (!forms || forms.length === 0) return;

		forms.forEach(function(form) {
			var slider = form.querySelector('.filter-price-slider');
			var minInput = form.querySelector('input[name="filter_price_min"]');
			var maxInput = form.querySelector('input[name="filter_price_max"]');
			var maxDisplay = form.querySelector('.filter-price-display-max');
			
			if (!minInput || !maxInput) return;
			
			// Debounce timer per form
			var debounceTimer = null;
			function debounceApplyFilter(callback, delay) {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(callback, delay);
			}
			
			// Function to apply price filter
			function applyPriceFilter() {
				if (typeof window.loadUrl === 'function') {
					var formData = new FormData(form);
					var qs = new URLSearchParams(formData).toString();
					var action = form.getAttribute('action') || form.action || '';
					var sep = action.indexOf('?') >= 0 ? '&' : '?';
					var url = action + sep + qs;
					window.loadUrl(url);
				} else {
					// Fallback: dispatch submit event
					var submitEvent = new Event('submit', {
						bubbles: true,
						cancelable: true
					});
					form.dispatchEvent(submitEvent);
				}
			}
			
			// Update slider min attribute when minInput changes
			if (minInput) {
				minInput.addEventListener('input', function() {
					var minVal = parseFloat(this.value) || parseFloat(this.placeholder) || 0;
					if (slider) {
						slider.min = minVal;
						// If slider value is less than new minimum, set to minimum
						if (parseFloat(slider.value) < minVal) {
							slider.value = minVal;
							maxInput.value = Math.round(minVal);
							if (maxDisplay) {
								maxDisplay.textContent = Math.round(minVal);
							}
						}
					}
					
					// Apply filter with debounce
					debounceApplyFilter(applyPriceFilter, 800);
				});
				
				// Set initial min value for slider
				if (slider) {
					var initialMin = parseFloat(minInput.value) || parseFloat(minInput.placeholder) || 0;
					slider.min = initialMin;
				}
			}
			
			// Handle maxInput changes
			if (maxInput) {
				maxInput.addEventListener('input', function() {
					// Apply filter with debounce
					debounceApplyFilter(applyPriceFilter, 800);
				});
			}
			
			// Sync slider with inputs
			if (slider) {
				slider.addEventListener('input', function() {
					var val = Math.round(parseFloat(this.value));
					var minVal = parseFloat(minInput.value) || parseFloat(minInput.placeholder) || 0;
					
					// If slider value is less than minimum, set to minimum
					if (val < minVal) {
						val = minVal;
						this.value = val;
					}
					
					maxInput.value = val;
					if (maxDisplay) {
						maxDisplay.textContent = val;
					}
					
					// Apply filter with debounce
					debounceApplyFilter(applyPriceFilter, 800);
				});
			}
		});
	}

	window.loadUrl = function loadUrl(url, noPush) {
		// Convert relative URLs to absolute
		if (url.indexOf('http') !== 0) {
			var a = document.createElement('a');
			a.href = url;
			url = a.href;
		}
		
		// Show loading animation
		if (targetEl) {
			targetEl.classList.add('loading');
		}
		
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) return;
			
			// Hide loading animation
			if (targetEl) {
				setTimeout(function() {
					targetEl.classList.remove('loading');
				}, 500);
			}
			
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var parser = new DOMParser();
					var doc = parser.parseFromString(xhr.responseText, 'text/html');
					var newGrid = doc.getElementById('category-products-grid');
					var newHeader = doc.getElementById('category-products-header');
					
					if (newGrid && targetEl) {
						// Preserve loading overlay structure
						var loadingOverlay = targetEl.querySelector('#category-products-loading');
						
						// Update only the grid content (preserve loading overlay)
						var gridContent = newGrid.querySelector('.grid');
						if (gridContent) {
							// Products exist - update only grid content
							var currentGrid = targetEl.querySelector('.grid');
							if (currentGrid) {
								currentGrid.innerHTML = gridContent.innerHTML;
							} else {
								// Grid doesn't exist yet - replace entire content and add overlay
								targetEl.innerHTML = newGrid.innerHTML;
								if (!targetEl.querySelector('#category-products-loading')) {
									var overlay = document.createElement('div');
									overlay.id = 'category-products-loading';
									overlay.innerHTML = '<div id="category-products-loading-spinner"></div>';
									targetEl.insertBefore(overlay, targetEl.firstChild);
								}
							}
						} else {
							// Empty state - no products found, replace entire content
							targetEl.innerHTML = newGrid.innerHTML;
							// Ensure loading overlay exists for future use
							if (!targetEl.querySelector('#category-products-loading')) {
								var overlay = document.createElement('div');
								overlay.id = 'category-products-loading';
								overlay.innerHTML = '<div id="category-products-loading-spinner"></div>';
								targetEl.insertBefore(overlay, targetEl.firstChild);
							}
						}
						
						// Update header (product count and sorting) if it exists
						if (newHeader && headerEl) {
							headerEl.innerHTML = newHeader.innerHTML;
						}
						
						// Update filter section if it exists in new content
						var newFilter = doc.querySelector('aside');
						var currentFilter = document.querySelector('aside');
						if (newFilter && currentFilter) {
							currentFilter.innerHTML = newFilter.innerHTML;
							// Reinitialize price filter after AJAX update
							initPriceFilter();
						}
						
						if (!noPush) {
							history.pushState({ url: url }, '', url);
						}
					} else {
						// Fallback: full page reload if target not found
						window.location.href = url;
					}
				} catch (e) {
					console.error('Error parsing AJAX response:', e);
					window.location.href = url;
				}
			} else {
				// Fallback: full page reload on error
				window.location.href = url;
			}
		};
		xhr.onerror = function () {
			// Hide loading animation on error
			if (targetEl) {
				targetEl.classList.remove('loading');
			}
			window.location.href = url;
		};
		xhr.send();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
