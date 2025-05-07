@extends('layouts.app')

@section('content')
<div class="container page-container">
    <div class="content-card content-card-body text-center" style="max-width: 500px; margin: 4rem auto;">
        <h1 class="page-title" style="color: var(--color-primary);">Solicitud de Mesa Pendiente</h1>
        <p style="color: var(--color-accents); font-size: var(--text-lg);">
            @if(isset($table) && $table)
                Gracias por elegir nuestro servicio.<br>
                Su solicitud para abrir la <strong>Mesa #{{ $table->table_number ?? $table->id }}</strong> ha sido recibida.<br>
                Por favor, espere un momento mientras nuestro personal prepara su mesa.<br>
                Será redirigido a la página de pedidos tan pronto como su mesa esté lista.
            @else
                Esta mesa no está disponible.
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
                    if (data.status === 'open') {
                        // Try redirect_url first, fall back to default order route
                        const redirectUrl = data.redirect_url || '{{ url("/order") }}/' + tableId;
                        window.location.href = redirectUrl;
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
