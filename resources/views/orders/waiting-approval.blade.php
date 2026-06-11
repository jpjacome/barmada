@extends('layouts.app')

@section('content')
<div class="container page-container">
    <div class="content-card content-card-body text-center" style="max-width: 500px; margin: 4rem auto;">
        <h1 class="page-title" style="color: var(--color-primary);">{{ __('Table Request Pending') }}</h1>
        <p style="color: var(--color-accents); font-size: var(--text-lg);">
            @if(isset($table) && $table)
                {{ __('Thank you for choosing our service.') }}<br>
                {!! __('Your request to open Table #:number has been received.', ['number' => '<strong>'.e($table->table_number ?? $table->id).'</strong>']) !!}<br>
                {{ __('Please wait a moment.') }}<br>
                {{ __('You will be redirected to the order page as soon as your table is ready.') }}
            @else
                {{ __('This table is not available.') }}
            @endif
        </p>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tableId = @json(isset($table) && $table ? $table->id : null);
        var pollInterval = 3000; // 3 seconds
        if (!tableId) {
            return; // No table context (e.g. direct visit): nothing to poll.
        }
        function pollStatus() {
            fetch('{{ url("/poll-table-status") }}/' + tableId)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'open' && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        setTimeout(pollStatus, pollInterval);
                    }
                })
                .catch(error => {
                    setTimeout(pollStatus, pollInterval);
                });
        }
        pollStatus();
    });
</script>
@endsection
