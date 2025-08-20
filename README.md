# Acumen Retail – Point of Sale (POS) Web Application

A complete web-based Point of Sale (POS) system built with PHP, MySQL, and Tailwind CSS. This system is designed to run on cPanel hosting with PHP 8+ and MySQL, featuring robust concurrency handling, comprehensive tax management, real-time inventory tracking, and a polished UI/UX.

Branded product name: Acumen Retail

## Features

### 🔐 Authentication & Security
- User login with role-based access control (Admin & Cashier)
- Password hashing using PHP's `password_hash()` and `password_verify()`
- Session-based authentication
- CSRF protection for form submissions
- Prepared statements to prevent SQL injection

### 📊 Dashboard
- Real-time statistics (total products, sales today, revenue, low stock items)
- Recent sales overview
- Responsive design with Tailwind CSS

### 🛍️ Product Management (Admin Only)
- Complete CRUD operations for products
- Category management
- Stock tracking with low stock alerts
- SKU-based product identification
- Admin-only visibility of cost price (moving average based on recent purchases)
- Tax rate management per product
- Add product supports entering initial cost price and tax rate
- Updating product no longer allows changing stock (stock is controlled via sales/purchases)

### 💰 POS Sales System
- Live product search by name or SKU
- Products list always visible and scrollable
- Real-time cart management with tax calculations
- **Cart persistence** - Cart survives page refreshes and browser restarts
- **Concurrency protection** - Real-time stock validation prevents overselling
- Discount field, mixed payments (cash/card), balance/change calculation
- **Tax-inclusive pricing** with backward tax calculation
- Stock validation during checkout with database-level protection
- Transaction processing with database rollback on errors
- Cart is preserved on checkout errors (restored automatically)
- **Fixed height layout** - Optimized for single-screen operation
- **Toggle header** - Hide/show navigation for more screen space
- **Payment modal** - Clean payment interface
- Printable thermal printer-optimized invoice
- **Individual item tax display** - Shows tax amount and rate for each item
 - **Hold/Resume sales (Parked orders)** - Save a cart with a note and resume later
 - **Keyboard shortcuts** - Fast operations for search, payment, quantities, park/resume

### 📈 Reports & Analytics (Admin Only)
- Daily, weekly, monthly, and custom date range reports
- Sales statistics and revenue tracking
- Detailed sales history with "View Invoice"
- Printable invoice with a subtle "DUPLICATE" watermark overlay (print-only)
- **Individual item tax breakdown** in invoice display
- **Always show change amount** - Even when zero for complete transparency
- Items sold analytics

### 🔄 Returns / Exchanges
- Line-level returns with specified quantities and reason per line
- Prevent over-returns with real-time eligibility checks
- Refund to original tender (cash/card) or a specified split
- Return invoices printable with duplicate overlay for reprints
- Returns list view with collapsible details and reprint

### 🧩 UI/UX Enhancements
- Fixed cart table layout with Sr., Item Name, Desc, Qty, Price, Tax, Total, Action
- Auto-scroll and highlight to newly added cart items
- Reduced padding, improved typography, consistent vertical borders
- Dark mode with proper contrast for cards, tables, inputs, and hover states
- Font Awesome 7 Pro icons across Dashboard, Products, Vendors, Purchases, Reports, Sales
- Accessible toasts and skeletons via `assets/ui.css` and `assets/ui.js`

### 🧾 Vendors (Admin Only)
- CRUD for vendors
- Prevent deletion if linked purchases exist

### 📦 Purchases (Admin Only)
- Create purchases with multiple items
- Increases product stock and updates cost price
- Safe database transactions for atomic updates
- View/delete purchases, view purchase details

### 🔄 Concurrency & Real-time Features
- **Multi-user support** - Multiple users can process sales simultaneously
- **Real-time stock validation** - Prevents overselling with database-level checks
- **Automatic stock refresh** - Cart updates with fresh stock data every 30 seconds
- **Session isolation** - Each user's data is completely separate
- **Transaction safety** - Database transactions ensure data integrity

## Tech Stack

- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript, jQuery (for AJAX)
- **Backend**: PHP 8+ (Procedural style)
- **Database**: MySQL with transaction support
- **Hosting**: Compatible with cPanel hosting

## Currency & Tax System

- All prices display in PKR
- **Tax-inclusive pricing** - Product prices include tax
- **Backward calculation** - Tax calculated from inclusive prices
- **Individual item tax display** - Shows tax amount and percentage for each item
- Invoice item rows show numeric values only; currency prefixes used in totals and payment sections

## Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- cPanel hosting account

### Step 1: Database Setup
1. Create a new MySQL database in your cPanel
2. Import the `database.sql` file into your database
3. Update database credentials in `includes/db.php`:

```php
$host = 'localhost';
$username = 'your_db_username';
$password = 'your_db_password';
$database = 'your_database_name';
```

### Step 2: File Upload
1. Upload all files to your web server's public directory
2. Ensure the file structure is maintained:
```
/
├── index.php
├── login.php
├── logout.php
├── database.sql
├── README.md
├── .gitignore
├── .cpanel.yml
├── includes/
│   ├── auth.php
│   ├── db.php
│   ├── header.php
│   └── footer.php
└── modules/
    ├── products.php
    ├── sales.php
    ├── reports.php
    ├── vendors.php
    ├── purchases.php
    ├── ajax_search_products.php
    ├── ajax_get_stock.php
    ├── get_sale_details.php
    └── get_purchase_details.php
```

### Step 3: Configuration
1. Set proper file permissions (644 for files, 755 for directories)
2. Ensure PHP has write permissions for session handling
3. Verify that your hosting supports PHP sessions

## Default Login Credentials

### Admin User
- **Username**: `admin`
- **Password**: `admin123`
- **Role**: Administrator (full access)

### Cashier User
- **Username**: `cashier`
- **Password**: `admin123`
- **Role**: Cashier (POS access only)

## Usage

### For Administrators
1. **Dashboard**: View system overview and statistics
2. **Products**: Manage product inventory, add/edit/delete products, set tax rates
3. **POS Sales**: Process customer transactions with real-time stock validation
4. **Reports**: Generate sales reports and analytics; print invoices with duplicate watermark
5. **Vendors**: Manage vendors
6. **Purchases**: Record purchases that increase stock and update cost prices

### For Cashiers
1. **Dashboard**: View basic statistics
2. **POS Sales**: Process customer transactions with cart persistence
3. **Limited Access**: Cannot access product management or reports

## Security Features

- **Password Security**: All passwords are hashed using PHP's `password_hash()`
- **SQL Injection Protection**: All database queries use prepared statements
- **XSS Protection**: All user input is properly escaped
- **CSRF Protection**: Form submissions include CSRF tokens
- **Session Security**: Secure session handling with proper validation
- **Access Control**: Role-based access control for different user types
- **Concurrency Safety**: Database-level protection against race conditions

## File Structure

```
/
├── index.php              # Main dashboard
├── login.php              # Login page
├── logout.php             # Logout handler
├── database.sql           # Database schema and sample data
├── README.md              # This file
├── .gitignore             # Git ignore file
├── .cpanel.yml            # cPanel deployment configuration
├── includes/              # Core includes
│   ├── auth.php          # Authentication functions
│   ├── db.php            # Database connection
│   ├── header.php        # Page header and navigation
│   └── footer.php        # Page footer
└── modules/              # Application modules
    ├── products.php      # Product management
    ├── sales.php         # POS sales system
    ├── reports.php       # Sales reports + invoice print/view
    ├── vendors.php       # Vendors management
    ├── purchases.php     # Purchases management
    ├── ajax_search_products.php # AJAX product search
    ├── ajax_get_stock.php       # AJAX stock refresh for concurrency
    ├── ajax_park_sale.php       # Park a sale (save cart with note)
    ├── ajax_list_parked.php     # List parked sales
    ├── ajax_get_parked.php      # Retrieve parked sale
    ├── list_sales.php           # Sales lookup for returns
    ├── list_returns.php         # Returns lookup
    ├── ajax_process_return.php  # Process returns
    ├── return_receipt.php       # Return receipt payload
    ├── get_sale_details.php     # AJAX sale details for returns
    └── purchases.php            # Purchases module (AJAX inside file)
```

## Database Schema

### Tables
- **users**: User accounts and authentication
- **categories**: Product categories
- **products**: Product inventory (fields include `cost_price`, `tax_rate`)
- **vendors**: Supplier details
- **purchases**: Purchase headers (with vendor, date, totals)
- **purchase_items**: Items for each purchase (updates stock and influences moving average cost)
- **sales**: Sales transactions (with discount, tax, and payment breakdown)
- **sale_items**: Individual items in each sale (with tax rate and tax amount)
 - **returns**: Line-level returns associated with sales
 - **refunds**: Refund transactions (cash/card totals per return)
 - **return_receipts**: Stored payloads for return invoice reprints

