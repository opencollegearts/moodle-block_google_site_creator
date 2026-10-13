# Changelog

## 1.1.1 - 2026-10-13

- Add Moodle 2 backup/restore support for course settings, created site records, and site banner files.

## 1.1.0 - 2026-03-29

- Generalise plugin for community / Marketplace use (remove institution-specific defaults and branding).
- Add Moodle enrolment-based visibility: **Course teachers** and **Course participants** (Drive user shares via email).
- Make Google Groups-based visibility optional (tutor, course, organisation groups only when configured).
- Add `allowedemaildomains` admin setting for ownership transfer eligibility.
- Decouple banner file access from Learning Logs; keep optional discovery helper APIs.
- Complete Privacy API metadata (Google external location, banner files, site fields).
- Remove unused `staffgroupemail` setting and orphaned `admin_settings.php`.
- Migrate legacy visibility values (`tutors`, `unit_group`, `all_oca`) on upgrade.

## 1.0.0

- Initial release: create Google Sites from Drive templates with group-based visibility and web services.
