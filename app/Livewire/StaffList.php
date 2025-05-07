<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StaffList extends Component
{
    public $staff = [];
    public $showStaffModal = false;
    public $editMode = false;
    public $name = '';
    public $email = '';
    public $password = '';
    public $staffId = null;

    public function mount()
    {
        $this->loadStaff();
    }

    public function loadStaff()
    {
        $editor = auth()->user();
        $this->staff = User::where('is_staff', true)
            ->where('editor_id', $editor->id)
            ->orderBy('name')
            ->get();
    }

    public function addStaff()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showStaffModal = true;
    }

    public function saveStaff()
    {
        $editor = auth()->user();
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->editMode && $this->staffId ? (',' . $this->staffId) : ''),
            'password' => $this->editMode ? 'nullable|min:6' : 'required|min:6',
        ]);

        if ($this->editMode && $this->staffId) {
            $user = User::findOrFail($this->staffId);
            $user->name = $this->name;
            $user->email = $this->email;
            if ($this->password) {
                $user->password = Hash::make($this->password);
            }
            $user->save();
        } else {
            User::create([
                'username' => strtolower(preg_replace('/\s+/', '', $this->name)) . rand(1000, 9999),
                'first_name' => $this->name,
                'last_name' => 'Staff',
                'name' => $this->name . ' Staff',
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'is_staff' => true,
                'editor_id' => $editor->id,
            ]);
        }
        $this->closeModal();
        $this->loadStaff();
    }

    public function confirmDelete($id)
    {
        $this->staffId = $id;
        $user = User::findOrFail($id);
        $this->dispatch('showDeleteConfirmation', [
            'message' => "Are you sure you want to delete staff member '{$user->name}'?"
        ]);
    }

    public function deleteConfirmed()
    {
        if ($this->staffId) {
            User::where('id', $this->staffId)->where('is_staff', true)->delete();
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

    public function render()
    {
        return view('livewire.staff-list');
    }
}
