# Google Site Creator (Moodle block)

<p align="center">
  <img src="pix/oca_logo.png" alt="Open College of the Arts (OCA) logo" width="120" height="120">
</p>

Block that lets course users create a Google Site from a Google Drive template (file copy), choose who can access it, and store a listing record in Moodle.

**Author:** Paul Vincent  
**Developed by:** [Open College of the Arts (OCA)](https://www.oca.ac.uk/)  
**Component:** `block_google_site_creator`  
**Requires:** Moodle 4.0 or later (tested with currently maintained branches such as 4.5)  
**License:** GNU GPL v3 or later

## Requirements

- Moodle 4.0+
- A Google Cloud project with the **Google Drive API** enabled
- A Google service account JSON key (and, for ownership transfer / acting as users, Google Workspace **domain-wide delegation**)
- Optional: Google Groups if you want group-based sharing in addition to Moodle enrolment-based sharing

This plugin does **not** require Composer or other PHP dependency managers. It talks to the Drive API using Moodle’s `\curl` helper and OpenSSL JWT signing.

## Installation

1. Place the plugin folder at `blocks/google_site_creator/` (folder name must be `google_site_creator`).
2. Visit **Site administration → Notifications** to install the block and create its database tables.
3. Purge caches if you update language strings or code on an existing site.

### Building a Marketplace release ZIP

Do **not** zip the folder with macOS Finder (that adds `__MACOSX/`), and do **not** include `.git/` or a nested `.zip`.

From a clean git checkout (commit your changes first when possible):

```bash
./scripts/build-release-zip.sh
```

This runs `git archive` with prefix `google_site_creator/` and writes the ZIP **outside** the plugin directory (for example `../block_google_site_creator-1.1.3-2026032903.zip`). Equivalent manual command when `HEAD` is clean:

```bash
git archive --format=zip --prefix=google_site_creator/ -o ../block_google_site_creator.zip HEAD
```

## Configuration

### Site administration

**Site administration → Plugins → Blocks → Google Site Creator**

| Setting | Purpose |
|---------|---------|
| **Template catalogue** | Admin-managed list of allowed templates, one per line as `Title\|DriveFileId`. Only these IDs can be used |
| **Default template** | Catalogue entry used when a course has not chosen a specific template |
| **Service account JSON file path** | Preferred. Path to the key file under `$CFG->dataroot` (outside the web root) |
| **Service account JSON (encrypted)** | Optional. Paste JSON once; Moodle encrypts it and does not show it again in clear text |
| **Delegated Workspace user** | Optional email to impersonate via domain-wide delegation |
| **Allowed email domains for ownership** | Optional comma-separated domains eligible for automatic ownership transfer. Empty = any valid user email |
| **Tutor Google Group email** | Optional. Enables the “Tutor Google Group” visibility option |
| **Organisation Google Group email** | Optional. Enables the “Organisation Google Group” visibility option |

Google Group settings are **optional**. Institutions that share only via Moodle enrolments can leave them empty.

Drive file IDs must match `^[A-Za-z0-9_-]{10,128}$`. Free-text / arbitrary Drive IDs are rejected so teachers cannot copy files outside the catalogue via the delegated account.

### Course settings (teachers)

Add the block to a course, then use **Course settings** in the block footer (capability **Manage course Google Site settings**):

| Setting | Purpose |
|---------|---------|
| **Course template** | Optional selection from the admin catalogue (or use the site default) |
| **Course Google Group** | Optional Google Group email for this course. Enables the “Course Google Group” visibility option |

## Visibility and sharing

When a user creates or edits a site they choose a **visibility** mode. Sharing is applied as Google Drive permissions (no notification emails).

**Prefer Google Groups** whenever your institution can use them. Group sharing grants access via a single group address and avoids listing every participant’s personal email in the Drive sharing UI (visible to writers and the owner).

### Capability defaults

| Capability | Default archetypes | Notes |
|------------|-------------------|--------|
| `block/google_site_creator:create` | student, teacher, editingteacher, manager | Marked `RISK_PERSONAL` because sharing may send emails to Google |
| `block/google_site_creator:managecourse` | editingteacher, manager | Required for course settings and for **Course participants (Moodle)** visibility |

Sites that must not allow student-created sites can revoke `:create` from the student role.

### Moodle-based options

| Option | Who can select it | Recipients |
|--------|-------------------|------------|
| **Course teachers (Moodle)** | Users with `:create` | If a **Tutor Google Group** is configured, that group is used. Otherwise: active enrolled users with `moodle/course:manageactivities` (`type=user`) |
| **Course participants (Moodle)** | Users with `:managecourse` only | If a **Course Google Group** is configured, that group is used. Otherwise: all active enrolled users with a valid email (`type=user`) |

Default for new sites: **Course teachers (Moodle)**.

Individual-email fallbacks issue one Drive API call per recipient and may be slower or hit Google quotas on large courses.

### Google Groups-based (optional, preferred)

These options appear only when the corresponding Google Group email is configured:

| Option | Recipients |
|--------|------------|
| **Tutor Google Group** | Site admin tutor group email (`type=group`, writer) |
| **Course Google Group** | Course setting group email (`type=group`, writer) |
| **Organisation Google Group** | Site admin organisation group email (`type=group`, reader) |

## Web services

Two external functions are available for REST/admin integration:

1. **`block_google_site_creator_get_site_details`** — parameters: `siteid`  
2. **`block_google_site_creator_create_site`** — parameters: `courseid`, `userid`, `title`, optional `templateid`, `visibility`  
   `templateid` must be an ID from the admin template catalogue.  
   Visibility values: `course_teachers`, `course_participants`, `tutor_group`, `course_group`, `organisation_group`  
   Legacy aliases still accepted: `tutors`, `unit_group`, `all_oca`

Enable via **Site administration → Plugins → Web services**, add the functions to a service, and create a token for a user with `block/google_site_creator:create_for_others`.

## Optional discovery helpers

Third-party plugins may call:

- `block_google_site_creator_get_sites_for_index($limit)` — sites with organisation-group visibility  
- `block_google_site_creator_build_cards_for_grid($sites)` — card objects for listing UIs  

There is **no hard dependency** on any other contributed plugin.

## Privacy

### Data stored in Moodle

- Created site records: user id, course id, title, description, Drive file id, URL, visibility, timestamps
- Optional banner images in the course file area
- Course settings: template id and optional Course Google Group email

### Personal data sent to Google

When a site is created or its visibility is updated, the plugin calls the Google Drive API and may transmit:

| Data | When |
|------|------|
| Site title and description | Create / metadata update |
| Creator email | Ownership transfer or create-as-user (if eligible) |
| **Recipient email addresses** | Sharing: either a Google Group email, or (fallback) individual Moodle user emails for teachers/participants |
| Delegated Workspace user email | If domain-wide delegation is configured |

Google Drive shows the sharing list to the file owner and to users with writer access. That is why this plugin:

1. Marks `:create` with `RISK_PERSONAL`
2. Restricts **Course participants (Moodle)** (individual emails) to `:managecourse`
3. Prefers Google Group sharing over individual addresses when a matching group is configured

See the in-plugin Privacy API provider for the metadata declared to Moodle administrators.

## Support

- Source and issues: [opencollegearts/moodle-block_google_site_creator](https://github.com/opencollegearts/moodle-block_google_site_creator)
- Contributions welcome under GPL v3+

## Credits

Developed for the Open College of the Arts (OCA) by Paul Vincent. The OCA logo in `pix/` identifies the developing organisation and is used as the plugin icon in Moodle’s plugin overview.
