# Barmada - Bar Management Dashboard

Barmada is a comprehensive bar management dashboard built with Laravel, designed to streamline bar operations, order management, and table service.

## Features

- **Table Management**
  - Real-time table status tracking
  - Table assignment and organization
  - Reference notes for each table

- **Order Management**
  - Create and manage orders
  - Track order status (pending, in progress, completed)
  - Individual item payment tracking
  - Order history and reporting

- **Product Management**
  - Add and manage products
  - Categorize products
  - Set prices and track inventory

- **User Management**
  - Role-based access control
  - Staff management
  - Activity logging

- **Reporting**
  - Sales reports
  - Activity logs
  - Export functionality

## Requirements

- PHP >= 8.1
- Composer
- MySQL >= 5.7
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

1. Access the application at `http://localhost:8000`
2. Log in with your admin credentials
3. Start managing your bar operations

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Security Vulnerabilities

If you discover a security vulnerability within Barmada, please send an e-mail to the maintainer. All security vulnerabilities will be promptly addressed.

## Support

For support, please open an issue in the GitHub repository or contact the maintainer.
