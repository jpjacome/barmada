<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Orders Archive
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <style>
                .archive-container {
                    background-color: var(--color-secondary);
                    border-radius: var(--border-radius-md);
                    box-shadow: var(--shadow-md);
                    overflow: hidden;
                    margin-bottom: var(--spacing-6);
                }
                
                .archive-header {
                    background-color: rgba(115, 171, 132, 0.1);
                    padding: var(--spacing-4);
                    border-bottom: var(--border-width) solid var(--color-primary);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .archive-title {
                    font-size: var(--text-lg);
                    font-weight: var(--font-weight-semibold);
                    color: var(--color-accents);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                }
                
                .archive-icon {
                    font-size: var(--text-xl);
                    color: var(--color-success);
                }
                
                .archive-description {
                    margin-top: var(--spacing-2);
                    font-size: var(--text-base);
                    color: var(--color-accents);
                    opacity: 0.7;
                }
                
                .archive-back {
                    display: inline-flex;
                    align-items: center;
                    gap: var(--spacing-2);
                    padding: var(--spacing-2) var(--spacing-4);
                    color: var(--color-accents);
                    background-color: rgba(115, 171, 132, 0.05);
                    border: var(--border-width) solid rgba(115, 171, 132, 0.2);
                    border-radius: var(--border-radius-md);
                    transition: var(--transition);
                    font-weight: var(--font-weight-medium);
                }
                
                .archive-back:hover {
                    background-color: rgba(115, 171, 132, 0.1);
                    border-color: rgba(115, 171, 132, 0.3);
                }
                
                .archive-table-container {
                    padding: var(--spacing-4);
                }
                
                .archive-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .archive-table-head {
                    background-color: rgba(115, 171, 132, 0.05);
                }
                
                .archive-table-head th {
                    padding: var(--spacing-3) var(--spacing-4);
                    text-align: left;
                    font-weight: var(--font-weight-semibold);
                    color: var(--color-accents);
                    border-bottom: var(--border-width) solid rgba(115, 171, 132, 0.2);
                }
                
                .archive-table-body td {
                    padding: var(--spacing-3) var(--spacing-4);
                    border-bottom: var(--border-width) solid rgba(115, 171, 132, 0.1);
                    color: var(--color-accents);
                }
                
                .archive-table-row:hover {
                    background-color: rgba(115, 171, 132, 0.03);
                }
                
                .archive-download {
                    display: inline-flex;
                    align-items: center;
                    gap: var(--spacing-2);
                    padding: var(--spacing-1) var(--spacing-3);
                    color: var(--color-success);
                    background-color: rgba(115, 171, 132, 0.05);
                    border: var(--border-width) solid rgba(115, 171, 132, 0.2);
                    border-radius: var(--border-radius-md);
                    transition: var(--transition);
                    font-weight: var(--font-weight-medium);
                }
                
                .archive-download:hover {
                    background-color: rgba(115, 171, 132, 0.1);
                    border-color: rgba(115, 171, 132, 0.3);
                }
                
                .archive-empty {
                    padding: var(--spacing-8);
                    text-align: center;
                    color: var(--color-accents);
                    font-style: italic;
                    opacity: 0.7;
                }
            </style>
        
            <div class="archive-container">
                <div class="archive-header">
                    <div>
                        <h2 class="archive-title">
                            <i class="bi bi-archive archive-icon"></i>
                            Orders XML Archives
                        </h2>
                        <p class="archive-description">
                            Download previously exported XML files of your orders.
                        </p>
                    </div>
                    <a href="{{ route('dashboard') }}" class="archive-back">
                        <i class="bi bi-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
                
                <div class="archive-table-container">
                    @if(count($files) > 0)
                        <table class="archive-table">
                            <thead class="archive-table-head">
                                <tr>
                                    <th>Filename</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="archive-table-body">
                                @foreach($files as $file)
                                    <tr class="archive-table-row">
                                        <td>{{ $file['name'] }}</td>
                                        <td>{{ $file['date'] }}</td>
                                        <td>{{ $file['time'] }}</td>
                                        <td>{{ $file['size'] }}</td>
                                        <td>
                                            <a href="{{ asset($file['path']) }}" download class="archive-download">
                                                <i class="bi bi-download"></i>
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="archive-empty">
                            No archived XML files found. You can create XML exports from the All Orders page.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 