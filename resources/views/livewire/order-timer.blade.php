<div class="chronometer {{ $isWarning ? 'chronometer-warning' : '' }}" wire:poll.1s="updateTimer">
    {{ $elapsedTime }}
</div>
