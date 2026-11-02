# Caio Cavalli — Personal Portfolio

Personal website of Caio Cavalli: architect by training, working with data analysis.
Live at [caiocavalli.com](https://caiocavalli.com).

## Pages

- **Home** — introduction and overview
- **About me** — who I am and what I do
- **Resume** — my professional path, in my own words (a more detailed take than the PDF resume and my LinkedIn profile)
- **Contact** — a form that lets visitors send me a message, which is saved directly to my database

## Tech stack

- PHP
- A database that stores messages sent through the contact form
- HTML, CSS and JavaScript on the front end

## Project status

This repository is a fresh restart of the site. The current goals for this version:

- Everything in English (the previous version had an Italian/English split based on visitor location)
- A cleaner, more professional visual design
- A stronger focus on data analysis work

## Project structure

```
.
├── includes/                # Shared PHP code (connections, helpers)
│   ├── dbconn.example.php   # Template: database connection
│   ├── auth.example.php     # Template: authentication
│   └── geo.example.php      # Template: geolocation API
├── .claude/settings.json    # Claude Code permissions (protects secret files)
├── CLAUDE.md                # Instructions for Claude Code
└── README.md
```

The real `dbconn.php`, `auth.php` and `geo.php` files hold credentials and API keys. They exist only on my computer and are never committed (see `.gitignore`).

## Running it locally

1. Install PHP and a local web server (for example XAMPP).
2. Copy each `includes/*.example.php` file to the same name without `.example`, for example `dbconn.example.php` → `dbconn.php`.
3. Open the copies and replace the placeholder values with your own credentials.
4. Create the database table used by the contact form.
5. Serve the project folder and open it in your browser.

## Contact

You can reach me through the contact form on the website.
