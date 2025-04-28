<x-app-layout>
    <div class="container page-container">
        <div class="content-card content-card-body text-center" style="max-width: 500px; margin: 4rem auto;">
            <h1 class="page-title" style="color: var(--color-primary);">Table Request Pending</h1>
            <p style="color: var(--color-accents); font-size: var(--text-lg);">
                @if(isset($table) && $table)
                    Thank you for choosing our service.<br>
                    Your request to open <strong>Table #{{ $table->id }}</strong> has been received.<br>
                    Please wait a moment while our staff prepares your table.<br>
                    You will be redirected to the order page as soon as your table is ready.
                @else
                    This table is not available.
                @endif
            </p>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tableId = @json($table->id);
            var pollInterval = 3000; // 3 seconds
            function pollStatus() {
                fetch('{{ url("/poll-table-status") }}/' + tableId)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Poll response:', data); // Debug log
                        if (data.status === 'open') {
                            // Try redirect_url first, fall back to default order route
                            const redirectUrl = data.redirect_url || '{{ url("/order") }}/' + tableId;
                            window.location.href = redirectUrl;
                        } else {
                            setTimeout(pollStatus, pollInterval);
                        }
                    })
                    .catch(error => {
                        console.error('Polling error:', error);
                        setTimeout(pollStatus, pollInterval);
                    });
            }
            pollStatus();
        });
    </script>
</x-app-layout>
