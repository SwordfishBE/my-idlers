@props([
    'selector',
    'nonOrderable' => [],
    'searchPlaceholder' => 'Search...',
    'emptyTable' => 'No records found',
    'pageLength' => 15,
    'lengthMenu' => [5, 10, 15, 25, 50, 100],
    'order' => null,
])

<script>
    window.addEventListener('load', function () {
        DataTable.ext.errMode = 'none';

        const selector = @json($selector);
        const options = {
            pageLength: @json($pageLength),
            lengthMenu: @json($lengthMenu),
            language: {
                search: "",
                searchPlaceholder: @json($searchPlaceholder),
                lengthMenu: "Show _MENU_",
                info: "Showing _START_ to _END_ of _TOTAL_",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                },
                emptyTable: @json($emptyTable)
            }
        };

        @if(!empty($nonOrderable))
        options.columnDefs = [{
            orderable: false,
            targets: @json(array_values($nonOrderable))
        }];
        @endif

        @if(!is_null($order))
        options.order = @json($order);
        @endif

        if (document.querySelector(selector)) {
            new DataTable(selector, options);
        }
    });
</script>
