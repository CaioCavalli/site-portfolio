# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Personal portfolio website (PHP). Owner: Caio Cavalli. I am a beginner with Git, so explain what you are doing in plain language and work in small steps.

## About the project

- Pages: Home, About me, Resume, Contact.
- The Contact page has a form; submitted messages are saved to the database.
- The previous version was in Italian/English depending on visitor location. This version must be **English only**. Remove the Italian content and the language-switching logic, but ask me before deleting any files.
- Goal: a cleaner, more professional design, with a focus on data analysis work.

## Files you must NOT read, edit or commit

These contain credentials and are intentionally git-ignored:

- `includes/dbconn.php` (database connection)
- `includes/auth.php` (authenticator)
- `includes/geo.php` (geolocation API)
- `.env`, if it exists

Never ask me to paste real credentials in the chat. Never put secrets in any file that is committed.

Do not touch these folders: `capturadetela/`, `não usados/`, `indexantigos/`.

## Architecture

- No build step, package manager, linter or test suite. Served by WAMP from `C:\wamp64\www\caiosite\portfolio`
  (locally: http://localhost/caiosite/portfolio/). Syntax-check PHP with `php -l <file>`.
- The public site is a single page, `index.php`. Home / About / Resume / Portfolio / Contact are `<section data-id="...">`
  blocks switched client-side by `js/main.js` (menu links use `#about-me`, `#resume`, etc.). The Portfolio section is
  hidden from the menu; its items load `portfolio-N{en,it}.html` via `ajax-page-load`.
- `index.php` includes `includes/geo.php` (provides `$ip`, `$geo`, `$pais` = visitor country) and `includes/dbconn.php`
  (provides `$conn`, a PDO connection). Every page load inserts a row into the `log` table.
- Page text comes from the database: `header` (meta tags), `about` (id 1 = EN, id 2 = IT),
  `resume` (`language` 1 = EN / 2 = IT; `categoria` Education/Formazione, Experience/Esperienza).
  Skills and knowledge lists are hard-coded in `index.php`.
- The contact form posts to `index.php` itself and inserts into the `form` table (name, email, subject, message, ip, geo, dia).
- `admin/` is a separate back office built on the CoolAdmin template (Bootstrap 5, UI text in Portuguese).
  `admin/index.php` is the login (sessions, CSRF token, 5-try lockout, `admin_users` table with `password_hash`).
  Protected pages call `require_login()` from `includes/auth.php`; `admin/topo.php` is the shared menu;
  `admin/message.php` reads the contact-form messages. Several admin pages are unused template demos (e.g. `form.php`).

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