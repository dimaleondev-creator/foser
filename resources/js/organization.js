const tree = document.querySelector('[data-organization-tree]');

if (tree) {
    tree.querySelectorAll('[data-organization-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('[data-organization-item]');
            const expanded = item.classList.toggle('is-expanded');
            button.setAttribute('aria-expanded', String(expanded));
        });
    });
}
