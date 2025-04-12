<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index');
    }

    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'mimes:jpeg,png,jpg,gif,svg',
                'max:2048',
            ],
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = 'logo.' . $file->getClientOriginalExtension();
            $path = public_path('images');
            
            // Ensure directory exists
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
            
            // Log the file details
            Log::info('Uploading logo', [
                'filename' => $filename,
                'path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension()
            ]);
            
            try {
                // Store the file in the public/images directory
                $file->move($path, $filename);
                
                Log::info('Logo uploaded successfully', [
                    'path' => $path . '/' . $filename
                ]);
                
                return redirect()->route('settings.index')
                    ->with('success', 'Logo updated successfully.');
            } catch (\Exception $e) {
                Log::error('Failed to upload logo', [
                    'error' => $e->getMessage(),
                    'path' => $path
                ]);
                
                return redirect()->route('settings.index')
                    ->with('error', 'Failed to update logo: ' . $e->getMessage());
            }
        }

        return redirect()->route('settings.index')
            ->with('error', 'No file was uploaded.');
    }
} 