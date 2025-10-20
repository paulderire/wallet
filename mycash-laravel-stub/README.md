Laravel migration stubs for MY CASH

Goal
----
This folder contains a minimal set of files (routes, controllers, Blade views, and a users migration) to jumpstart converting the existing custom PHP app into Laravel.

How to use
----------
1. Install Composer on your machine: https://getcomposer.org/
2. Create a fresh Laravel project (example uses Laravel 10):

```bash
composer create-project laravel/laravel mycash-laravel
cd mycash-laravel
```

3. Copy the contents of this `mycash-laravel-stub` into the new project (merge where appropriate):

From the repo root (example):
```bash
rsync -av --progress "mycash-laravel-stub/" mycash-laravel/
```

4. Install npm dependencies and build frontend (if you use Laravel Mix/Vite):
```bash
cd mycash-laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve # for local testing
```

5. Replace or port your existing PHP view markup into `resources/views` and wire controllers as needed. The stubs here provide a starting point for `auth` and `dashboard` flows.

What is included
----------------
- `routes/web.php` - route examples mapping to controllers
- `app/Http/Controllers/AuthController.php` - login/logout handling (simple)
- `app/Http/Controllers/DashboardController.php` - dashboard
- `resources/views/layouts/app.blade.php` - base layout
- `resources/views/auth/login.blade.php` - login view
- `resources/views/dashboard.blade.php` - dashboard view
- `database/migrations/2025_10_20_000000_create_users_table.php` - users migration skeleton
- `.env.example` - example environment variables

Notes
-----
- These are stubs and not a complete Laravel migration. After copying, update models, controllers and views to port all app features incrementally.
- I can continue and port specific pages/controllers if you want — tell me which page to convert first.
