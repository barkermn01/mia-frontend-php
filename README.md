# Mia Storefront

A simple PHP storefront built using only the Mia SDK and memcached for caching. No templating frameworks - just pure PHP with clean separation of concerns.

*Currently configured for OxWinches - Premium winches and marine equipment store.*

## Features

- **Product Catalog**: Browse and search products with pagination
- **Shopping Cart**: Add, update, and remove items from cart
- **Customer Authentication**: Login, register, and account management
- **Order Management**: View order history for authenticated customers
- **Saved Baskets**: Save and load shopping carts (authenticated users)
- **Caching**: Memcached integration for improved performance
- **Responsive Design**: Mobile-friendly CSS without frameworks

*Currently themed for marine equipment and winches.*

## Requirements

- PHP 8.4+
- Memcached extension
- Mia SDK access
- Web server with URL rewriting (Apache/Nginx)

## Quick Start

### Using PowerShell (Recommended)
```powershell
# Quick start with default settings
.\start.ps1

# Custom port and host
.\start.ps1 -Port 3000 -Host 0.0.0.0

# Production mode
.\start.ps1 -Production

# Skip memcached check
.\start.ps1 -SkipMemcached

# Get help
.\start.ps1 -Help
```

### Using Batch File (Windows)
```cmd
# Simple start (uses default port 8080)
start.bat
```

### Manual Setup
If you prefer manual setup or the scripts don't work:

### Manual Setup

1. **Install Dependencies:**
   ```bash
   composer install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your Mia API credentials
   ```

3. **Set up web server:**
   - Point document root to `public/` directory
   - Ensure URL rewriting is enabled
   - For Apache, `.htaccess` is included

4. **Start the development server:**
   ```bash
   # Navigate to public directory
   cd public
   
   # Start PHP built-in server
   php -S localhost:8080 -t . index.php
   ```

5. **Open your browser:**
   - Visit: http://localhost:8080

## Development Setup Helper

For first-time setup, use the development configuration helper:

```powershell
# Check current environment
.\dev-config.ps1

# Get PHP installation help
.\dev-config.ps1 -InstallPHP

# Get Composer installation help
.\dev-config.ps1 -InstallComposer

# Get Memcached installation help
.\dev-config.ps1 -InstallMemcached
```

## Startup Scripts

### PowerShell Script (`start.ps1`)
The main startup script with full feature support:

**Features:**
- ✅ Dependency checking (PHP, Composer, extensions)
- ✅ Environment validation
- ✅ Memcached connectivity test
- ✅ Automatic .env creation
- ✅ Colored output and progress indicators
- ✅ Production/development modes
- ✅ Configurable host and port
- ✅ Comprehensive error handling

**Usage:**
```powershell
.\start.ps1 [OPTIONS]

Options:
  -Port <port>        Port to run server on (default: 8080)
  -Host <host>        Host to bind to (default: localhost)
  -Production         Run in production mode
  -SkipMemcached      Skip memcached startup check
  -Help               Show help message

Examples:
  .\start.ps1                          # Start on localhost:8080
  .\start.ps1 -Port 3000               # Start on port 3000
  .\start.ps1 -Host 0.0.0.0 -Port 80  # Start on all interfaces
  .\start.ps1 -Production              # Production mode
```

### Batch Script (`start.bat`)
Simple Windows batch file for quick startup:

**Features:**
- ✅ Basic dependency checking
- ✅ Automatic .env creation
- ✅ Simple error handling
- ✅ Works on older Windows systems

### Development Helper (`dev-config.ps1`)
Environment setup and configuration helper:

**Features:**
- ✅ Environment status checking
- ✅ Installation instructions for dependencies
- ✅ PHP extension validation
- ✅ Memcached connectivity testing

## Configuration

### Environment Variables

