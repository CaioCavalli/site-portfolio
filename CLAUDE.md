# CLAUDE.md

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

Use `includes/dbconn.example.php`, `includes/auth.example.php` and `includes/geo.example.php` to understand how these files are used. Never ask me to paste real credentials in the chat. Never put secrets in any file that is committed.

Do not touch these folders: `capturadetela/`, `não usados/`, `indexantigos/`.

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