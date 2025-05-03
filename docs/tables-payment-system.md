# Tables and Payment System Documentation

## Database Structure

### Tables
- `tables`: Stores table information
  - `id`: Primary key
  - `orders`: Number of orders for this table

### Orders
- `orders`: Stores order information
  - `id`: Primary key
  - `table_id`: Foreign key to tables
  - `total_amount`: Total amount of the order
  - `amount_paid`: Amount paid so far
  - `amount_left`: Remaining amount to pay
  - `created_at`: Order creation timestamp

### Order Items
- `order_items`: Stores individual items in an order
  - `id`: Primary key
  - `order_id`: Foreign key to orders
  - `product_id`: Foreign key to products
  - `quantity`: Number of items
  - `price`: Price per item
  - `is_paid`: Boolean indicating if this item is paid (default: false)
  - `item_index`: Index of the item in the order (starts from 0)

### Products
- `products`: Stores product information
  - `id`: Primary key
  - `name`: Product name
  - `price`: Product price
  - `icon_type`: Type of icon (svg or bootstrap)
  - `icon_value`: Icon value (path or class)

## Order Creation Flow

1. When a new order is created:
   - Create the order record in `orders` table
   - For each product in the order:
     - Create an `order_items` record for each quantity
     - Set `is_paid` to false by default
     - Set `item_index` sequentially (0, 1, 2, etc.)
     - Store the product's price at the time of order

2. Example of order creation:
```php
// Create the order
$order = Order::create([
    'table_id' => $tableId,
    'total_amount' => $totalAmount,
    'amount_paid' => 0,
    'amount_left' => $totalAmount
]);

// Create order items
$itemIndex = 0;
foreach ($products as $product) {
    for ($i = 0; $i < $product['quantity']; $i++) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product['id'],
            'quantity' => 1,
            'price' => $product['price'],
            'is_paid' => false,
            'item_index' => $itemIndex++
        ]);
    }
}
```

## Payment Flow

1. When viewing a table's orders:
   - Load all orders for the table
   - For each order, load its items with their payment status
   - Display items with visual indicators for paid/unpaid status

2. When marking an item as paid:
   - Find the specific `order_item` by order_id, product_id, and item_index
   - Update its `is_paid` status to true
   - Recalculate the order's `amount_paid` and `amount_left`
   - Update the order's payment status

3. When marking an entire order as paid:
   - Find all `order_items` for the order
   - Update all their `is_paid` status to true
   - Set order's `amount_paid` equal to `total_amount`
   - Set order's `amount_left` to 0

## Data Structure in Views

### Order Data Structure
```php
$order = [
    'id' => int,
    'table_id' => int,
    'total_amount' => float,
    'amount_paid' => float,
    'amount_left' => float,
    'created_at' => timestamp,
    'items' => [
        [
            'id' => int,
            'product_id' => int,
            'quantity' => int,
            'price' => float,
            'is_paid' => boolean,
            'item_index' => int,
            'product' => [
                'id' => int,
                'name' => string,
                'price' => float,
                'icon_type' => string,
                'icon_value' => string
            ]
        ]
    ]
];
```

## Current Implementation

1. Order Creation:
   - Orders are created with products
   - Each product quantity creates individual order items
   - All items start as unpaid

2. Payment Handling:
   - Items can be individually marked as paid
   - Order totals are updated based on paid items
   - Visual feedback shows paid/unpaid status

3. Data Loading:
   - Orders are loaded with their items
   - Each item includes its product information
   - Payment status is tracked per item

## Current Issues

1. Data Structure Mismatch:
   - The view expects `$order['items']` but the data is not being loaded correctly
   - Need to ensure the `loadTableOrders` method properly loads and structures the data

2. Payment Status Updates:
   - Need to properly track individual item payment status
   - Need to update order totals when items are marked as paid

## Required Changes

1. Update `loadTableOrders` method to:
   - Load orders with their items
   - Include product information for each item
   - Structure the data correctly for the view

2. Update payment handling to:
   - Track individual item payment status
   - Update order totals correctly
   - Provide proper visual feedback

3. Update the view to:
   - Display items correctly
   - Show proper payment status
   - Handle item selection and payment marking

## Table Sessions, Staff, and QR Code Flow (2025-05-02 Update)

### Table Sessions
- Each table session is represented by a TableSession model and database record.
- A session is opened by a staff member or editor (recorded in `opened_by` and `opened_at`).
- A session is closed by a staff member or editor (recorded in `closed_by` and `closed_at`).
- Session duration can be calculated as `closed_at - opened_at`.
- Each session has a unique token for QR access, a session number (per table, per day), and is linked to all orders created during the session.
- Only editors can reopen closed sessions; reopening generates a new token and updates `opened_by`/`opened_at`.

### Staff Users and Management
- Staff users are a distinct user type with limited permissions compared to editors.
- Editors and admins can manage staff accounts via the staff management UI (add, view, delete staff).
- Staff can open and close table sessions, but only editors can reopen sessions.
- Staff actions (opening/closing sessions) are tracked in TableSession (`opened_by`, `closed_by`).

### QR Code Functionality
- Each table has a unique QR code image generated and stored in `public/images/`.
- QR codes are generated using the endroid/qr-code and simplesoftwareio/simple-qrcode packages.
- Scanning a QR code allows customers to access the table, request service, or place orders (depending on table/session status).
- QR codes are regenerated with each new session (new token).

### UI and Data Flow
- The UI displays a QR code modal for each table, accessible to editors and staff.
- The staff management UI is accessible to editors and admins.
- Table session data (who opened/closed, when, duration, orders) is tracked in the database and can be displayed in the UI if needed.