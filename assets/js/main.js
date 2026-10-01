// Confirm before any destructive action (delete links/buttons with data-confirm)
document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-confirm]');
    if (el) {
        const msg = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!confirm(msg)) e.preventDefault();
    }
});

// Simple client-side filter for tables with a search box: <input data-table-filter="#tableId">
document.querySelectorAll('[data-table-filter]').forEach(function (input) {
    const table = document.querySelector(input.getAttribute('data-table-filter'));
    if (!table) return;
    input.addEventListener('input', function () {
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
