# Changelog

All notable changes to this project are documented in this file.

## Planned / Future

- Add FAQ icon link to topbar user-manual page, contextualized to the currently open page.
- Add AI Advisor rotator text in topbar to surface system insights and latest user issues.
- Redesign topbar with a darker "PowerBar" concept.
- Build notification module and add topbar notifications dropdown (similar to settings) showing the latest 5 notifications.

## [1.1.0] - 2026-03-11

### Added
- Global admin toast system with typed variants (`success`, `error`, `info`) and shared composable API.
- Global admin confirmation dialog service for destructive/reset actions.
- New reusable UI components for toast and confirm dialog mounting at app level.
- Toast showcase section in Kitchen Sink with live success/info/error triggers.

### Changed
- Admin topbar now supports inline toast presentation and sticky behavior for persistent feedback visibility.
- Admin menu model expanded to support depth-3 hierarchy (`parent > child > grandchild`).
- Sidebar navigation rendering updated for recursive active/open behavior across nested levels.
- `/admin/menus` redesigned to support reorder + hide controls at group/item/child/grandchild levels.
- Compact sidebar mode added and moved into topbar settings dropdown.
- Core action flows updated to use toast + confirm patterns (save, delete, upload, reset/hide-risk actions).

### Backend & Contracts
- `adminMenuPrefs` schema expanded with nested preference fields:
  - `childOrder`
  - `grandchildOrder`
  - `hiddenChildren`
  - `hiddenGrandchildren`
- Backward compatibility maintained for previously stored menu preferences.

### UX Polish
- Refined topbar toast motion, timing, and title/visibility choreography.
- Updated spacing/alignment behavior for site title/profile/topbar elements.

## [1.0.0] - 2026-03-01

### Added
- Initial full-stack CMS foundation with Vue 3 admin app and Express API server.
- Authentication flow, role-based admin capabilities, and core dashboard experience.
- Content management modules for posts, pages, categories, menus, settings, and media.
- Media metadata handling and development support screens/tools.
- Monorepo setup scripts for clean local install and bootstrapping.

### Changed
- Consolidated release for CMS main dashboard, media metadata improvements, and development tooling cleanup.
- UI refinements across early admin screens (login, profile/settings, sidebar/header behavior).

## Historical Notes (Pre-1.0.0)

- Early milestone commits introduced:
  - Base CMS scaffold and API/app wiring.
  - Sidebar/header and settings UX iterations.
  - User CRUD and shared user edit flows.
  - Theme color picker and login UI modernization.

[1.1.0]: https://github.com/mfauzzury/corrad-laravel/releases/tag/v1.1.0
[1.0.0]: https://github.com/mfauzzury/corrad-laravel/releases/tag/v1.0.0
