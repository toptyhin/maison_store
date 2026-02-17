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