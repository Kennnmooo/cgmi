# CGMI IMS — Refactored Structure

This refactor reorganizes the project into a cleaner application layout while preserving the original working URLs.

## Main structure

- `app/Core` — shared core PHP files such as auth, helpers, session, app URL helpers, and database config.
- `app/Api` — real API endpoint implementations grouped by feature.
- `app/Views` — real PHP page views grouped by role.
- `assets` — public CSS, JS, and vendor libraries kept in the same public path for compatibility.
- `database` — SQL schema.
- `storage/uploads` — canonical uploaded files location.
- `uploads` — compatibility public upload path retained so existing image links continue to work.
- `docs/notes` — project notes and setup/readme text files.
- `legacy/static-html` — static prototype HTML files moved out of the active app flow.

## Compatibility layer

The original files inside `includes/`, `config/`, `api/`, and `pages/` are still present as thin loaders. Existing links and backend calls continue to work as before.

## Recommended next step

If you later want a deeper cleanup, the next safe step is to centralize reusable page parts into shared partials for headers, sidebars, and footers.
