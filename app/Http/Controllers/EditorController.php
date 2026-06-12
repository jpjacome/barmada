<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EditorController extends Controller
{
    /**
     * Delete an establishment and ALL of its data (admin only; the route
     * is admin-gated and this guards again for defense in depth).
     *
     * Deletion follows foreign-key dependency order: order items before
     * orders, orders before tables, session requests before sessions,
     * sessions before tables.
     */
    public function destroy($id)
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $editor = User::where('is_editor', true)->where('is_admin', false)->findOrFail($id);

        // FK-ordered transactional purge, shared with the API.
        app(\App\Actions\Admin\DeleteEstablishment::class)->handle($editor);

        return redirect()->route('admin.editors')
            ->with('status', 'Establishment and all related data deleted successfully.');
    }
}
