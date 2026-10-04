import Alpine from 'alpinejs';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';

window.Alpine = Alpine;
Alpine.start();

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
