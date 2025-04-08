<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Table;
use Livewire\Attributes\On;

class TablesList extends Component
{
    public $tables = [];
    public $lastUpdated;
    public $status = 'Loading tables...';
    public $refreshInterval = 10; // in seconds
    
    // Form properties
    public $name = '';
    public $capacity = 4;
    public $showAddForm = false;

    public function mount()
    {
        $this->loadTables();
    }

    public function loadTables()
    {
        $this->tables = Table::orderBy('name')->get();
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Tables updated at ' . $this->lastUpdated;
    }

    #[On('refresh-tables')]
    public function refreshTables()
    {
        $this->loadTables();
    }

    public function toggleAddForm()
    {
        $this->showAddForm = !$this->showAddForm;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->capacity = 4;
    }

    public function addTable()
    {
        $this->validate([
            'name' => 'required|string|max:50|unique:tables,name',
            'capacity' => 'required|integer|min:1|max:20',
        ]);

        Table::create([
            'name' => $this->name,
            'capacity' => $this->capacity,
            'is_occupied' => false,
        ]);

        $this->toggleAddForm();
        $this->status = 'Table added successfully!';
        $this->dispatch('refresh-tables');
    }

    public function toggleTableStatus($tableId)
    {
        $table = Table::findOrFail($tableId);
        $table->is_occupied = !$table->is_occupied;
        $table->save();
        
        $this->status = 'Table status updated!';
        $this->dispatch('refresh-tables');
    }

    public function render()
    {
        return view('livewire.tables-list');
    }
} 