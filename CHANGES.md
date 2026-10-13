# Changelog

## 1.1.8 - 2026-10-13

- Fix create flow aborting under Whoops/debugdisplay: log fallback errors without `debugging()` so create-as-user can fall back to the delegated account.
- Show a clearer message when the template Drive file is not found or not shared with the API account.

## 1.1.7 - 2026-10-13

- Restore default `block/google_site_creator:create` for the student role so the block is visible again; keep course-participants sharing limited to `:managecourse`.

## 1.1.6 - 2026-10-13

- Store service account JSON with `admin_setting_encryptedpassword`; prefer a dataroot file path that is never stored as clear-text secrets in settings UI.
- Rename public GitHub repository to `moodle-block_google_site_creator` (Moodle plugin naming convention).
- Move `create_site_form` into `block_google_site_creator\form` and rely on class autoloading (same as the other forms).

## 1.1.5 - 2026-10-13

- Admin-managed template catalogue (`Title|DriveFileId`); course managers select from the list only.
- Validate Drive file IDs and `rawurlencode()` them in Drive API paths.

## 1.1.4 - 2026-10-13

- Fix visibility sync removing the site creator's writer access; preserve creator/delegated emails and re-ensure writer.
- Store plugin-created Drive permission IDs so later syncs revoke only those grants.

## 1.1.3 - 2026-10-13

- Coding style: use Moodle snake_case parameter/variable names in `drive_service`.
- Add `scripts/build-release-zip.sh` for clean Marketplace ZIPs via `git archive` (no `.git` / `__MACOSX` / nested zip).

## 1.1.2 - 2026-10-13

- Privacy: remove student archetype from `:create`, add `RISK_PERSONAL`, limit Course participants visibility to `:managecourse`.
- Prefer configured Google Groups over individual Moodle emails when resolving Moodle-based visibility.
- Document personal data sent to Google in the README.

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
