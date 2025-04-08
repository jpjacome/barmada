<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Number;
use Livewire\Attributes\On;

class NumbersList extends Component
{
    public $numbers = [];
    public $lastUpdated;
    public $status = 'Initializing...';
    public $refreshInterval = 3; // in seconds

    public function mount()
    {
        $this->loadNumbers();
    }

    public function loadNumbers()
    {
        $this->numbers = Number::latest('id')->get();
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Updated at ' . $this->lastUpdated;
    }

    #[On('refresh-numbers')]
    public function refreshNumbers()
    {
        $this->loadNumbers();
    }

    public function checkForNewNumbers()
    {
        $this->status = 'Checking for new numbers...';
        $this->dispatch('refresh-numbers');
    }

    public function render()
    {
        return view('livewire.numbers-list');
    }
}
