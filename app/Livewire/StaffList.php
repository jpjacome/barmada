<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class StaffList extends Component
{
    use AuthorizesRequests;

    public $staff = [];
    public $showStaffModal = false;
    public $editMode = false;
    public $name = '';
    public $email = '';
    public $password = '';
    public $staffId = null;

    public function mount()
    {
        $this->authorize('createStaff', User::class);
        $this->loadStaff();
    }

    public function loadStaff()
    {
        $this->staff = $this->ownStaff()
            ->orderBy('name')
            ->get();
    }

    public function addStaff()
    {
        $this->authorize('createStaff', User::class);
        $this->resetForm();
        $this->editMode = false;
        $this->showStaffModal = true;
    }

    public function editStaff($id)
    {
        $user = $this->ownStaff()->findOrFail($id);
        $this->authorize('manageStaff', $user);

        $this->resetForm();
        $this->editMode = true;
        $this->staffId = $user->id;
        $this->name = $user->first_name ?: $user->name;
        $this->email = $user->email;
        $this->showStaffModal = true;
    }

    public function saveStaff()
    {
        $editor = auth()->user();

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->editMode && $this->staffId ? (',' . $this->staffId) : ''),
            'password' => $this->editMode ? 'nullable|min:6' : 'required|min:6',
        ]);

        if ($this->editMode && $this->staffId) {
            // Lookup is constrained to the editor's own staff; anything
            // else 404s before any attribute is touched.
            $user = $this->ownStaff()->findOrFail($this->staffId);
            $this->authorize('manageStaff', $user);

            app(\App\Actions\Staff\SaveStaffMember::class)->handle($editor, [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ], $user);
        } else {
            $this->authorize('createStaff', User::class);

            app(\App\Actions\Staff\SaveStaffMember::class)->handle($editor, [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);
        }

        $this->closeModal();
        $this->loadStaff();
    }

    public function confirmDelete($id)
    {
        $user = $this->ownStaff()->findOrFail($id);
        $this->authorize('manageStaff', $user);

        $this->staffId = $user->id;
        $this->dispatch('showDeleteConfirmation', [
            'message' => "Are you sure you want to delete staff member '{$user->name}'?"
        ]);
    }

    public function deleteConfirmed()
    {
        if ($this->staffId) {
            $user = $this->ownStaff()->find($this->staffId);

            if ($user) {
                $this->authorize('manageStaff', $user);
                $user->delete();
            }

            $this->staffId = null;
            $this->loadStaff();
        }
    }

    public function closeModal()
    {
        $this->showStaffModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->staffId = null;
    }

    /**
     * Staff accounts belonging to the authenticated editor's tenant only.
     */
    private function ownStaff()
    {
        return User::where('is_staff', true)
            ->where('editor_id', auth()->id());
    }

    public function render()
    {
        return view('livewire.staff-list');
    }
}
