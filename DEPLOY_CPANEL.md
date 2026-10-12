# Deploy MeetSpace on cPanel

For normal shared hosting with cPanel (Apache or LiteSpeed): no SSH, no Node, no queue worker needed.

## 0. What you need

- PHP **8.3 or newer** for the domain (cPanel → *MultiPHP Manager*).
- PHP extensions turned on (cPanel → *Select PHP Version → Extensions*): `mbstring`, `openssl`, `pdo_mysql`, `curl`, `fileinfo`, `tokenizer`, `dom` (xml), `libxml`, `iconv`, `ctype`, `json`, `session`, `filter`. Most are on by default.
- MySQL 5.7+ or MariaDB 10.3+.
- HTTPS on the domain (cPanel → *SSL/TLS Status → Run AutoSSL*). Sign-in and "Install app" (PWA) only work over HTTPS.
- A Clerk account (<https://dashboard.clerk.com>).

## 1. Build the package (on your computer)

```
python deploy/build_cpanel.py
```

This creates `deploy/out/meetspace-cpanel.zip`:

| Folder / file          | What it is                                                                            |
|------------------------|---------------------------------------------------------------------------------------|
| `meetspace-app/`       | the Laravel app, `vendor/` installed (no dev packages), production `.env`             |
| `meetspace-public/`    | the public web files (`index.php` already points at `../meetspace-app`)               |
| `database.sql`         | all tables, for a **new, empty** database                                             |
| `database-upgrade.sql` | adds the sign-in tables to an **existing** MeetSpace database (keeps your rooms and bookings) |

The folder names are unique on purpose: extracting the zip can never overwrite another site's `public_html`.

## 2. Database

cPanel → **MySQL® Database Wizard**: create a database and a user, give the user **ALL PRIVILEGES**
(names carry your cPanel username as a prefix, for example `acme_meetspace`).
You can also reuse the database of an earlier MeetSpace install.

cPanel → **phpMyAdmin** → select the database → **Import**:

- **New, empty database** → import `database.sql`. Result: `rooms`, `users`, `api_tokens`, `bookings`.
- **Database that already has `rooms` and `bookings`** → import `database-upgrade.sql` **only**. Your data stays; it adds `users`, `api_tokens` and a `user_id` column on `bookings`.

> Never import both. If you get "table already exists", you picked the wrong file.
> With SSH you can run `php artisan migrate --force` instead, on a new, empty database.

## 3. Upload the files

First find your site's **Document Root**: cPanel → **Domains** (older cPanel: *Subdomains*). Example:
`/home/YOURUSER/meetspace.example.com`.

1. **File Manager** → your home folder `/home/YOURUSER/` (not inside `public_html`).
2. Create a folder `meetspace-upload`, open it, **Upload** `meetspace-cpanel.zip`, right-click → **Extract**.
3. **Move** `meetspace-upload/meetspace-app` to `/home/YOURUSER/meetspace-app`.
   (If an older copy of the app is installed, rename it to `meetspace-app-old` first as a backup.)
4. Open `meetspace-upload/meetspace-public`, select **everything inside**, and **copy** it into the site's **Document Root**, overwriting existing files.
   An older install often has its own `index.php`, `.htaccess` and an old `public` folder there: replacing them is what you want. Do not touch any other site's folder.
5. File Manager → **Settings → Show Hidden Files**, and check that `.htaccess` is in the Document Root.

Final layout (example for a subdomain):

```
/home/YOURUSER/
├── meetspace-app/                   <- the app, outside the web root (private)
│   ├── .env                         <- your settings
│   └── app/ config/ vendor/ storage/ ...
└── meetspace.example.com/           <- the site's Document Root (what the web serves)
    └── index.php .htaccess app.js app.css sw.js icons/ ...
```

If the Document Root is a folder inside `public_html` (for example `public_html/meet`), it still works: `index.php` also looks two levels up.

## 4. Fill in `meetspace-app/.env`

File Manager → right-click → **Edit**:

| Setting | Value |
|---|---|
| `APP_URL` | `https://yourdomain.com` (no trailing `/`) |
| `DB_DATABASE`, `DB_USERNAME` | from step 2 |
| `DB_PASSWORD` | from step 2, **in double quotes** if it has symbols: `DB_PASSWORD="p@ss;word"` |
| `CLERK_PUBLISHABLE_KEY`, `CLERK_SECRET_KEY` | from step 6 |
| `ADMIN_PASSWORD` | a strong password; it opens room management on the home page |
| `APP_DEBUG` | `false` (use `true` only for a minute to see an error) |

Keep `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.
Do **not** run `config:cache`; the `.env` is read on every request.

## 5. Folder permissions

`meetspace-app/storage` and `meetspace-app/bootstrap/cache` (and everything inside) must be writable: **755** normally, **775** if you see permission errors.

## 6. Clerk

A development key (`pk_test_...`) is fine to try things out. For real users:

1. Clerk Dashboard → create / switch to the **Production** instance and add your domain.
2. Clerk shows **DNS records** (CNAMEs). Add them in cPanel → **Zone Editor**, wait until Clerk shows them verified.
3. Turn on the sign-in methods you want. **Google** on production needs your own Google OAuth client (Google Cloud Console → Credentials). Paste its Client ID and Secret into Clerk and add the redirect URI Clerk shows.
4. Copy the production **Publishable key** (`pk_live_...`) and **Secret key** (`sk_live_...`) into `.env`.
5. Optional but recommended: `CLERK_AUTHORIZED_PARTIES=https://yourdomain.com` in `.env`.

## 7. Check it works

- `https://yourdomain.com/up`: framework health page.
- `https://yourdomain.com/`: the **Bookings** page (Today / Upcoming) with the **+ New booking** and **Sign in** buttons. A plain "Laravel" welcome page means the old version is still being served.
- **Sign in**, create an account, then **+ New booking**.
- **Admin** → enter the password from `.env` → **Manage rooms**.

## Problems

| You see | Do this |
|---|---|
| Laravel welcome page at `/`, `/app.js` is 404 | The new files are not in the Document Root yet (step 3.4), or the Document Root is a different folder than you copied into. |
| `MeetSpace app folder not found` | `meetspace-app/` must sit next to the Document Root folder (see layout). |
| 500 error | Set `APP_DEBUG=true` for a minute, reload, read the message, or open `meetspace-app/storage/logs/laravel.log`. Set it back to `false`. |
| 403 / 404 on every page except `/` | `.htaccess` is missing (hidden file) or `mod_rewrite` is off. |
| `Your Composer dependencies require a PHP version >= 8.3` | Switch the domain to PHP 8.3+ in MultiPHP Manager. |
| `SQLSTATE ... Access denied` / `Unknown database` | The `DB_*` values do not match step 2; quote the password. |
| `Table 'users' doesn't exist` or `Unknown column 'user_id'` | Import `database-upgrade.sql` (existing database) or `database.sql` (empty database). |
| "Sign-in is not set up yet" | `CLERK_PUBLISHABLE_KEY` / `CLERK_SECRET_KEY` are empty in `.env`. |
| "Sign-in could not be verified" | The secret key belongs to another Clerk instance than the publishable key, or the host blocks outgoing HTTPS to `api.clerk.com`. |
| Old design after an update | Press Ctrl+Shift+R (the app caches its files for offline use). |

## Updating later

1. `python deploy/build_cpanel.py`.
2. Upload and extract the zip into a fresh `meetspace-upload` folder.
3. Replace `meetspace-app` but **keep your `meetspace-app/.env`** (copy it out first, put it back after).
4. Copy `meetspace-public/*` over the Document Root.
5. If the database changed, run the new SQL in phpMyAdmin. Then Ctrl+Shift+R in the browser.
