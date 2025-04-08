<div>
    <div class="py-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Numbers (Live Updates with Livewire)</h3>
            <div class="text-sm text-gray-500">{{ $status }}</div>
        </div>
        
        <button wire:click="checkForNewNumbers" class="mb-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Check for New Numbers
        </button>
        
        <div 
            wire:poll.{{ $refreshInterval }}s="refreshNumbers"
            class="space-y-2 mt-4"
        >
            @foreach ($numbers as $number)
                <div 
                    class="p-3 bg-gray-100 rounded hover:bg-gray-200 transition" 
                    wire:key="number-{{ $number->id }}"
                >
                    <span class="font-semibold">{{ $number->value }}</span>
                    <span class="text-gray-500 text-sm ml-2">
                        @if(isset($number->created_at))
                            {{ $number->created_at->diffForHumans() }}
                        @else
                            Just now
                        @endif
                    </span>
                </div>
            @endforeach
            
            @if(count($numbers) === 0)
                <div class="text-gray-500 italic">No numbers found</div>
            @endif
        </div>
    </div>
    
    <div class="mt-4 text-sm text-gray-500">
        <div>Auto-refreshes every {{ $refreshInterval }} seconds.</div>
        <div>Last updated: {{ $lastUpdated }}</div>
    </div>
</div>
