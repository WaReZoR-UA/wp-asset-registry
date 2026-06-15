# Asset Registry

A portfolio-grade WordPress plugin implementing a generic Asset / Equipment Registry. It pairs a custom database table and a role-based capability model with a responsive front-end grid, a custom REST API, server-side PDF spec sheets, and secure authenticated file delivery, built on a pure-logic-first architecture with a comprehensive unit-test suite and continuous integration.

![CI](https://github.com/WaReZoR-UA/wp-asset-registry/actions/workflows/ci.yml/badge.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)

## Live demo

A live, read-only demo is available at **[asset-registry.mediacreator.agency](https://asset-registry.mediacreator.agency/)**.

The front page is the live asset grid. A demo bar offers one-click sign-in as either role:

- **Registry Manager** - full asset CRUD inside the WordPress admin.
- **Registry Viewer** - read-only access to all asset fields.

A logged-out visitor sees the public preview, which exposes only an asset's name, category, and status. The sample data resets daily, so the demo can be explored freely.

<!-- TODO: add screenshots -->

## Features

**Data and access control**

- Custom `wp_ar_assets` database table created on activation via `dbDelta`, modelling a single Asset entity.
- Custom roles and capabilities: Registry Manager (`manage_assets`) and Registry Viewer (`view_assets`).
- Field-level visibility: a logged-out public preview exposes only name, category, and status; authorized viewers see every field.

**Admin**

- A `WP_List_Table` asset list with sortable columns, a status filter, full-text search, pagination, and row actions.
- A validated, nonce-protected add/edit form with duplicate asset-tag detection and attachment upload.
- Delete with confirmation.

**REST API and front end**

- Custom REST namespace `asset-registry/v1` with `GET /assets` and `GET /assets/{id}`, pagination headers, and server-side field visibility applied per request.
- An `[asset_registry]` shortcode and a matching editor block.
- A responsive, vanilla-JS card grid with category and status filters, debounced search, and an accessible asset detail dialog.

**PDF**

- Server-side PDF spec sheets rendered with Dompdf, served only behind a capability check and a per-asset nonce.

**Secure files**

- A traversal-safe protected attachment store served exclusively through an authenticated, capability-checked, nonce-guarded download route.
- The store is relocatable outside the web root via the `ASSET_REGISTRY_PROTECTED_DIR` constant or the `asset_registry_protected_dir` filter, for servers that ignore `.htaccess`.

**Engineering**

- Composer with PSR-4 autoloading.
- PHPUnit and Brain Monkey unit-test suite (108 tests).
- WordPress Coding Standards (PHPCS) ruleset, clean.
- GitHub Actions CI matrix across PHP 8.1 through 8.4.
- Semantic Versioning and a Keep a Changelog history.

## Architecture

The codebase follows a pure-logic-first design. The decision-making core is expressed as plain, side-effect-free PHP that can be unit-tested without a running WordPress: status and category enums, an asset DTO, the input sanitizer, the repository's SQL builders, the field-visibility resolver, the PDF HTML builder, and the traversal-safe path resolution are all pure functions. WordPress integration - hook registration, `$wpdb` queries, REST route registration, the `WP_List_Table` rendering, and file streaming - is kept as a thin shell that delegates to that core.

Key building blocks:

- **Custom table.** A single `wp_ar_assets` table holds the Asset records, created and versioned on activation.
- **REST namespace.** `asset-registry/v1` exposes read endpoints for the grid and detail view, applying field visibility on the server so the response never contains fields the requester is not entitled to see.
- **Capability model.** Two capabilities, `manage_assets` and `view_assets`, gate every privileged action; the public preview is an explicit, narrow projection of the data rather than an unguarded query.
- **Secure files.** Attachments live in a protected store that is never linked directly. Downloads pass through a gated route that checks login, capability, and a nonce before streaming the file, and the store can be moved outside the web root for servers that do not honor `.htaccess` rules.

## Requirements

- PHP 8.1 or later
- WordPress 6.4 or later
- Composer 2.x (to build from source)

## Installation

**From a release (end users)**

1. Download the latest `asset-registry-*.zip` from the [Releases](https://github.com/WaReZoR-UA/wp-asset-registry/releases) page.
2. In the WordPress admin, go to **Plugins -> Add New -> Upload Plugin**, upload the ZIP, and activate it.

**From source**

```bash
git clone https://github.com/WaReZoR-UA/wp-asset-registry.git
cd wp-asset-registry
composer install --no-dev
```

Then copy the directory into `wp-content/plugins/` (or symlink it) and activate the plugin from the WordPress admin.

## Development

```bash
composer install      # install dependencies and register the coding standards
composer test         # run the PHPUnit unit suite
composer lint         # run WordPress Coding Standards (PHPCS)
composer lint:fix     # auto-fix fixable coding-standard issues
```

Work proceeds on feature branches; each change is reviewed via pull request before merging to `main`. CI runs the linter and the test matrix on every push and pull request.

## Manual QA checklist

- [ ] Responsive layout verified at mobile, tablet, and desktop breakpoints.
- [ ] Role boundaries verified for administrator, Registry Manager, Registry Viewer, and logged-out visitor.
- [ ] Public-preview fields (name, category, status only) versus authenticated full-field visibility confirmed.
- [ ] Attachment download denied when logged out.
- [ ] PDF spec sheet denied when logged out.
- [ ] Direct access to a protected file (bypassing the gated route) denied.
- [ ] Activation, deactivation, and uninstall lifecycle leaves no orphaned table, roles, or options.

## License

GPL-2.0-or-later.
