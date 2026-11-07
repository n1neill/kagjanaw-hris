import Alpine from 'alpinejs';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';

window.Alpine = Alpine;

/**
 * State of the signed-in app shell: the mobile navigation drawer and the
 * desktop sidebar, which collapses to an icon rail. The collapsed choice is
 * remembered in this browser only; the page works when storage is blocked.
 */
Alpine.data('appShell', () => ({
    sidebarOpen: false,
    sidebarCollapsed: false,
    sidebarReady: false,

    init() {
        try {
            this.sidebarCollapsed = localStorage.getItem('sidebar-collapsed') === '1';
        } catch (error) {
            // Storage is unavailable; start expanded.
        }

        this.$watch('sidebarCollapsed', (collapsed) => {
            try {
                localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
            } catch (error) {
                // The choice just won't be remembered.
            }
        });

        // Enable width transitions only after the first paint so a restored
        // collapsed state does not animate on page load.
        requestAnimationFrame(() => {
            this.sidebarReady = true;
        });
    },
}));

Alpine.start();

/**
 * Ask before submitting a <form data-confirm="Question?">. Inline onsubmit
 * handlers are blocked by the Content Security Policy.
 */
document.addEventListener('submit', (event) => {
    const question = event.target.dataset?.confirm;

    if (question && !window.confirm(question)) {
        event.preventDefault();
    }
});

/**
 * Chart.js is loaded only on pages that have a chart (the dashboards).
 */
if (document.querySelector('canvas[data-chart]')) {
    import('./charts');
}

/**
 * Enhance every <table data-datatable> with search, sorting and paging.
 * Columns opt out of sorting with data-orderable="false" on their <th>.
 */
document.querySelectorAll('table[data-datatable]').forEach((table) => {
    new DataTable(table, {
        pageLength: 25,
        order: [],
        language: {
            search: '',
            searchPlaceholder: 'Search…',
            emptyTable: table.dataset.empty ?? 'No records found.',
        },
    });
});
