# Barmada - Bar & Restaurant Management Platform

Barmada is a modern, multi-tenant management dashboard for bars and restaurants, built with Laravel and Livewire. It enables independent businesses ("editors") to manage their own tables, products, and orders, while providing a global admin with oversight and analytics. The platform supports real-time operations, QR-based table flows, and robust data isolation for each editor.

---

## Features

- **Multi-Editor (Multi-Tenant) Support:**  
  Each editor (bar/restaurant owner/manager) has a fully isolated environment: their own tables, products, categories, and orders. Admins can view and manage all editors and their data.
- **Table Management:**  
  Real-time table status, per-editor table numbering, reference notes, and QR-based table request/approval flow.
- **Order Management:**  
  Create, update, and track orders per table. Track order status and individual item payments. Export order history (XML).
- **Product & Category Management:**  
  CRUD for products and categories, custom icons, and pricing.
- **User & Role Management:**  
  Role-based access (Admin, Editor, Staff), staff accounts, and activity logging.
- **Analytics & Reporting:**  
  Sales and activity reports per editor, global admin reports, and export functionality.
- **Real-Time UI:**  
  Livewire-powered real-time updates for tables, orders, and products.
- **Responsive Design:**  
  Mobile-friendly, with theme support (light/dark).

---

## Folder Structure

- **app/**: Main application code (controllers, models, Livewire components, events, providers, etc.)
- **bootstrap/**: Laravel bootstrap files.
- **config/**: Application configuration files.
- **database/**: Migrations, seeders, and factories.
- **docs/**: Project documentation (payment system, live refresh, etc.)
- **public/**: Public assets (CSS, JS, images, build output, entry point).
- **resources/**: Blade views, source CSS/JS, and components.
- **routes/**: Route definitions (web, API, console, etc.)
- **storage/**: App, framework, and log storage.
- **tests/**: Feature and unit tests.
- **vendor/**: Composer dependencies (auto-generated).

For a detailed breakdown of every folder and file, see:
- `APP_STRUCTURE_REFERENCE.txt`
- `APP_STRUCTURE_REFERENCE_PART_2.txt`
- `APP_STRUCTURE_EXPLAINED.txt`

---

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/barmada.git
   cd barmada
   ```
2. **Install PHP dependencies:**
   ```bash
   composer install
   ```
3. **Install JavaScript dependencies:**
   ```bash
   npm install
   ```
4. **Create environment file:**
   ```bash
   cp .env.example .env
   ```
5. **Generate application key:**
   ```bash
   php artisan key:generate
   ```
6. **Configure your database in `.env`.**
7. **Run migrations:**
   ```bash
   php artisan migrate
   ```
8. **Create an admin user:**
   ```bash
   php artisan user:create-admin admin@example.com your_password
   ```
9. **Start the development server:**
   ```bash
   php artisan serve
   ```
10. **Start asset compilation:**
    ```bash
    npm run dev
    ```

---

## Usage

- Access the app at `http://localhost:8000`
- Admins manage editors and view global data
- Editors manage their own tables, products, and orders
- Customers interact via QR codes for table requests and ordering

---

## Upgrading

- Run all new migrations for schema changes and multi-editor support.
- See `implementation_plan_multi_editor.txt` for migration and data assignment details.
- QR links use `/qr-entry/{editorname}/{table_number}` for per-editor uniqueness.

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is source-available and licensed under a custom End User License Agreement (EULA) by DR PIXEL.  
See the [EULA.txt](EULA.txt) file for details.

---

## Support & Security

- For support, open an issue or contact the maintainer.
- For security vulnerabilities, email the maintainer directly.

---

## More Information

- For a full breakdown of the app structure, see the `APP_STRUCTURE_REFERENCE.txt` and related docs in the repo.
- For payment and table session logic, see `docs/tables-payment-system.md`.
