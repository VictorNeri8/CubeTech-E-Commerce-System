# CubeTech E-Commerce

A simple e-commerce website built using PHP and MySQL. It allows customers to browse products, add items to their cart, and place orders. It also includes an admin dashboard for managing products, inventory, customers, and orders.

## Features

**Customer Side**
- Register and log in to an account.
- Browse and search for products.
- Filter products by category and sort by price.
- View product details and related products.
- Add products to the cart and update quantities.
- Place orders using COD, E-Wallet, or Bank Transfer.
- Save and update account and delivery information.
- View order history and track order status.

**Admin Side**
- Log in to the admin dashboard.
- View sales and order statistics.
- Add, edit, and deactivate products.
- Manage product categories.
- Monitor inventory and stock levels.
- View customer records.
- Manage orders and update their status.
- Export and manage product, inventory, and sales records.

## Built With

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- XAMPP

## Getting Started

### Requirements
- XAMPP
- PHP
- MySQL
- A web browser

### Installation

1. Install XAMPP and start Apache and MySQL.
2. Copy the project folder into the `htdocs` directory.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create a database named `cubetech_ecommerce`.
5. Import these files in order:
   - `database/schema.sql`
   - `database/seed.sql`
   - `database/customer_accounts.sql`
6. Open `app/config/database.php` and check the database connection settings.

Default XAMPP database settings:

```php
$host = '127.0.0.1';
$dbname = 'cubetech_ecommerce';
$username = 'root';
$password = '';
```

7. Open the application in your browser:

`http://localhost/cubetech-ecommerce/public/`

## Admin Login

For local testing:

- **Username:** admin
- **Password:** Admin@12345

Change these credentials before deploying the application.

## Order Status

Orders follow these statuses:

- Pending
- Confirmed
- Preparing
- Shipped
- Completed
- Cancelled

Stock is deducted when an order is successfully placed. If an eligible order is cancelled, the purchased quantities are returned to the inventory.

## Notes

- The payment options are for recording the customer's preferred payment method. The website does not process actual online payments.
- The project is currently set up for local development using XAMPP.