# Google map (Sheet) sync

The public "domains" map is a Google **My Maps** map whose layer reads from a backing Google
**Sheet**. This app keeps that Sheet in sync with the database: whenever an organization is
added, edited, or deleted, it rewrites the Sheet from the current active organizations
(`Name, City, State, RST Contact`, ordered by name). It authenticates to the Google Sheets API
v4 with a **service account** and needs no Composer packages (uses PHP's built-in `openssl`,
`curl`, `json`).

The **RST Contact** column is the role-based contact email of the region each org sits in (the
region-level org's `email`, e.g. `wrrst@wr.modernenigmasociety.org`), so every mapped location
shows a way to reach a storyteller. It is blank for orgs not within a region (nation/globe level).

## How it works

- `classes/GoogleSheetsService.php` — signs a service-account JWT, exchanges it for an access
  token, then `clear`s columns `A:D` of the target tab and writes a header + one row per org.
- `OrganizationDAO::getMapRows()` — mappable orgs as `org_name, city, state`, plus `region_contact`
  (the region-level org's `email`) resolved from each org's globe/nation/region. Only real
  **domain-level** orgs are included: region/nation/globe entries (empty domain) and virtual/test
  entries are excluded.
- The org handlers (`ModifyOrgListAddOrg.php`, `ModifyOrgList4.php`, `ModifyOrgList2.php`) call
  `GoogleSheetsService::syncOrgMap()` after their write. It's **best-effort**: any failure is
  logged (`error_log`) and never blocks the org save. Because each call is a full refresh, a
  missed/failed sync self-heals on the next change or via the CLI script.
- `utility/sync_org_map.php` — force a full sync (initial seed / cron / manual). Ignores the
  enable flag so it can seed before you flip it on.

The sync **owns columns A:D of the configured tab** and overwrites them on every run. Do not put
manually maintained data in those columns; if the Sheet has other content, point
`GOOGLE_MAP_SHEET_TAB` at a dedicated tab and set the My Maps layer to read from it.

## One-time Google setup

1. In the [Google Cloud console](https://console.cloud.google.com/), create (or pick) a project.
2. **APIs & Services → Enable APIs** → enable **Google Sheets API**.
3. **IAM & Admin → Service Accounts → Create service account**. No project roles are needed.
4. On the service account, **Keys → Add key → JSON**. Download the key file.
5. Open the target Sheet and **Share** it with the service account's `client_email`
   (e.g. `name@project.iam.gserviceaccount.com`) as **Editor**.
6. Copy the Sheet ID from its URL: `https://docs.google.com/spreadsheets/d/<SHEET_ID>/edit`.

## Server configuration

1. Put the JSON key **outside the web root**, e.g. `/etc/approvals/google-sa.json`, readable by
   the PHP-FPM user. Never place it under the docroot.
2. In `include/settings.inc` set:
   ```php
   $SETTINGS["GOOGLE_MAP_SYNC_ENABLED"] = true;
   $SETTINGS["GOOGLE_SA_KEY_FILE"]      = "/etc/approvals/google-sa.json";
   $SETTINGS["GOOGLE_MAP_SHEET_ID"]     = "<SHEET_ID>";
   $SETTINGS["GOOGLE_MAP_SHEET_TAB"]    = "Sheet1"; // the tab the sync owns
   ```
3. Seed the Sheet: `php utility/sync_org_map.php`
4. In My Maps, refresh the layer (or re-import) so it reflects the Sheet.

## Verifying

- `php utility/sync_org_map.php` prints the org count and `OK`; the Sheet shows the header plus
  every active org, with accents rendering correctly.
- Add/edit/delete an org in the UI and confirm the Sheet updates.
- To confirm best-effort safety, point `GOOGLE_SA_KEY_FILE` at a bad path: org saves still
  succeed and the failure appears only in the PHP error log.
