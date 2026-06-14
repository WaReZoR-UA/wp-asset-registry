# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-13

### Added
- Plugin foundation: PSR-4 autoloading and Composer-managed dependencies.
- Custom `wp_ar_assets` table created on activation via `dbDelta`.
- Custom roles and capabilities: Registry Manager (`manage_assets`) and Registry Viewer (`view_assets`).
- Activation, deactivation, and uninstall lifecycle (table and roles removed on uninstall).
- PHPUnit + Brain Monkey unit-test suite.
- WordPress Coding Standards ruleset and PHP 8.1-8.4 CI matrix.
