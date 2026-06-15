# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2026-06-15

### Fixed
- Front-end PDF and attachment download links did not work: the gated URLs were built with HTML-escaped ampersands (`&amp;`), which broke the query string once the link passed through the REST payload into the DOM, so the routes received no asset id and returned 403. Download URLs are now built raw and escaped only where rendered as HTML.

## [1.0.1] - 2026-06-15

### Fixed
- Detail dialog download buttons (PDF and attachment) were not visible: the dialog renders outside the grid container, so the colour palette did not apply to it. The palette is now declared on the dialog scope as well.

## [1.0.0] - 2026-06-15

### Added
- Admin asset management: a list screen with sortable columns, a status filter, full-text search, pagination, and row actions.
- A validated, nonce-protected add/edit form with duplicate asset-tag detection and attachment upload, plus delete with confirmation.
- Custom REST API namespace `asset-registry/v1` exposing `GET /assets` and `GET /assets/{id}` with pagination headers and server-side field visibility: anonymous requests receive only name, category, and status, while authorized viewers receive all fields.
- Front-end `[asset_registry]` shortcode and a matching editor block rendering a responsive card grid with category and status filters, debounced search, and an accessible asset detail view.
- Server-side PDF spec sheets generated on demand, served only behind a capability check and a per-asset nonce.
- Secure attachment storage in a traversal-safe protected store served exclusively through an authenticated, capability-checked, nonce-guarded download route. The store is relocatable outside the web root via the `ASSET_REGISTRY_PROTECTED_DIR` constant or the `asset_registry_protected_dir` filter for servers that ignore `.htaccess`.
- A tagged-release workflow that builds a distributable plugin ZIP and publishes it as a GitHub Release.

## [0.1.1] - 2026-06-14

### Fixed
- Unit-test autoloading on case-sensitive filesystems: the test directory case now matches its PSR-4 namespace, so the test matrix passes on Linux across PHP 8.1-8.4.

### Changed
- CI: updated the checkout action to v5 (Node 24).

## [0.1.0] - 2026-06-13

### Added
- Plugin foundation: PSR-4 autoloading and Composer-managed dependencies.
- Custom `wp_ar_assets` table created on activation via `dbDelta`.
- Custom roles and capabilities: Registry Manager (`manage_assets`) and Registry Viewer (`view_assets`).
- Activation, deactivation, and uninstall lifecycle (table and roles removed on uninstall).
- PHPUnit + Brain Monkey unit-test suite.
- WordPress Coding Standards ruleset and PHP 8.1-8.4 CI matrix.
