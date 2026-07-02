# MES Approvals System

Web application for managing LARP character approvals for the [Modern Enigma Society](https://www.modernenigmasociety.org/). Storytellers submit and review applications through a multi-level hierarchy (VSS → Domain → Region → Nation → Globe). Characters, XP, and VSS documents are also managed here.

**User and admin documentation:** see [ADMIN_GUIDE.md](ADMIN_GUIDE.md).

---

## Tech stack

- PHP 8.3
- MySQL (AWS RDS)
- nginx + PHP-FPM
- OAuth2 login via MES Portal
- jQuery (CDN)

---

## First-time setup

1. **Clone the repo**
   ```bash
   git clone git@github.com:MindsEyeSociety/approvals_rewrite.git
   cd approvals_rewrite
   ```

2. **Create `include/settings.inc`**

   Copy the example file and fill in real credentials:
   ```bash
   cp include/settings.inc.example include/settings.inc
   # Edit include/settings.inc — never commit this file
   ```

   `settings.inc` is gitignored. It must be placed on every server manually (see [Deployment](#deployment)).

3. **Run database migrations**

   Import any `.sql` files in `sql/` that have not yet been applied:
   ```bash
   mysql -h <host> -u <user> -p approvals_2017 < sql/add_character_sheet_versions.sql
   ```

4. **Configure nginx**

   Point the server root at the repo directory. A minimal site block:
   ```nginx
   server {
       server_name your-domain.example.com;
       root /var/www/approvals_rewrite;
       index index.php;

       location / { try_files $uri $uri/ =404; }

       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

5. **Allow the PHP-FPM user to run git** (needed for the dynamic build date in the footer)
   ```bash
   sudo git config --system --add safe.directory /var/www/approvals_rewrite
   ```

---

## Configuration reference

All settings live in `include/settings.inc` (gitignored). See `include/settings.inc.example` for the full template. Key settings:

| Key | Description |
|-----|-------------|
| `APPROVALS_SERVER` | Hostname of the MySQL server |
| `APPROVALS_USERNAME` | Database username |
| `APPROVALS_PASSWORD` | Database password |
| `APPROVALS_DATABASE` | Approvals database name (default: `approvals_2017`) |
| `PORTAL_SERVER` | Hostname for the MES Portal database (read-only) |
| `PORTAL_USERNAME` | Portal database username |
| `PORTAL_PASSWORD` | Portal database password |
| `PORTAL_DATABASE` | Portal database name (default: `mes-portal`) |
| `OAUTH_CLIENT_ID` | OAuth2 client ID — obtain from MES Portal admin |
| `OAUTH_CLIENT_SECRET` | OAuth2 client secret |
| `OAUTH_REDIRECT_URI` | Full URL to `oauth_callback.php` on this server |
| `OAUTH_TOKEN_URL` | OAuth2 token endpoint (usually the MES Portal) |
| `OAUTH_API_URL` | OAuth2 user info endpoint |
| `GOOGLE_MAP_SYNC_ENABLED` | Turn the org→Google Sheet map sync on/off |
| `GOOGLE_SA_KEY_FILE` | Absolute path to the Google service-account JSON key (**store outside the web root**) |
| `GOOGLE_MAP_SHEET_ID` | ID of the Sheet that backs the domains map |
| `GOOGLE_MAP_SHEET_TAB` | Tab the sync owns (columns A:C) |

---

## Google map sync

Organization changes can be pushed to the Google Sheet that backs the public "domains" map
(Google My Maps). Enabled via the `GOOGLE_*` settings above. Setup, security notes, and the CLI
seed/re-sync tool (`utility/sync_org_map.php`) are documented in
[docs/google-map-sync.md](docs/google-map-sync.md).

---

## Deployment

The repo uses SSH-based git auth. The deploy user must have the SSH key registered with GitHub.

```bash
cd /var/www/approvals_rewrite
git pull
```

`include/settings.inc` is **not** in the repo and must be copied to the server separately whenever credentials change:

```bash
scp include/settings.inc user@server:/var/www/approvals_rewrite/include/settings.inc
```

---

## Super user access

Super user status cannot be granted through the web UI. It must be set directly in the `users` table:

```sql
UPDATE users SET super_user = 1 WHERE ww_number = 'US2012012345';
```

See [ADMIN_GUIDE.md §9](ADMIN_GUIDE.md#9-super-user-capabilities) for a full description of what super users can do.

---

## Feedback and bugs

Report issues via the [MES contact page](https://www.modernenigmasociety.org/contact-us/).
