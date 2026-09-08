@section('header')
    <h1 class="page-title">{{ __('Staff Management') }}</h1>
@endsection
<div class="staff-container">
    <style>
        @import url('{{ asset('css/staff-list.css') }}');
    </style>
    <div class="staff-main">
        <div class="staff-data">
            <div class="staff-table-container">
                <table class="staff-table">
                    <thead class="staff-table-header">
                        <tr>
                            <th class="staff-table-header-cell">{{ __('Name') }}</th>
                            <th class="staff-table-header-cell">{{ __('Email') }}</th>
                            <th class="staff-table-header-cell staff-table-cell-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="staff-table-body">
                        @forelse ($staff as $user)
                            <tr wire:key="staff-{{ $user->id }}" class="staff-row">
                                <td class="staff-cell staff-name-cell">{{ $user->name }}</td>
                                <td class="staff-cell staff-email-cell">{{ $user->email }}</td>
                                <td class="staff-cell staff-actions">
                                    <button wire:click="editStaff({{ $user->id }})" class="staff-edit-button" title="{{ __('Edit Staff') }}" style="background:transparent;border:none;cursor:pointer;margin-right:0.4rem;" aria-label="{{ __('Edit staff') }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </button>
                                    <button wire:click="confirmDelete({{ $user->id }})" class="staff-delete-button" title="{{ __('Delete Staff') }}" aria-label="{{ __('Delete staff') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="staff-empty-message">
                                    {{ __('No staff found. Add your first staff member to get started.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="staff-footer">
            <button wire:click="addStaff" class="staff-add-button">
                <i class="bi bi-plus-circle staff-button-icon"></i> {{ __('Add Staff') }}
            </button>
        </div>
    </div>

    <!-- Staff Modal -->
    @if($showStaffModal)
    <div class="staff-modal-overlay">
        <div class="staff-modal">
            <div class="staff-modal-header">
                <h3 class="staff-modal-title">
                    {{ $editMode ? __('Edit Staff') : __('Add New Staff') }}
                </h3>
            </div>
            <form wire:submit.prevent="saveStaff">
                <div class="staff-modal-body">
                    <div class="staff-form-group">
                        <label for="name" class="staff-form-label">{{ __('Name') }}</label>
                        <input type="text" id="name" wire:model="name" class="staff-form-input" placeholder="{{ __('Enter staff name') }}">
                        @error('name') <span class="staff-form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="staff-form-group">
                        <label for="email" class="staff-form-label">{{ __('Email') }}</label>
                        <input type="email" id="email" wire:model="email" class="staff-form-input" placeholder="{{ __('Enter staff email') }}">
                        @error('email') <span class="staff-form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="staff-form-group">
                        <label for="password" class="staff-form-label">{{ __('Password') }}</label>
                        <input type="password" id="password" wire:model="password" class="staff-form-input"
                               placeholder="{{ $editMode ? __('Leave blank to keep the current password') : __('Set password') }}">
                        @error('password') <span class="staff-form-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="staff-modal-footer">
                    <button type="button" wire:click="closeModal" class="staff-cancel-button">{{ __('Cancel') }}</button>
                    <button type="submit" class="staff-submit-button">{{ $editMode ? __('Update') : __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation - uses JS -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            @this.on('showDeleteConfirmation', (event) => {
                if (confirm(event.message)) {
                    @this.dispatch('deleteConfirmed');
                }
            });
        });
    </script>
</div>
