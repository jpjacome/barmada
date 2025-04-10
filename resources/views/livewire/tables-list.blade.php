<div>
    <div class="py-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Tables Management</h3>
            <div class="text-sm text-gray-500">{{ $status }}</div>
        </div>
        
        <!-- Add Table Button -->
        <div class="mb-4">
            <button wire:click="toggleAddForm" class="bg-blue-500 hover:bg-blue-700 text-black font-bold py-2 px-4 rounded flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $showAddForm ? 'Cancel' : 'Add New Table' }}
            </button>
        </div>
        
        <!-- Add Table Form -->
        @if($showAddForm)
        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <h4 class="font-medium mb-2">Add New Table</h4>
            <div class="text-sm text-gray-600 mb-3">
                Click "Create Table" to add a new table.
            </div>
            <div class="mt-4 flex justify-end">
                <button wire:click="addTable" class="bg-green-500 hover:bg-green-700 text-black font-bold py-2 px-4 rounded">
                    Create Table
                </button>
            </div>
        </div>
        @endif
        
        <!-- Tables List -->
        <div 
            wire:poll.{{ $refreshInterval }}s="refreshTables"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4"
        >
            @forelse ($tables as $table)
                <div 
                    class="p-4 rounded-lg shadow hover:shadow-md transition-shadow duration-200 bg-gray-50 border border-gray-200"
                    wire:key="table-{{ $table->id }}"
                >
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="font-bold">Table {{ $table->id }}</h4>
                        <div class="flex items-center">
                            <button 
                                wire:click="deleteTable({{ $table->id }})" 
                                class="text-red-500 hover:text-red-700"
                                onclick="return confirm('Are you sure you want to delete this table?')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-3">
                        <span>Orders: {{ $table->orders }}</span>
                    </div>
                    
                    <div class="flex space-x-2">
                        <a href="{{ route('orders.index') }}" class="text-sm px-3 py-1 rounded bg-blue-500 hover:bg-blue-600 text-black">
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