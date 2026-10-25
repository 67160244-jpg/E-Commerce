/**
 * Mochi Vita - Frontend Main JavaScript
 */
document.addEventListener('DOMContentLoaded', function () {
    // Enable Bootstrap tooltips if any
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Confirmation on deletion or dangerous action
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'คุณแน่ใจหรือไม่ว่าต้องการดำเนินการนี้?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
