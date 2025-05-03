<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class AnalyticsDashboard extends Component
{
    public function render()
    {
        // For now, just pass static/random data to the view
        return view('livewire.analytics-dashboard');
    }
}
