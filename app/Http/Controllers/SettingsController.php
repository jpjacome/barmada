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
        // The theme logo is a single global brand asset served from the web
        // root, so only the platform admin may replace it. The settings view
        // already hides this form for non-admins; enforce it server-side too.
        abort_unless((bool) ($request->user()?->is_admin), 403);

        $request->validate([
            'logo' => [
                'required',
                'file',
                'image',
                // SVG intentionally excluded: it can carry active content and
                // is not a safe upload target for a web-root asset.
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048',
            ],
            'theme' => [
                'required',
                'in:light,dark',
            ],
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $theme = $request->input('theme');
            // Never trust the client-supplied extension for the on-disk name;
            // derive a safe extension from the validated image content type.
            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
            ];
            $extension = $extensionMap[$file->getMimeType()] ?? 'png';
            $filename = 'logo-' . $theme . '.' . $extension;
            $path = public_path('images');
            
            // Ensure directory exists
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
            
            // Log the file details
            Log::info('Uploading theme logo', [
                'theme' => $theme,
                'filename' => $filename,
                'path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $extension
            ]);
            
            try {
                // Store the file in the public/images directory
                $file->move($path, $filename);
                
                Log::info('Theme logo uploaded successfully', [
                    'theme' => $theme,
                    'path' => $path . '/' . $filename
                ]);
                
                return redirect()->route('settings.index')
                    ->with('success', ucfirst($theme) . ' theme logo updated successfully.');
            } catch (\Exception $e) {
                Log::error('Failed to upload theme logo', [
                    'theme' => $theme,
                    'error' => $e->getMessage(),
                    'path' => $path
                ]);
                
                return redirect()->route('settings.index')
                    ->with('error', 'Failed to update ' . $theme . ' theme logo: ' . $e->getMessage());
            }
        }

        return redirect()->route('settings.index')
            ->with('error', 'No file was uploaded.');
    }

    public function toggleTheme(Request $request)
    {
        $currentTheme = session('theme', 'light');
        $newTheme = $currentTheme === 'light' ? 'dark' : 'light';
        
        \Log::info('Theme toggle', [
            'current_theme' => $currentTheme,
            'new_theme' => $newTheme,
            'session_id' => session()->getId()
        ]);
        
        // Store in session
        session(['theme' => $newTheme]);
        
        // Store in user preferences if authenticated
        if (auth()->check()) {
            $user = auth()->user();
            $user->forceFill(['preferences->theme' => $newTheme])->save();
            
            \Log::info('Theme saved to user preferences', [
                'user_id' => $user->id,
                'theme' => $newTheme
            ]);
        }
        // For guests, just set the session (already done above)
        // Redirect back to previous page
        return redirect()->back()->with('success', 'Theme updated successfully');
    }
}