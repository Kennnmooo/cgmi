CGMI IMS - PHP + MySQL Starter Setup

1. Place this folder inside your XAMPP htdocs directory.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import database/cgmi_ims.sql.
4. Visit http://localhost/cgmi/index.php (adjust folder name if needed).

Default test accounts:
- admin / Admin123!
- cashier / Cashier123!
- inventory / Inventory123!

Notes:
- UI was kept as close as possible to the original frontend.
- Current backend foundation includes authentication, sessions, role-based page protection, and starter database tables.
- Inventory, POS, shelves, incidents, reports, and user management are connected to their PHP API endpoints in this package.
