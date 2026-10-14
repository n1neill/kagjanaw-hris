import Alpine from 'alpinejs';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';

window.Alpine = Alpine;
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
            search: 'Search:',
            emptyTable: table.dataset.empty ?? 'No records found.',
        },
    });
});
