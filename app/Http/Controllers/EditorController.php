<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class EditorController extends Controller
{
    public function destroy($id)
    {
        $editor = User::where('is_editor', true)->where('is_admin', false)->findOrFail($id);

        // Delete all related data
        $editor->products()->delete();
        $editor->tables()->delete();
        $editor->orders()->delete();
        $editor->categories()->delete();
        $editor->activityLogs()->delete();

        $editor->delete();

        return redirect()->route('admin.editors')->with('status', 'Establishment and all related data deleted successfully.');
    }
}
