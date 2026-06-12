<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\OrderItem;
use App\Support\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class OrderController extends Controller
{
    /**
     * Apply a venue's presentation settings (locale for the menu pages,
     * currency symbol for prices). Returns the currency symbol.
     */
    private function applyVenuePresentation(?int $editorId, ?User $fallback = null): string
    {
        $venue = $editorId ? User::find($editorId) : null;
        $venue = $venue ?: $fallback;

        if ($venue) {
            app()->setLocale($venue->guestLocale());

            return $venue->currencySymbol();
        }

        return '$';
    }

    /**
     * Show the order creation page or handle table link redirection.
     */
    public function orderEntry(Request $request)
    {
        $user = Auth::user();
        $tableId = $request->query('table');
        if ($tableId) {
            // If table param is present, use the redirection logic
            return $this->handleTableLink($request);
        }
        if ($user->is_editor) {
            $editorId = $user->id;
        } elseif ($user->is_staff) {
            $editorId = $user->editor_id;
        } else {
            $editorId = $user->id;
        }
        $products = Product::where('editor_id', $editorId)->orderBy('name')->get();
        $tables = Table::where('editor_id', $editorId)->orderBy('table_number')->get();
        $selectedTableId = null;
        $currentEditorId = $editorId;
        $currency = $this->applyVenuePresentation($editorId, $user);
        return view('orders.create', compact('products', 'tables', 'selectedTableId', 'currentEditorId', 'currency'));
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'products' => 'required|array|max:50',
            'products.*' => 'integer|min:0|max:99',
            'note' => 'nullable|string|max:280',
        ]);
        
        // Check if any products were ordered
        $hasProducts = false;
        foreach ($validated['products'] as $quantity) {
            if ($quantity > 0) {
                $hasProducts = true;
                break;
            }
        }
        
        if (!$hasProducts) {
            return redirect()->back()->withErrors(['products' => 'Please select at least one product.']);
        }
        
        // Get the table
        $table = Table::findOrFail($validated['table_id']);
        
        // Only restrict if user is an editor
        if ($user && $user->is_editor && $table->editor_id != $user->id) {
            abort(403);
        }
        
        // Find the current open TableSession for this table
        $currentSession = \App\Models\TableSession::where('table_id', $table->id)
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();
        
        if (!$currentSession) {
            return redirect()->back()->withErrors(['table_id' => 'No open session for this table. Please open the table first.']);
        }
        
        // Assign editor_id: admins record the table's tenant; editors and
        // staff record their own tenant (a staff user's editor, not the
        // staff user's id).
        $editorId = $user->is_admin ? $table->editor_id : $user->effectiveEditorId();
        
        // Create the order. Manual orders record who entered them so the
        // staff analytics have a real grouping column.
        $order = Order::create([
            'table_id' => $table->id,
            'table_session_id' => $currentSession->id,
            'status' => 'pending',
            'note' => isset($validated['note']) ? strip_tags($validated['note']) : null,
            'created_by' => $user->id,
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_left' => 0,
            'editor_id' => $editorId,
        ]);

        // Calculate total amount and create order items
        $totalAmount = 0;
        $itemIndex = 0;

        foreach ($validated['products'] as $productId => $quantity) {
            if ($quantity > 0) {
                // Products must belong to the table's tenant (also bounds
                // admin-created orders to the right catalog).
                $product = Product::forEditor($table->editor_id)->findOrFail($productId);
                if (! $product->is_available) {
                    $order->items()->delete();
                    $order->delete();
                    return redirect()->back()->withErrors([
                        'products' => __('“:name” is sold out.', ['name' => $product->name]),
                    ]);
                }
                $price = $product->price;
                $totalAmount += $quantity * $price;
                
                // Create individual order items for each unit
                for ($i = 0; $i < $quantity; $i++) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'quantity' => 1,
                        'price' => $price,
                        'is_paid' => false,
                        'item_index' => $itemIndex++
                    ]);
                }
            }
        }
        
        // Update order total
        $order->update([
            'total_amount' => $totalAmount,
            'amount_left' => $totalAmount
        ]);
        
        return redirect()->route('orders.confirmation')->with('success', 'Your order has been submitted!');
    }
    
    /**
     * Print-friendly ticket for one order — for the bar or kitchen pass.
     */
    public function ticket(Order $order)
    {
        $this->authorize('view', $order);
        $order->load(['items.product', 'table']);

        $lines = [];
        foreach ($order->items as $item) {
            $name = $item->product->name ?? '—';
            $lines[$name] = ($lines[$name] ?? 0) + 1;
        }

        return view('orders.ticket', [
            'order' => $order,
            'lines' => $lines,
        ]);
    }

    /**
     * Display order confirmation page.
     *
     * Guests arrive here from the stateless order submission with their
     * table token in `t`, which lets the page speak the venue's language
     * and name the table. Staff arrive without it after manual entry.
     */
    public function confirmation(Request $request)
    {
        $tableNumber = null;

        if ($token = $request->query('t')) {
            $table = Table::where('unique_token', $token)->first();
            if ($table && $table->editor) {
                app()->setLocale($table->editor->guestLocale());
                $tableNumber = $table->table_number;
            }
        } elseif ($user = Auth::user()) {
            $this->applyVenuePresentation($user->is_admin ? $user->id : $user->effectiveEditorId(), $user);
        }

        return view('orders.confirmation', compact('tableNumber'));
    }
    
    /**
     * Display order archives page with XML files.
     *
     * - Editors only (abort 403 otherwise).
     * - Only the current editor's own files are listed.
     * - Files live OUTSIDE the public disk at:
     *   storage/app/archive/{editor_id}/orders_{editor_id}_YYYY-MM-DD_HH-ii-ss.xml
     * - Each row links to the authorized downloadArchive() route, never a
     *   public storage URL.
     */
    public function archive()
    {
        $user = Auth::user();
        if (!$user->is_editor) {
            abort(403);
        }
        $editorId = $user->id;
        // Archives live outside the public disk; the listing only ever scans
        // the caller's own per-editor directory.
        $archiveDir = storage_path('app/archive/' . $editorId);
        $files = [];
        if (file_exists($archiveDir)) {
            $xmlFiles = glob($archiveDir . '/orders_' . $editorId . '_*.xml');
            foreach ($xmlFiles as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $lastModified = filemtime($file);
                preg_match('/orders_\\d{1,}_([0-9]{4}-[0-9]{2}-[0-9]{2})_([0-9]{2}-[0-9]{2}-[0-9]{2})\\.xml/', $filename, $matches);
                $date = isset($matches[1]) ? $matches[1] : '';
                $time = isset($matches[2]) ? str_replace('-', ':', $matches[2]) : '';
                $files[] = [
                    'name' => $filename,
                    // Served through the authorized, ownership-checked route —
                    // never a public/guessable storage URL.
                    'download_url' => route('orders.archive.download', ['filename' => $filename]),
                    'size' => $this->formatFileSize($size),
                    'last_modified' => date('Y-m-d H:i:s', $lastModified),
                    'date' => $date,
                    'time' => $time
                ];
            }
            usort($files, function($a, $b) {
                return strtotime($b['last_modified']) - strtotime($a['last_modified']);
            });
        }
        return view('orders.archive', compact('files'));
    }

    /**
     * Stream an order-archive XML file to its owning editor only.
     *
     * Archives are stored outside the public disk and contain customer/sales
     * data, so every download is gated on (a) editor role, (b) a filename that
     * matches the caller's own per-editor naming pattern, and (c) a realpath
     * containment check that prevents traversal or symlink escape.
     */
    public function downloadArchive(string $filename)
    {
        $user = Auth::user();
        if (!$user->is_editor) {
            abort(403);
        }
        $editorId = $user->id;

        // Filename must be exactly one of this editor's own archive files.
        if (!preg_match('/^orders_' . $editorId . '_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.xml$/', $filename)) {
            abort(404);
        }

        $dir = storage_path('app/archive/' . $editorId);
        $realBase = realpath($dir);
        $realPath = realpath($dir . '/' . $filename);
        if ($realBase === false || $realPath === false
            || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->download($realPath, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Handle permanent table link and redirect to unique token link if open.
     */
    public function handleTableLink(Request $request)
    {
        $tableId = $request->query('table');
        if (!$tableId) {
            abort(404, 'Table not specified.');
        }
        $table = Table::where('id', $tableId)->first();
        if (!$table) {
            abort(404, 'Table not found.');
        }
        if ($table->status === 'open' && $table->unique_token) {
            // Redirect to the unique token link
            return redirect()->route('order.redirect', ['unique_token' => $table->unique_token]);
        }
        // Table is closed or no token: show a message
        if ($table->editor) {
            app()->setLocale($table->editor->guestLocale());
        }
        return response()->view('orders.table-closed', ['table' => $table]);
    }

    /**
     * Handle QR code entry: set table to pending_approval and show waiting page, or redirect if open.
     */
    public function qrEntry($editorname, $table_number)
    {
        $editor = User::where('username', $editorname)->first();
        if (!$editor) {
            abort(404, 'Editor not found.');
        }
        $table = Table::where('editor_id', $editor->id)
            ->where('table_number', $table_number)
            ->whereNull('archived_at') // archived tables are retired from the QR flow [#5]
            ->first();
        if (!$table) {
            abort(404, 'Table not found.');
        }
        $ip = request()->ip();
        // Identify the device with a long-lived cookie [F-18]; IP remains a
        // fallback for rows recorded before the cookie existed.
        $device = DeviceToken::ensure(request());
        app()->setLocale($editor->guestLocale());

        // Every scan is an analytics signal (QR conversion metrics).
        ActivityLog::create([
            'type' => 'qr_scan',
            'table_id' => $table->id,
            'editor_id' => $table->editor_id,
            'description' => 'QR scan for table '.$table->table_number,
        ]);

        // If table is open and has a unique token, handle session request
        if ($table->status === 'open' && $table->unique_token) {
            $currentSession = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
            if ($currentSession) {
                // Check if this device is already pending or approved
                $existingRequest = $this->scopeToDevice(
                    $currentSession->sessionRequests()->whereIn('status', ['pending', 'approved']),
                    $ip,
                    $device
                )->first();
                if (!$existingRequest) {
                    \App\Models\TableSessionRequest::create([
                        'table_session_id' => $currentSession->id,
                        'table_id' => $table->id,
                        'ip_address' => $ip,
                        'device_token' => $device,
                        'status' => 'pending',
                        'requested_at' => now(),
                    ]);
                }
                // If already approved, redirect to order page
                $approved = $this->scopeToDevice(
                    $currentSession->sessionRequests()->where('status', 'approved'),
                    $ip,
                    $device
                )->exists();
                if ($approved) {
                    return redirect()->route('order.redirect', ['unique_token' => $table->unique_token]);
                }
            }
            // If not approved, show waiting page
            return view('orders.waiting-approval', ['table' => $table]);
        }
        // If table is closed or pending, set to pending_approval if not already
        if ($table->status !== 'pending_approval') {
            $table->status = 'pending_approval';
            $table->save();
        }
        // Record the scanning device NOW (without a session): staff approval
        // adopts these requests into the session created when the table
        // opens, so the first customer is approved in one step. [F-1]
        $alreadyRequested = $this->scopeToDevice(
            \App\Models\TableSessionRequest::whereNull('table_session_id')
                ->where('table_id', $table->id)
                ->where('status', 'pending')
                ->whereDate('created_at', now()->toDateString()),
            $ip,
            $device
        )->exists();
        if (! $alreadyRequested) {
            \App\Models\TableSessionRequest::create([
                'table_id' => $table->id,
                'ip_address' => $ip,
                'device_token' => $device,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
        }
        return view('orders.waiting-approval', ['table' => $table]);
    }

    /**
     * Constrain a session-request query to the calling device: by device
     * cookie when present (with IP fallback for token-less legacy rows),
     * by IP otherwise. [F-18]
     */
    private function scopeToDevice($query, string $ip, ?string $device)
    {
        return $query->where(function ($q) use ($ip, $device) {
            if ($device) {
                $q->where('device_token', $device)
                    ->orWhere(function ($qq) use ($ip) {
                        $qq->whereNull('device_token')->where('ip_address', $ip);
                    });
            } else {
                $q->where('ip_address', $ip);
            }
        });
    }

    /**
     * Polling endpoint for table status: returns status and redirect URL if open.
     */
    public function pollTableStatus($tableId)
    {
        $table = \App\Models\Table::find($tableId);
        if (!$table) {
            return response()->json(['status' => 'not_found']);
        }
        
        if ($table->status === 'open') {
            // Generate unique_token if missing
            if (!$table->unique_token) {
                $table->generateUniqueToken();
            }
            $user = Auth::user();
            if ($user && ($user->is_admin || $user->is_editor || $user->is_staff)) {
                // Authenticated users: allow immediate access
                return response()->json([
                    'status' => 'open',
                    'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
                ]);
            } else {
                // Guest: require device approval
                $ip = request()->ip();
                $device = DeviceToken::ensure(request());
                $currentSession = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
                if ($currentSession) {
                    $approved = $this->scopeToDevice(
                        $currentSession->sessionRequests()->where('status', 'approved'),
                        $ip,
                        $device
                    )->exists();
                    if ($approved) {
                        return response()->json([
                            'status' => 'open',
                            'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
                        ]);
                    }

                    // Self-healing registration [F-1]: a guest polling an open
                    // table must always end up visible to staff. Adopt the
                    // device's pre-session request into the current session,
                    // or register a fresh pending request if none exists —
                    // without this, guests who scanned while the table was
                    // closed could wait forever with staff seeing nothing.
                    $known = $this->scopeToDevice(
                        $currentSession->sessionRequests()->whereIn('status', ['pending', 'approved']),
                        $ip,
                        $device
                    )->exists();
                    if (! $known) {
                        $orphan = $this->scopeToDevice(
                            \App\Models\TableSessionRequest::whereNull('table_session_id')
                                ->where('table_id', $table->id)
                                ->where('status', 'pending'),
                            $ip,
                            $device
                        )->latest('requested_at')->first();
                        if ($orphan) {
                            $orphan->table_session_id = $currentSession->id;
                            $orphan->save();
                        } else {
                            \App\Models\TableSessionRequest::create([
                                'table_session_id' => $currentSession->id,
                                'table_id' => $table->id,
                                'ip_address' => $ip,
                                'device_token' => $device,
                                'status' => 'pending',
                                'requested_at' => now(),
                            ]);
                        }
                    }
                }
                // Not approved: keep waiting
                return response()->json(['status' => 'waiting_ip_approval']);
            }
        }
        
        return response()->json(['status' => $table->status]);
    }

    /**
     * Polling endpoint for customer waiting page: returns order status and redirect URL if approved.
     */
    public function pollOrderStatus($orderId)
    {
        $order = \App\Models\Order::find($orderId);
        if ($order && $order->status === 'approved') {
            $table = \App\Models\Table::find($order->table_id);
            if ($table && $table->unique_token) {
                return response()->json([
                    'status' => 'approved',
                    'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
                ]);
            }
        }
        return response()->json(['status' => $order ? $order->status : 'not_found']);
    }
}