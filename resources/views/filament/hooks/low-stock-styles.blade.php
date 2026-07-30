<style>
    /*
     * Filament list tables use <tr class="fi-ta-row"> (not .fi-ta-record).
     * Paint both the row and cells so the background always shows.
     */
    tr.fi-ta-row.fi-ta-record-low-stock,
    tr.fi-ta-row.fi-ta-record-low-stock > td.fi-ta-cell,
    tr.fi-ta-row.fi-ta-record-low-stock:hover,
    tr.fi-ta-row.fi-ta-record-low-stock:hover > td.fi-ta-cell,
    .fi-ta-record.fi-ta-record-low-stock {
        background-color: rgb(254 226 226) !important; /* red-100 */
    }

    .dark tr.fi-ta-row.fi-ta-record-low-stock,
    .dark tr.fi-ta-row.fi-ta-record-low-stock > td.fi-ta-cell,
    .dark tr.fi-ta-row.fi-ta-record-low-stock:hover,
    .dark tr.fi-ta-row.fi-ta-record-low-stock:hover > td.fi-ta-cell,
    .dark .fi-ta-record.fi-ta-record-low-stock {
        background-color: rgb(127 29 29 / 0.45) !important;
    }
</style>
