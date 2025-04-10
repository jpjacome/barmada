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
    public $showAddForm = false;

    public function mount()
    {
        $this->loadTables();
    }

    public function loadTables()
    {
        $this->tables = Table::orderBy('id')->get();
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
        // No form fields to reset
    }

    public function addTable()
    {
        // Find the lowest available ID
        $existingIds = Table::orderBy('id')->pluck('id')->toArray();
        $newId = 1;
        
        // Find the first gap in the sequence
        foreach ($existingIds as $index => $id) {
            if ($id != $index + 1) {
                $newId = $index + 1;
                break;
            }
            $newId = count($existingIds) + 1;
        }
        
        // Create the table with the specific ID
        Table::create([
            'id' => $newId,
            'orders' => 0,
        ]);

        $this->toggleAddForm();
        $this->status = 'Table added successfully!';
        $this->dispatch('refresh-tables');
    }

    public function deleteTable($tableId)
    {
        $table = Table::findOrFail($tableId);
        
        // Delete the table regardless of orders
        $table->delete();
        $this->status = 'Table deleted successfully!';
        $this->dispatch('refresh-tables');
    }

    public function render()
    {
        return view('livewire.tables-list');
    }
} 