# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Personal portfolio website (PHP). Owner: Caio Cavalli. I am a beginner with Git, so explain what you are doing in plain language and work in small steps.

## About the project

- Visible pages: About me, Resume, Contact. Home and Portfolio are hidden for now (commented out, not deleted).
- The Contact page has a form; submitted messages are saved to the database.
- The site is **English only**. The Italian content and the language-switching logic were removed
  (old Italian rows still exist in the database but are never read).
- Goal: a cleaner, more professional design, with a focus on data analysis work. The redesign of the public site
  is done in small steps (see the plan agreed in the conversation).
- When a file is no longer used, I move it to `admin/não usados/` myself; ask me before deleting files.

## Files you must NOT read, edit or commit

These contain credentials and are intentionally git-ignored:

- `includes/dbconn.php` (database connection)
- `includes/auth.php` (authenticator)
- `includes/geo.php` (geolocation API)
- `.env`, if it exists

Never ask me to paste real credentials in the chat. Never put secrets in any file that is committed.

Do not touch these folders (at any level, including `admin/capturadetela/` and `admin/não usados/`):
`capturadetela/`, `não usados/`, `indexantigos/`. They are git-ignored and must never be uploaded to the server.

## Architecture

- No build step, package manager, linter or test suite. Served by WAMP from `C:\wamp64\www\caiosite\portfolio`
  (locally: http://localhost/caiosite/portfolio/). Syntax-check PHP with `php -l <file>`.
### Public site
- A single page, `index.php` (BreezyCV template, jQuery). The sections are `<section data-id="...">` blocks switched
  client-side by `js/animating.js`; the site opens on the first section in the HTML. The Home section and its menu item
  are commented out, so the site opens on About me. The Portfolio section is hidden from the menu; its items load
  `portfolio-Nen.html` via `ajax-page-load`.
- `index.php` sets safe defaults, then includes `includes/geo.php` (provides `$ip`, `$geo`) and `includes/dbconn.php`
  (provides `$conn`, a PDO connection), both inside try/catch. All page queries go through `db_one()` / `db_all()`,
  which log errors and return an empty result, so visitors never see technical details.
- Every page load inserts a row into the `log` table, unless the browser has the cookie `skip_visit_log=1`
  (set from the admin dashboard so the owner's own visits are not counted).
- The `geo` column is a `key=value;` string from the geolocation API (lat, lon, city, country, countryCode, status,
  isp, org, as, ...). The user agent is always last, after `navegador=`, and may itself contain `;`.
- Page text comes from the database: `header` (meta tags), `about` (id 1 = EN), `resume` (`language` 1 = EN;
  `categoria` Experience, then Education, each ordered by `data_ref` DESC; the page title is `main_title_1`).
  Database text may contain HTML on purpose and is printed unescaped (only the owner edits it).
  Skills and knowledge lists are hard-coded in `index.php`.
- The contact form posts to `index.php` itself and inserts into the `form` table (name, email, subject, message, ip, geo, dia).
  Times are stored in the `Europe/Rome` time zone.

### Admin (`admin/`)
- Back office built on the CoolAdmin template (Bootstrap 5). Real pages: `dashboard.php` (visit and message stats),
  `visits.php` (visit log with filters), `map.php` (visitor map, Leaflet), `message.php`, `about.php` and
  `resume.php` (edit the site text), `account.php` (change e-mail/password). `inbox.php` is still a template demo.
- `admin/index.php` is the login (session, CSRF token, `admin_users` table with `password_hash`). Failed logins are
  stored in the `login_attempts` table and limited per IP and globally.
- Protected pages call `require_login()` from `includes/auth.php` and set `date_default_timezone_set('Europe/Rome')`.
  `admin/topo.php` is the shared menu and header; its variables are prefixed with `topo` to avoid clashing with the page.
- `admin/visit-helpers.php` holds the shared visit logic: `parse_geo()`, bot detection (`is_bot()`, user agent),
  cloud/hosting detection (`is_cloud()`, `isp`/`org` only, never `as`), `visit_type()` and `sql_exclude_bots()`.
- Admin forms use the session token `$_SESSION['csrf_admin']` and Post/Redirect/Get.

### Security files
- `.htaccess` (root): security headers, no directory listing, blocks hidden files, `.md`, `.sql`, logs and backups.
- `includes/.htaccess` and `admin/.htaccess`: block direct access to include-only files.
- `.user.ini`: turns off `display_errors` on PHP-FPM/CGI hosts.

## Deployment

- Hosting is an iFastNet-type shared host. Upload files by FTP into `htdocs/` (never into the account root, and never
  replace the host's read-only `.htaccess` there). The server needs PHP 8.
- The host shows an anti-bot JavaScript challenge, so the live site cannot be tested with `curl`; test in a browser.
- New database tables: Claude writes the SQL, and I run it in phpMyAdmin, locally and on the server.

## Working rules

- Start in plan mode: propose a plan and wait for my approval before changing files.
- Do one task at a time. Do not redesign the whole site in a single step.
- Work on a separate branch (for example `redesign`), never directly on `main`.
- Do not change the database structure and do not delete files without asking me first.
- After each change, tell me which files were modified and how to check the result.

## Security (the contact form writes to the database)

- Use prepared statements (PDO or mysqli) for every database query. Never build queries by concatenating user input.
- Validate and sanitize all form input on the server and escape output with `htmlspecialchars`.
- Add CSRF protection and basic spam protection to the form.
- Do not display database or server error details to visitors.

## Git conventions

- Write all commit messages in English, imperative mood, short and specific (max ~50 characters, no trailing period). Example: `Add contact form validation`.
- Never commit or push without asking me first. I run the Git commands myself.

## Language

- Always reply to me in Brazilian Portuguese (pt-BR).
- Keep code, code comments, file contents (README, etc.) and commit messages in English.