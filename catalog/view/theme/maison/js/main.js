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