- `MIA_API_URL`: Mia API endpoint (default: https://api.miaai.me)
- `MIA_SITE_ID`: Your site ID (required)
- `MEMCACHED_HOST`: Memcached server host (default: localhost)
- `MEMCACHED_PORT`: Memcached server port (default: 11211)
- `CACHE_TTL`: Cache time-to-live in seconds (default: 300)

### Mia SDK Permissions

This storefront uses only public and site_customer permission endpoints:

**Public Endpoints (no authentication):**
- Product browsing and search
- Cart management (create, add, update, remove)
- Customer registration
- Checkout configuration
- Stock information

**Site Customer Endpoints (authenticated):**
- Customer profile management
- Order history
- Saved basket management

## Architecture

### File Structure

```
├── public/
│   ├── index.php          # Entry point
│   └── .htaccess          # Apache rewrite rules
├── src/
│   ├── Storefront.php     # Main application class
│   └── templates/         # PHP templates
│       ├── layout.php     # Base layout
│       ├── home.php       # Homepage
│       ├── products.php   # Product listing
│       ├── product.php    # Product details
│       ├── cart.php       # Shopping cart
│       ├── login.php      # Customer login
│       ├── register.php   # Customer registration
│       ├── account.php    # Customer account
│       ├── orders.php     # Order history
│       ├── checkout.php   # Checkout process
│       ├── 404.php        # Not found page
│       └── error.php      # Error page
├── .env.example           # Environment configuration
└── composer.json          # Dependencies
```

### Key Components

1. **Storefront Class**: Main application controller handling routing and business logic
2. **Templates**: Pure PHP templates with no framework dependencies
3. **Caching**: Memcached integration for product data and API responses
4. **Session Management**: PHP sessions for cart and authentication state

## Usage

### Basic Routing

The application handles these routes:

- `GET /` - Homepage with featured products
- `GET /products` - Product listing with search and pagination
- `GET /product?id={id}` - Product details page
- `GET|POST /cart` - Shopping cart management
- `GET|POST /login` - Customer authentication
- `GET|POST /register` - Customer registration
- `GET /account` - Customer account dashboard
- `GET /orders` - Order history
- `GET /checkout` - Checkout process
- `GET /logout` - Customer logout

### API Integration

All API calls go through the Mia SDK:

```php
// Initialize client
$client = new MiaClient([
    'apiUrl' => 'https://api.miaai.me',
    'siteId' => 'your-site-id'
]);

// Get products
$products = $client->products->getProducts(['limit' => 12]);

// Add to cart
$client->cart->addToCart($cartId, $sku, $quantity);

// Customer login
$result = $client->login([
    'email' => $email,
    'password' => $password,
    'siteId' => $siteId
]);
```

### Caching Strategy

Product data is cached in memcached to reduce API calls:

```php
$cacheKey = 'products_' . md5(serialize($filters));
$cached = $this->cache->get($cacheKey);

if ($cached === false) {
    $products = $this->client->products->getProducts($filters);
    $this->cache->set($cacheKey, $products, $this->config['cache_ttl']);
}
```

## Customization

### Styling

All CSS is inline in `src/templates/layout.php`. To customize:

1. Extract CSS to separate file in `public/css/`
2. Update layout template to include external stylesheet
3. Modify styles as needed

### Adding Features

To add new functionality:

1. Add route handling in `Storefront::handleRequest()`
2. Create corresponding method in `Storefront` class
3. Add template file in `src/templates/`
4. Update navigation in `layout.php` if needed

### Template System

Templates use PHP's output buffering:

```php
<?php
$title = 'Page Title';
ob_start();
?>

<h1>Page Content</h1>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
```

## Security Considerations

- All user input is escaped with `htmlspecialchars()`
- CSRF protection should be added for production use
- HTTPS should be enforced in production
- Session security headers are recommended
- Input validation is handled by the Mia SDK

## Performance

- Memcached caching reduces API calls
- Static asset caching via `.htaccess`
- Minimal CSS/JS for fast loading
- Efficient database queries through Mia SDK

## Deployment

For production deployment:

1. Set up proper web server configuration
2. Configure SSL/TLS certificates
3. Set production environment variables
4. Enable error logging
5. Set up monitoring and backups
6. Configure memcached clustering if needed

## Troubleshooting

### Common Issues

1. **Cart not persisting**: Check PHP session configuration
2. **Products not loading**: Verify MIA_SITE_ID and API connectivity
3. **Caching issues**: Restart memcached service
4. **Authentication failing**: Check API credentials and site permissions

### Debug Mode

Enable debug mode by setting in your environment:
```php
$client = new MiaClient([
    'debug' => true,
    // ... other config
]);
```

## License

This project is provided as an example implementation. Check the Mia SDK license for usage terms.