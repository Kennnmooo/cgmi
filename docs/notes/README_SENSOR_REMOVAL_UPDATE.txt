Sensor module removal update

This build uses the uploaded cgmi(21).zip as the base.

Changes included:
- Removed the admin Sensor module page, API wrappers, backend API files, JS files, and CSS files.
- Removed Sensor links from the admin sidebar navigation.
- Removed sensor API calls, sensor dropdown, sensor details, and sensor_id updates from the shelves module.
- Shelves still save/load from the database using shelves and inventory_items.
- Dashboard no longer calls the sensors API or renders sensor charts.
- Inventory Reports are displayed before Sales Reports on the dashboard.
- Removed the Smart & Advanced Reports dashboard section.

Database note:
- Existing sensor tables/columns were left in the SQL files to avoid breaking current databases or foreign keys. The application no longer depends on them.
