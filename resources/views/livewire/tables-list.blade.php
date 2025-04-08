<div>
    <div class="py-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Tables Management</h3>
            <div class="text-sm text-gray-500">{{ $status }}</div>
        </div>
        
        <!-- Add Table Button -->
        <div class="mb-4">
            <button wire:click="toggleAddForm" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ $showAddForm ? 'Cancel' : 'Add New Table' }}
            </button>
        </div>
        
        <!-- Add Table Form -->
        @if($showAddForm)
        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <h4 class="font-medium mb-2">Add New Table</h4>
            <form wire:submit.prevent="addTable">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Table Name</label>
                        <input type="text" id="name" wire:model="name" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label for="capacity" class="block text-sm font-medium text-gray-700">Capacity</label>
                        <input type="number" id="capacity" wire:model="capacity" min="1" max="20"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('capacity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Save Table
                    </button>
                </div>
            </form>
        </div>
        @endif
        
        <!-- Tables List -->
        <div 
            wire:poll.{{ $refreshInterval }}s="refreshTables"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4"
        >
            @forelse ($tables as $table)
                <div 
                    class="p-4 rounded-lg shadow hover:shadow-md transition-shadow duration-200 
                           {{ $table->is_occupied ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}"
                    wire:key="table-{{ $table->id }}"
                >
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="font-bold">{{ $table->name }}</h4>
                        <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $table->is_occupied ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                            {{ $table->is_occupied ? 'Occupied' : 'Available' }}
                        </span>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-3">
                        <span>Capacity: {{ $table->capacity }} people</span>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button 
                            wire:click="toggleTableStatus({{ $table->id }})"
                            class="text-sm px-3 py-1 rounded
                                {{ $table->is_occupied 
                                    ? 'bg-green-500 hover:bg-green-600 text-white' 
                                    : 'bg-red-500 hover:bg-red-600 text-white' }}"
                        >
                            {{ $table->is_occupied ? 'Mark Available' : 'Mark Occupied' }}
                        </button>
                        
                        <a href="{{ route('dashboard') }}" class="text-sm px-3 py-1 rounded bg-blue-500 hover:bg-blue-600 text-white">
                            View Orders
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-3 p-4 bg-gray-50 rounded text-center text-gray-500 italic">
                    No tables have been added yet. Add your first table to get started!
                </div>
            @endforelse
        </div>
    </div>
    
    <div class="mt-4 text-sm text-gray-500">
        <div>Auto-refreshes every {{ $refreshInterval }} seconds.</div>
        <div>Last updated: {{ $lastUpdated }}</div>
    </div>
</div> 