### Cost Price / Moving Average
- The system stores a `cost_price` on the product and shows an admin-only cost column based on a moving average of recent purchase item prices.
- Purchases increase stock and update the product's cost basis.

### Tax System
- **Tax-inclusive pricing**: Product prices include tax
- **Backward calculation**: Tax calculated from inclusive prices
- **Individual item tax**: Each item shows its tax amount and rate
- **Database storage**: Tax rates and amounts stored per item

### Key Features
- Foreign key relationships for data integrity
- Proper indexing for performance
- Transaction-safe stock updates via purchases/sales
- Stock managed exclusively via purchases (in) and sales (out)
- Concurrency protection with conditional stock updates

## Concurrency & Multi-User Features

### Real-time Stock Management
- **Automatic stock refresh**: Cart updates every 30 seconds
- **Database-level protection**: Conditional UPDATE prevents overselling
- **Stock validation**: Real-time checks before adding to cart
- **Automatic adjustments**: Quantities adjusted if stock becomes insufficient

### Session Management
- **User isolation**: Each user's session is completely separate
- **Unique sale identifiers**: Prevents session data conflicts
- **Cart persistence**: Survives page refreshes and browser restarts
- **Error recovery**: Cart restored automatically on checkout errors

### Transaction Safety
- **Database transactions**: Atomic operations prevent partial sales
- **Rollback on errors**: Failed sales don't affect inventory
- **Stock verification**: Affected rows check ensures stock updates

## Customization

### Styling
The system uses Tailwind CSS for styling. You can customize the appearance by:
1. Modifying Tailwind classes in the HTML
2. Adding custom CSS in the header
3. Replacing Tailwind with your preferred CSS framework

### Adding Features
- **New Modules**: Create new PHP files in the `modules/` directory
- **Database Changes**: Modify `database.sql` and update related PHP code
- **Authentication**: Extend `includes/auth.php` for additional security features

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `includes/db.php`
   - Ensure MySQL service is running
   - Check database name and user permissions

2. **Session Issues**
   - Ensure PHP sessions are enabled
   - Check file permissions for session storage
   - Verify session configuration in php.ini

3. **AJAX Search Not Working**
   - Check jQuery is loaded properly
   - Verify file paths for AJAX requests
   - Check browser console for JavaScript errors

4. **Permission Denied Errors**
   - Set proper file permissions (644 for files, 755 for directories)
   - Ensure web server can read all files
   - Check PHP execution permissions

5. **Invoice Prints Across Multiple Pages**
   - The app includes print CSS to keep invoices on a single page. If you still see extra pages, ensure your browser print scaling is set to "Fit to page" and margins are set to "Default" or "None".

6. **Concurrency Issues**
   - Ensure database supports transactions
   - Check that stock updates are working properly
   - Verify AJAX endpoints are accessible

### Performance Optimization

1. **Database Optimization**
   - Ensure all indexes are created
   - Use prepared statements for all queries
   - Consider query optimization for large datasets

2. **Caching**
   - Implement PHP opcache if available
   - Consider Redis/Memcached for session storage
   - Use browser caching for static assets

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify your hosting environment meets the requirements
3. Review PHP error logs for detailed error messages
4. Ensure all files are uploaded correctly

## License

This project is provided as-is for educational and commercial use. Feel free to modify and distribute according to your needs.

## Changelog

### Version 1.2.0
- **Concurrency protection** - Multi-user support with real-time stock validation
- **Cart persistence** - Cart survives page refreshes and browser restarts
- **Tax system** - Tax-inclusive pricing with individual item tax display
- **Enhanced UI** - Fixed height layout, toggle header, payment modal
- **Real-time features** - Automatic stock refresh, session isolation
- **Invoice improvements** - Thermal printer optimization, tax breakdown
- **Error handling** - Better message display, cart preservation on errors

### Version 1.1.0
- Added Vendors and Purchases modules (with transactional stock updates)
- Added admin-only cost price and moving average display on products
- Improved POS: mixed payments, discount, balance, persistent cart on error
- Invoice tweaks: item rows without currency; reports print with DUPLICATE watermark
- Navigation fixes for module paths; PKR currency across the app

### Version 1.0.0
- Initial release with authentication, products, sales, reports
- Responsive design with Tailwind CSS
