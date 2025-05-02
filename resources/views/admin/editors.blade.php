@extends('layouts.app')
@section('header')
    <h1 class="page-title">Establishments</h1>
@endsection
@section('content')
<div class="editors-container">
    <link href="{{ asset('css/editors-list.css') }}" rel="stylesheet">
    <div class="editors-main">
        <div class="editors-table-container">
            <table class="editors-table">
                <thead class="editors-table-header">
                    <tr>
                        <th class="editors-table-header-cell">Name</th>
                        <th class="editors-table-header-cell">Email</th>
                        <th class="editors-table-header-cell">Created</th>
                        <th class="editors-table-header-cell editors-table-cell-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="editors-table-body">
                    @forelse ($editors as $editor)
                        <tr class="editor-row">
                            <td class="editor-cell editor-name">{{ $editor->name }}</td>
                            <td class="editor-cell editor-email">{{ $editor->email }}</td>
                            <td class="editor-cell editor-created">{{ $editor->created_at->format('Y-m-d') }}</td>
                            <td class="editor-cell editor-actions">
                                <form method="POST" action="{{ route('admin.impersonate', $editor->id) }}" style="display:inline">
                                    @csrf
                                    <button class="editor-dashboard-button" title="Enter Dashboard">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                    </button>
                                </form>
                                <button class="editor-edit-button" title="Edit Editor">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="editor-delete-button" title="Delete Editor">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="editor-empty-message">
                                No editors found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="editors-pagination">
            {{ $editors->links() }}
        </div>
    </div>
</div>
@endsection

@push('styles')
<!-- The component-specific CSS is loaded above -->
@endpush