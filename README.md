# Asset Registry (WordPress Plugin)

A portfolio-grade WordPress plugin implementing a generic Asset / Equipment
Registry: custom database tables, role-based access control, a responsive
front-end grid with filters and search, server-side PDF spec sheets, and
secure authenticated attachments.

## Status

Phase 1 (foundation & lifecycle) is in place: activation creates the custom
table and roles; deactivation and uninstall clean up. Admin CRUD, the
front-end grid, PDF, and secure files land in subsequent phases.

## Requirements

- PHP 8.1+
- WordPress 6.4+
- Composer 2.x

## Development

```bash
composer install      # install dependencies and register coding standards
composer test         # run the PHPUnit unit suite
composer lint         # run WordPress Coding Standards (PHPCS)
composer lint:fix     # auto-fix fixable coding-standard issues
```

## License

GPL-2.0-or-later.
