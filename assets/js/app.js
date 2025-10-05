(function () {
    const toggles = document.querySelectorAll('[data-toggle-target]');
    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            const target = document.getElementById(toggle.dataset.toggleTarget);
            if (target) {
                target.classList.toggle('is-open');
            }
        });
    });
})();
