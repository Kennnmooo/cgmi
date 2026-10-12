This package is based on the user's uploaded cgmi(3).zip and was patched to address the clearest backend/UI wiring issues found during inspection.

Key fixes included:
- Incident Reports backend made schema-tolerant for the current incident_reports table.
- Incident submit now writes both old/new incident columns when present.
- Admin incident listing/review now reads the same incident records reliably.
- User Management UI toggle button state fixed.
- User API frontend payload now includes isActive for cleaner UI behavior.

Important:
- This is a targeted repair of the uploaded project, not a ground-up rebuild.
- Please test login, inventory, POS, incidents, user management, shelves and reports in XAMPP.
