<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', StockItem::class);

        return view('inventory.index');
    }
}
