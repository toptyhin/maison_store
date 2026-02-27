// Mobile Menu Toggle Logic
const appRoot = document.getElementById('app-root');
function toggleMobileMenu() {
    if (appRoot.classList.contains('mobile-menu-closed')) {
        appRoot.classList.remove('mobile-menu-closed');
        appRoot.classList.add('mobile-menu-open');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    } else {
        appRoot.classList.remove('mobile-menu-open');
        appRoot.classList.add('mobile-menu-closed');
        document.body.style.overflow = ''; // Restore scrolling
    }
}


// search autocomplete
(function() {
    var searchAutocompleteUrl = "/index.php?route=product/search/autocomplete";
    if (!searchAutocompleteUrl) return;

    function initHeaderSearch() {
        var inputs = document.querySelectorAll('.header-search-input');
        var dropdowns = document.querySelectorAll('.header-search-dropdown');
        var timers = {};
        var minLength = 2;

        function hideAllDropdowns() {
            dropdowns.forEach(function(el) { el.classList.add('hidden'); el.innerHTML = ''; });
        }

        function showDropdown(dropdownEl, items) {
            dropdownEl.innerHTML = '';
            if (!items.length) { dropdownEl.classList.add('hidden'); return; }
            items.forEach(function(item) {
                var a = document.createElement('a');
                a.href = item.href;
                a.className = 'flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-100 text-left no-underline text-luxury-charcoal';
                a.innerHTML = '<img src="' + (item.image || '') + '" alt="" class="w-10 h-10 object-cover rounded flex-shrink-0" />' +
                    '<span class="truncate flex-1">' + escapeHtml(item.name) + '</span>';
                a.addEventListener('click', function(e) { e.preventDefault(); window.location.href = item.href; });
                dropdownEl.appendChild(a);
            });
            dropdownEl.classList.remove('hidden');
        }

        function escapeHtml(s) {
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function fetchAutocomplete(value, callback) {
            if (value.length < minLength) { callback([]); return; }
            var url = searchAutocompleteUrl + (searchAutocompleteUrl.indexOf('?') !== -1 ? '&' : '?') + 'search=' + encodeURIComponent(value);
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                try { callback(JSON.parse(xhr.responseText) || []); } catch (e) { callback([]); }
            };
            xhr.onerror = function() { callback([]); };
            xhr.send();
        }

        inputs.forEach(function(input, i) {
            var dropdown = document.getElementById(input.id.replace('-input', '-dropdown').replace('-input-mobile', '-dropdown-mobile'));
            if (!dropdown) return;

            input.addEventListener('input', function() {
                var value = (input.value || '').trim();
                clearTimeout(timers[input.id]);
                timers[input.id] = setTimeout(function() {
                    if (value.length < minLength) { hideAllDropdowns(); return; }
                    fetchAutocomplete(value, function(items) {
                        showDropdown(dropdown, items);
                    });
                }, 250);
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') hideAllDropdowns();
            });
        });

        document.addEventListener('click', function(e) {
            var inSearch = e.target.closest('.header-search-wrap, #header-search-form-mobile');
            if (!inSearch) hideAllDropdowns();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHeaderSearch);
    } else {
        initHeaderSearch();
    }
})();

// Product card: add to favorites (wishlist)
(function () {
    var wishlistAddUrl = 'index.php?route=account/wishlist/add';

    function showPopup(text) {
        var overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50';
        var box = document.createElement('div');
        box.className = 'bg-white rounded-lg shadow-xl max-w-md w-full p-6 relative';
        var p = document.createElement('p');
        p.className = 'text-luxury-charcoal text-sm leading-relaxed';
        p.innerHTML = text;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mt-4 w-full py-2 bg-[#263238] text-white text-sm font-bold uppercase tracking-widest rounded-sm hover:bg-[#455A64] transition-colors';
        btn.textContent = 'Закрыть';
        box.appendChild(p);
        box.appendChild(btn);
        overlay.appendChild(box);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === btn) overlay.remove();
        });
        document.body.appendChild(overlay);
    }

    function initProductFavorites() {
        document.querySelectorAll('.product-favorite-btn').forEach(function (btn) {
            if (btn.dataset.favoriteBound) return;
            btn.dataset.favoriteBound = '1';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var productId = this.getAttribute('data-product-id');
                if (!productId) return;
                addToFavorites(productId, this);
            });
        });
    }

    function addToFavorites(productId, btn) {
        btn.disabled = true;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', wishlistAddUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            btn.disabled = false;
            try {
                var json = JSON.parse(xhr.responseText);
                if (json.success) {
                    btn.classList.add('text-discount-red');
                    btn.querySelector('.material-symbols-outlined').style.fontVariationSettings = "'FILL' 1";
                    var totalEl = document.getElementById('wishlist-total');
                    if (totalEl && json.total) {
                        var span = totalEl.querySelector('span');
                        if (span) span.textContent = json.total;
                        totalEl.setAttribute('title', json.total);
                    }
                } else if (json.success === false && json.text) {
                    showPopup(json.text);
                }
            } catch (e) {}
        };
        xhr.onerror = function () { btn.disabled = false; };
        xhr.send('product_id=' + encodeURIComponent(productId));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProductFavorites);
    } else {
        initProductFavorites();
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    // Добавляем fade-in анимацию при загрузке страницы
    const bodyElement = document.body;
    if (bodyElement) {
        // Небольшая задержка для плавной анимации
        setTimeout(function () {
            bodyElement.classList.add('page-loaded');
        }, 50);
    }
});