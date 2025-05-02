# Barmada - Bar Management Dashboard

Barmada is a modern, multi-tenant bar and restaurant management dashboard built with Laravel. It enables multiple independent businesses ("editors") to manage their own tables, products, and orders, while providing a global admin with oversight and management tools. The platform supports real-time operations, QR-based table flows, and robust data isolation for each editor.

## Key Features

### Multi-Editor (Multi-Tenant) Support
- Each editor (bar/restaurant owner/manager) has a fully isolated environment: their own tables, products, categories, and orders.
- Admin user can view and manage all editors and their data, with impersonation and dashboard switching.
- Editors can customize their business info (name, contact, etc.).

### Table Management
- Real-time table status tracking and assignment
- Per-editor table numbering (each editor's tables start from 1)
- Reference notes for each table
- **QR Table Request & Approval Flow**
  - Customers scan a QR code unique to each editor and table
  - Editors/admins approve table requests in real time
  - Customers are notified when their table is ready

### Order Management
- Create, update, and track orders per table
- Track order status (pending, in progress, completed)
- Individual item payment tracking
- Order history, reporting, and export (XML)
- Real-time updates via Livewire

### Product & Category Management
- Add, edit, and organize products and categories per editor
- Upload custom icons for products
- Set prices and manage inventory

### User & Role Management
- Role-based access: Admin, Editor, Staff
- Editors can have staff accounts linked to their business
- Admin dashboard for managing editors and global data
- Activity logging for auditing

### Reporting & Activity Logs
- Sales and activity reports per editor
- Global admin reports
- Export functionality for orders and logs

### Real-Time & UX Features
- Livewire-powered real-time UI for tables, orders, and products
- Responsive, mobile-friendly design
- Theme support (light/dark)

## Requirements

- PHP >= 8.1
- Composer
- MySQL >= 5.7 or SQLite (for development)
- Node.js & NPM (for frontend assets)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/barmada.git
   cd barmada
   ```
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Install JavaScript dependencies:
   ```bash
   npm install
   ```
4. Create environment file:
   ```bash
   cp .env.example .env
   ```
5. Generate application key:
   ```bash
   php artisan key:generate
   ```
6. Configure your database in `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=barmada
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```
7. Run migrations:
   ```bash
   php artisan migrate
   ```
8. Create an admin user:
   ```bash
   php artisan user:create-admin admin@example.com your_password
   ```
9. Start the development server:
   ```bash
   php artisan serve
   ```
10. In a separate terminal, start the asset compilation:
   ```bash
   npm run dev
   ```

## Usage

- Access the app at `http://localhost:8000`
- Admins log in to manage editors and view global data
- Editors log in to manage their own tables, products, and orders
- Customers interact via QR codes for table requests and ordering

## Upgrading from Previous Versions

- Run all new migrations to add multi-editor support (`editor_id` columns, per-editor table numbering, etc.)
- Review the [implementation_plan_multi_editor.txt](implementation_plan_multi_editor.txt) for migration and data assignment details
- QR links now use `/qr-entry/{editorname}/{table_number}` for per-editor uniqueness

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is source-available and licensed under a custom End User License Agreement (EULA) by DR PIXEL. You may view and download the code, but you may not modify or use it in your own projects without express written permission from DR PIXEL. See the [EULA.txt](EULA.txt) file for details.

## Security Vulnerabilities

If you discover a security vulnerability within Barmada, please send an e-mail to the maintainer. All security vulnerabilities will be promptly addressed.

## Support

For support, please open an issue in the GitHub repository or contact the maintainer.
