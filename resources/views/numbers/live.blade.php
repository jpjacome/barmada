<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Live Number Updates') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold mb-4">Numbers (Updates Live)</h3>
                    
                    <button id="manual-fetch" class="mb-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Check for New Numbers
                    </button>
                    
                    <div id="numbers-container" class="space-y-2">
                        @foreach ($numbers as $number)
                            <div class="p-3 bg-gray-100 rounded" data-id="{{ $number->id }}">
                                <span class="font-semibold">{{ $number->value }}</span>
                                <span class="text-gray-500 text-sm ml-2">{{ $number->created_at->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            
            <div class="mt-6">
                <a href="{{ route('numbers.create') }}" class="text-blue-500 hover:underline">
                    Submit a new number
                </a>
                |
                <a href="{{ route('numbers.index') }}" class="text-blue-500 hover:underline">
                    Regular Numbers View
                </a>
                |
                <a href="{{ route('numbers.livewire') }}" class="text-blue-500 hover:underline">
                    Livewire View
                </a>
            </div>
        </div>
    </div>
    
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add a status indicator to the page
            const statusDiv = document.createElement('div');
            statusDiv.id = 'polling-status';
            statusDiv.className = 'mt-4 text-sm text-gray-500';
            statusDiv.textContent = 'Setting up polling...';
            document.querySelector('.p-6').appendChild(statusDiv);
            
            console.log('Setting up polling for number updates');
            
            // Last number ID we've seen (used to determine if there are new numbers)
            let lastNumberId = getMaxNumberId();
            console.log('Initial lastNumberId:', lastNumberId);
            
            // Function to get the maximum ID from current numbers
            function getMaxNumberId() {
                const numberElements = document.querySelectorAll('#numbers-container > div');
                let maxId = 0;
                
                numberElements.forEach(el => {
                    const id = parseInt(el.dataset.id || 0);
                    maxId = Math.max(maxId, id);
                });
                
                console.log('Found max ID:', maxId);
                return maxId;
            }
            
            // Function to fetch new numbers
            async function fetchNewNumbers() {
                try {
                    statusDiv.textContent = 'Checking for new numbers...';
                    console.log('Fetching new numbers after ID:', lastNumberId);
                    
                    const response = await fetch(`/numbers-api.php?after=${lastNumberId}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    console.log('API response:', data);
                    
                    if (data.numbers && data.numbers.length > 0) {
                        console.log(`Found ${data.numbers.length} new numbers`);
                        statusDiv.textContent = `Adding ${data.numbers.length} new numbers...`;
                        
                        const numbersContainer = document.getElementById('numbers-container');
                        
                        // Sort numbers by ID (descending) to ensure newest first
                        data.numbers.sort((a, b) => b.id - a.id);
                        
                        data.numbers.forEach(number => {
                            console.log('Adding number:', number);
                            
                            // Create the new number element
                            const newNumberDiv = document.createElement('div');
                            newNumberDiv.className = 'p-3 bg-gray-100 rounded';
                            newNumberDiv.style.animation = 'fadeIn 1s';
                            newNumberDiv.dataset.id = number.id;
                            
                            // Add the value
                            const valueSpan = document.createElement('span');
                            valueSpan.className = 'font-semibold';
                            valueSpan.textContent = number.value;
                            newNumberDiv.appendChild(valueSpan);
                            
                            // Add the timestamp
                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'text-gray-500 text-sm ml-2';
                            timeSpan.textContent = 'just now';
                            newNumberDiv.appendChild(timeSpan);
                            
                            // Insert at the beginning
                            numbersContainer.insertBefore(newNumberDiv, numbersContainer.firstChild);
                        });
                        
                        // Update the last ID we've seen
                        lastNumberId = data.numbers[0].id;
                        console.log('Updated lastNumberId to:', lastNumberId);
                        
                        statusDiv.textContent = `Last updated: ${new Date().toLocaleTimeString()} - found ${data.numbers.length} new numbers`;
                    } else {
                        console.log('No new numbers found');
                        statusDiv.textContent = `Last checked: ${new Date().toLocaleTimeString()} - no new numbers`;
                    }
                } catch (error) {
                    console.error('Error fetching new numbers:', error);
                    statusDiv.textContent = `Error: ${error.message} - retrying in 3 seconds`;
                }
            }
            
            // Immediately check once
            fetchNewNumbers();
            
            // Set up polling every 3 seconds
            const intervalId = setInterval(fetchNewNumbers, 3000);
            console.log('Polling interval set up with ID:', intervalId);
            
            // Clean up interval when navigating away
            window.addEventListener('beforeunload', function() {
                clearInterval(intervalId);
                console.log('Polling interval cleared');
            });
            
            // Add manual fetch button handler
            document.getElementById('manual-fetch').addEventListener('click', function() {
                console.log('Manual fetch triggered');
                this.textContent = 'Checking...';
                
                // Disable the button temporarily
                this.disabled = true;
                
                fetchNewNumbers().finally(() => {
                    // Re-enable the button and reset text
                    this.disabled = false;
                    this.textContent = 'Check for New Numbers';
                });
            });
        });
    </script>
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    @endpush
</x-app-layout> 