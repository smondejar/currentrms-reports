# CurrentRMS Report Builder

A powerful, mobile-friendly report builder and analytics dashboard for CurrentRMS. Built with simple PHP, SQL, CSS, and JavaScript - ready for shared hosting with no compilation required.

![PHP](https://img.shields.io/badge/PHP-8.0+-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

### Report Builder
- **Multiple Modules**: Products, Invoices, Opportunities, Members, Projects, Purchase Orders, Stock Levels, Quarantines
- **Flexible Filtering**: Multiple predicates (equals, contains, greater than, less than, etc.)
- **Column Selection**: Choose which columns to include in reports
- **Sorting**: Sort by any column ascending or descending
- **Save Reports**: Save report configurations for quick access later
- **Public Reports**: Share reports with other users

### Export Options
- **CSV Export**: Excel-compatible with UTF-8 support
- **Excel XML**: Native Excel format without external libraries
- **JSON Export**: For API integration or data processing
- **Print View**: Optimized HTML for printing
- **PDF Export**: Via wkhtmltopdf (when available)

### Analytics Dashboard
- **KPI Cards**: Key metrics at a glance
- **Interactive Charts**: Bar, line, pie, and doughnut charts (Chart.js)
- **Timeline Widgets**: Upcoming events and activities
- **Data Tables**: Quick view of recent records
- **Custom Dashboards**: Per-user widget configuration

### User Management
- **Role-Based Access**: Admin, Manager, User, Viewer roles
- **Granular Permissions**: Control access to reports, exports, settings
- **User Profiles**: Self-service profile management
- **Password Reset**: Secure password recovery

### Mobile Friendly
- **Responsive Design**: Works on desktop, tablet, and mobile
- **Touch Optimized**: Easy navigation on touch devices
- **Collapsible Sidebar**: More screen space on mobile

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- cURL extension enabled
- JSON extension enabled
- PDO MySQL extension

## Installation

### Shared Hosting

1. **Upload Files**: Upload all files to your web hosting via FTP/SFTP

2. **Create Database**: Create a MySQL database using your hosting control panel

3. **Run Installer**: Navigate to `https://yourdomain.com/install/` in your browser

4. **Configure**:
   - Enter your database credentials
   - Enter your CurrentRMS API credentials (Subdomain + API Token)
   - Create your admin account

5. **Done!**: Delete the `/install` folder for security

### Manual Installation

1. Clone or download this repository

2. Create a MySQL database:
   ```sql
   CREATE DATABASE currentrms_reports CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Import the schema:
   ```bash
   mysql -u username -p currentrms_reports < install/schema.sql
   ```

4. Copy config files:
   ```bash
   cp config/database.php.example config/database.php
   cp config/api.php.example config/api.php
   ```

5. Update configuration files with your credentials

6. Create storage directories:
   ```bash
   mkdir -p storage/{cache,logs,uploads}
   chmod 755 storage storage/*
   ```

7. Access the application and login with:
   - Email: `admin@example.com`
   - Password: `admin123`
   - **Change this password immediately!**

## CurrentRMS API Setup

1. Login to your CurrentRMS account
2. Go to **System Setup → Integrations → API**
3. Generate or copy your API Token
4. Note your subdomain (from `yourcompany.current-rms.com`)
5. Enter these in the Report Builder settings

## File Structure

```
currentrms-reports/
├── api/                    # API endpoints
│   ├── modules.php         # Module configuration
│   └── reports/            # Report API endpoints
├── assets/
│   ├── css/style.css       # Main stylesheet
│   └── js/app.js           # JavaScript application
├── config/
│   ├── app.php             # Application config
│   ├── api.php             # CurrentRMS API config
│   └── database.php        # Database config
├── includes/
│   ├── Analytics.php       # Analytics engine
│   ├── Auth.php            # Authentication
│   ├── bootstrap.php       # Application bootstrap
│   ├── CurrentRMSClient.php# API client
│   ├── Dashboard.php       # Dashboard widgets
│   ├── Database.php        # Database wrapper
│   ├── Exporter.php        # Export functionality
│   ├── ReportBuilder.php   # Report builder core
│   ├── ReportManager.php   # Report CRUD
│   └── partials/           # View partials
├── install/
│   ├── index.php           # Installation wizard
│   └── schema.sql          # Database schema
├── storage/                # App storage (logs, cache, uploads)
├── .htaccess               # Apache configuration
├── analytics.php           # Analytics page
├── index.php               # Dashboard
├── login.php               # Login page
├── logout.php              # Logout handler
├── profile.php             # User profile
├── report-view.php         # View saved report
├── reports.php             # Report builder
├── settings.php            # Admin settings
└── users.php               # User management
```

## Available Modules

| Module | Description |
|--------|-------------|
| Products | Equipment, accessories, consumables, services |
| Members | Contacts, organizations, users, venues, vehicles |
| Opportunities | Bookings, quotes, orders |
| Invoices | Customer invoices |
| Projects | Project tracking |
| Purchase Orders | Supplier orders |
| Stock Levels | Inventory by store |
| Quarantines | Items in quarantine |

## Filter Predicates

| Predicate | Description |
|-----------|-------------|
| eq | Equals |
| not_eq | Not equals |
| cont | Contains |
| not_cont | Does not contain |
| start | Starts with |
| end | Ends with |
| lt | Less than |
| lteq | Less than or equal |
| gt | Greater than |
| gteq | Greater than or equal |
| null | Is null |
| not_null | Is not null |

## Permissions

| Permission | Description |
|------------|-------------|
| view_reports | View reports and report builder |
| create_reports | Create new reports |
| edit_reports | Edit existing reports |
| delete_reports | Delete reports |
| export_reports | Export data (CSV, Excel, etc.) |
| view_dashboard | View dashboard |
| edit_dashboard | Customize dashboard widgets |
| manage_users | Manage users and permissions |
| view_analytics | View analytics page |
| system_settings | Access system settings |

## Security

- CSRF protection on all forms
- Password hashing with bcrypt
- Prepared statements for all database queries
- Input sanitization and validation
- Session-based authentication
- Rate limiting recommendations in .htaccess

## Customization

### Adding Custom Modules

Edit `includes/ReportBuilder.php` and add to the `$this->modules` array:

```php
'custom_module' => [
    'name' => 'Custom Module',
    'icon' => 'custom-icon',
    'endpoint' => 'custom_endpoint',
    'columns' => [
        'field_name' => ['label' => 'Field Label', 'type' => 'string'],
    ],
    'filters' => [
        'field_name' => ['label' => 'Field', 'type' => 'text', 'predicates' => ['cont', 'eq']],
    ],
],
```

### Styling

Edit `assets/css/style.css` to customize colors, fonts, and layout. CSS variables at the top make it easy to change the color scheme.

## Troubleshooting

### API Connection Failed
- Verify your subdomain and API token
- Check that cURL is enabled in PHP
- Ensure SSL is properly configured

### Database Connection Failed
- Verify database credentials
- Check that PDO MySQL extension is installed
- Ensure database exists and user has permissions

### Reports Show No Data
- Check CurrentRMS API has data for the selected module
- Verify filters aren't too restrictive
- Check API rate limits

## Support

For issues and feature requests, please create an issue on GitHub.

## License

MIT License - feel free to use in personal and commercial projects.

## Credits

- [Chart.js](https://www.chartjs.org/) - Beautiful charts
- [CurrentRMS](https://www.current-rms.com/) - Rental management system
- Built with vanilla PHP, CSS, and JavaScript

---

**Sources:**
- [CurrentRMS API Documentation](https://api.current-rms.com/doc)
- [CurrentRMS Postman Collection](https://documenter.getpostman.com/view/4811107/SzS5wSad)
- [CurrentRMS Help Center](https://help.current-rms.com/en/collections/34939-api)
