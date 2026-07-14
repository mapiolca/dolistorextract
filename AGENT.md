# AGENT.md — DolistoreExtract

This repository is the root of the external Dolibarr module `dolistorextract`.

## Scope

- Keep every source change inside this module root.
- Never modify or copy Dolibarr core files.
- Support Dolibarr 20+ and PHP 8.0+ with MySQL/MariaDB.
- Preserve existing administrator settings during activation, deactivation and reactivation.

## Dolibarr integration

- Prefer native Dolibarr objects, hooks, CRUD triggers, permissions, documents, Agenda, Notifications, categories and scheduled jobs.
- Use `isModEnabled()`, `getDolGlobalInt()`, `getDolGlobalString()` and `$user->hasRight()` directly when their native behavior is sufficient.
- Use `price2num($value, 'MU')` for unit prices and `price2num($value, 'MT')` for totals; use native price calculation helpers for commercial lines.
- Keep custom mutation trigger codes limited to `CREATE`, `UPDATE` and `DELETE`; carry business transition details in object context and `oldcopy`.
- Use native CSRF tokens for every mutating POST or sensitive GET action.

## Multicompany and documents

- Every business query and write must enforce the correct entity.
- Use normalized `fk_*` relations instead of copying data already owned by another Dolibarr object.
- Resolve documents from the owning object's entity with native multidir helpers.
- Never write user files or generated documents into the module source tree.

## Quality

- Declare object properties and document stable array shapes and method contracts for static analysis.
- Do not add PHPStan ignores, baselines or global exclusions to hide new errors.
- Provide and maintain `fr_FR` and `en_US` translations with correct spelling and UTF-8 accents.
- Keep lists, forms, pagination, status badges and administration pages visually native to Dolibarr.
- Update `ChangeLog.md` for functional changes and always propose a commit title and description in the delivery report.
