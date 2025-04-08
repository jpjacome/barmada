# Golems App 🍺

A modern, real-time bar ordering system built with Laravel and Livewire. Designed for small bars with up to 6 tables, Golems App streamlines the ordering process, inventory management, and checkout experience.

## Features

### Current Features
- ✅ Real-time updates using Laravel Livewire
- ✅ Dynamic, responsive UI with Tailwind CSS
- ✅ Secure authentication system
- ✅ Database integration
- ✅ Pusher integration for real-time events
- ✅ Table management system (up to 6 tables)

### Planned Features
- 🍹 Menu and product catalog
- 🛒 Order creation and tracking
- 💰 Checkout and payment processing
- 📊 Basic reporting and analytics
- 🗄️ Order history and search
- 📱 Mobile-responsive design

## Technology Stack

- **Backend**: Laravel 12
- **Frontend**: Livewire 3.6, Alpine.js
- **Database**: MySQL
- **Styling**: Tailwind CSS
- **Real-time**: Pusher
- **Authentication**: Laravel Breeze

## Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and NPM
- MySQL or compatible database

### Installation

1. Clone the repository
```bash
git clone https://github.com/jpjacome/golems-bar.git
cd golems-bar
```

2. Install PHP dependencies
```bash
composer install
```

3. Install NPM dependencies
```bash
npm install
```

4. Create environment file
```bash
cp .env.example .env
```

5. Generate application key
```bash
php artisan key:generate
```

6. Configure your database in the .env file
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=golems
DB_USERNAME=root
DB_PASSWORD=your_password
```

7. Run migrations
```bash
php artisan migrate
```

8. Compile assets
```bash
npm run dev
```

9. Start the development server
```bash
php artisan serve
```

The application will be available at http://localhost:8000

## Screenshots

(Coming soon)

## Development Roadmap

1. **Phase 1**: Setup and infrastructure ✅
2. **Phase 2**: Table management system ✅
3. **Phase 3**: Menu and product catalog
4. **Phase 4**: Order creation and tracking
5. **Phase 5**: Checkout and payment
6. **Phase 6**: Reporting and analytics

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- Built with [Laravel](https://laravel.com/)
- Real-time updates with [Livewire](https://livewire.laravel.com/)
- Styling with [Tailwind CSS](https://tailwindcss.com/)
