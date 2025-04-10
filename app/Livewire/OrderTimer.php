<?php

namespace App\Livewire;

use Livewire\Component;
use Carbon\Carbon;

class OrderTimer extends Component
{
    public $createdAt;
    public $status;
    public $elapsedTime = '00:00:00';
    public $isWarning = false;

    public function mount($createdAt, $status)
    {
        $this->createdAt = $createdAt;
        $this->status = $status;
        $this->updateTimer();
    }

    public function updateTimer()
    {
        if ($this->status === 'pending') {
            $now = now();
            $created = Carbon::parse($this->createdAt);
            $diff = $now->diff($created);
            
            $this->elapsedTime = sprintf(
                '%02d:%02d:%02d',
                $diff->h,
                $diff->i,
                $diff->s
            );
            
            $totalMinutes = ($diff->h * 60) + $diff->i;
            $this->isWarning = $totalMinutes >= 5;
        } else {
            $this->elapsedTime = '00:00:00';
            $this->isWarning = false;
        }
    }

    public function updatedStatus($value)
    {
        $this->updateTimer();
    }

    public function render()
    {
        return view('livewire.order-timer', [
            'elapsedTime' => $this->elapsedTime,
            'isWarning' => $this->isWarning
        ]);
    }
}
