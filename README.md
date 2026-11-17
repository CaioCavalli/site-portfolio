# Caio Cavalli — Personal Portfolio

Personal website of Caio Cavalli: architect by training, working with data analysis.
Live at [caiocavalli.com](https://caiocavalli.com).

## Pages

- **About me** — who I am and what I do
- **Resume** — my professional path, in my own words (a more detailed take than the PDF resume and my LinkedIn profile)
- **Contact** — a form that lets visitors send me a message, which is saved directly to my database

## Admin panel

A private back office (login required) where I manage the site and look at its data:

- **Dashboard** — visits today, last 7 and 30 days, unique visitors, messages, visits per day and visits by country
- **Visits** — the full visit log (date, location, browser and operating system), filtered by period
- **Visitor map** — where visits come from, grouped by city
- **Bot filtering** — visits from crawlers and from cloud/hosting providers are separated from real visitors
- **Messages** — messages sent through the contact form
- **About / Resume** — edit the site text directly, without touching the code

## Tech stack

- PHP 8 with PDO (MySQL / MariaDB)
- HTML, CSS and JavaScript (jQuery on the public site, Bootstrap 5 in the admin)
- Chart.js for charts and Leaflet with OpenStreetMap for the map

## Security

- Prepared statements for every database query
- CSRF tokens and server-side validation on every form, escaped output
- Spam protection on the contact form and a limit on failed admin logins
- Credentials are kept out of the repository

## Project status

- Done: English-only content, admin panel with real data, security review
- In progress: a cleaner, more professional visual design with a stronger focus on data analysis work

## Project structure

```
.
├── index.php              # Public site (single page)
├── js/, css/, img/        # Front-end files
├── download/              # PDF resume
├── portfolio-*en.html     # Portfolio items (section currently hidden)
├── admin/                 # Admin panel
├── includes/              # Shared PHP code (database connection, login, geolocation)
├── .htaccess, .user.ini   # Server security settings
├── CLAUDE.md              # Instructions for Claude Code
└── README.md
```

The real `dbconn.php`, `auth.php` and `geo.php` files hold credentials and API keys. They exist only on my computer and on the server, and are never committed (see `.gitignore`).

## Running it locally

1. Install a local server with PHP 8 and MySQL (for example WAMP or XAMPP).
2. Create the database tables: `header`, `about`, `resume`, `form`, `log`, `admin_users` and `login_attempts`.
3. Create your own `includes/dbconn.php`, `includes/auth.php` and `includes/geo.php`.
4. Serve the project folder and open it in your browser.

## Contact

You can reach me through the contact form on the website.
