@extends('layouts.app')

@section('content')
<div class="container page-container">
    <div class="content-card content-card-body text-center" style="max-width: 500px; margin: 4rem auto;">
        <h1 class="page-title" style="color: var(--color-danger);">Table Closed</h1>
        <p style="color: var(--color-accents); font-size: var(--text-lg);">
            @if(isset($table) && $table)
                Table #{{ $table->id }} is currently closed and cannot accept new orders.<br>
                Please contact a staff member if you believe this is an error.
            @else
                This table is not available.
            @endif
        </p>
        <a href="/" class="page-link" style="margin-top: var(--spacing-6); display: inline-block;">Return to Home</a>
    </div>
</div>
@endsection
