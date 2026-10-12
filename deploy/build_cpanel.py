#!/usr/bin/env python3
"""
Builds the upload package for cPanel shared hosting.

    python deploy/build_cpanel.py

Result: deploy/out/meetspace-cpanel.zip containing
    meetspace-app/       the Laravel app (vendor installed WITHOUT dev packages, production .env)
    meetspace-public/    the public web files (index.php already points at ../meetspace-app)
    database.sql         full schema for a NEW, empty database (phpMyAdmin > Import)
    database-upgrade.sql adds the sign-in tables to an EXISTING MeetSpace database
    DEPLOY_CPANEL.md

The folder names are deliberately unique so that extracting the zip can never overwrite
the main site's public_html.

Nothing in your working copy is changed: the package is assembled in deploy/out/build.
"""
import base64
import secrets
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'deploy' / 'out'
BUILD = OUT / 'build'
APP = BUILD / 'meetspace-app'
PUBLIC = BUILD / 'meetspace-public'
ZIP = OUT / 'meetspace-cpanel.zip'

COMPOSER_CANDIDATES = [shutil.which('composer'), 'C:/php83/composer.bat', 'C:/php83/composer.phar']

INDEX_PHP = r'''<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The Laravel app lives OUTSIDE the web root, in a folder called "meetspace-app".
// Subdomain root  /home/USER/meetspace.example.com  -> app in /home/USER/meetspace-app      (../)
// Folder in public_html  /home/USER/public_html/meet -> app in /home/USER/meetspace-app     (../../)
$base = null;
foreach (['/../meetspace-app', '/../../meetspace-app'] as $relative) {
    if (is_file(__DIR__.$relative.'/vendor/autoload.php')) {
        $base = __DIR__.$relative;
        break;
    }
}
if ($base === null) {
    http_response_code(500);
    exit('MeetSpace app folder not found. Upload the "meetspace-app" folder next to the site folder (see DEPLOY_CPANEL.md).');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
'''

HTACCESS_HTTPS = '''    # Always use HTTPS (needed for sign-in and for installing the app as a PWA)
    RewriteCond %{HTTPS} off
    RewriteCond %{HTTP:X-Forwarded-Proto} !https
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

'''

ENV_TEMPLATE = '''APP_NAME=MeetSpace
APP_ENV=production
APP_KEY={app_key}
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN.COM

# Admin password for the room-management mode on the home page. Change it if you like.
ADMIN_PASSWORD={admin_password}

# Clerk keys from https://dashboard.clerk.com (API keys).
# On a real domain use the PRODUCTION instance keys (pk_live_... / sk_live_...).
CLERK_PUBLISHABLE_KEY=
CLERK_SECRET_KEY=
# Optional, recommended: only accept sign-ins that started on your own site.
# CLERK_AUTHORIZED_PARTIES=https://YOUR-DOMAIN.COM

# MySQL database created in cPanel > MySQL Databases (names carry your cPanel username prefix).
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPANELUSER_meetspace
DB_USERNAME=CPANELUSER_meetspace
DB_PASSWORD=

# Shared hosting: keep everything on files, no queue worker, no extra tables.
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
BCRYPT_ROUNDS=12
MAIL_MAILER=log
'''

STORAGE_DIRS = [
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/testing',
    'storage/logs',
    'bootstrap/cache',
]


def run(cmd, cwd):
    print('  $', ' '.join(map(str, cmd)))
    res = subprocess.run(cmd, cwd=cwd)
    if res.returncode:
        sys.exit(f'command failed ({res.returncode}): {cmd}')


def find_composer():
    for c in COMPOSER_CANDIDATES:
        if c and Path(c).exists():
            return [c] if not str(c).endswith('.phar') else ['php', c]
    sys.exit('composer not found. Install Composer or add it to PATH.')


def copy_tree(src, dst, ignore=None):
    shutil.copytree(src, dst, ignore=shutil.ignore_patterns(*(ignore or [])), dirs_exist_ok=True)


def main():
    print('1/6 clean build folder')
    if BUILD.exists():
        shutil.rmtree(BUILD)
    APP.mkdir(parents=True)
    PUBLIC.mkdir(parents=True)

    print('2/6 copy application code')
    for folder in ['app', 'bootstrap', 'config', 'database', 'resources', 'routes']:
        copy_tree(ROOT / folder, APP / folder, ignore=['*.sqlite', '*.sql', '*.log', '.gitignore'])
    for name in ['artisan', 'composer.json', 'composer.lock']:
        shutil.copy2(ROOT / name, APP / name)
    # empty writable folders the framework needs (a .gitkeep keeps them in the zip)
    for d in STORAGE_DIRS:
        (APP / d).mkdir(parents=True, exist_ok=True)
        (APP / d / '.gitkeep').write_text('')
    for stale in (APP / 'bootstrap' / 'cache').glob('*.php'):
        stale.unlink()  # rebuilt by composer below, without dev packages

    print('3/6 composer install --no-dev (production vendor folder)')
    composer = find_composer()
    run(composer + ['install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--prefer-dist'], APP)
    run(composer + ['check-platform-reqs', '--no-dev'], APP)

    print('4/6 public_html (web root)')
    copy_tree(ROOT / 'public', PUBLIC, ignore=['*.log', 'hot', 'storage', 'build'])
    (PUBLIC / 'index.php').write_text(INDEX_PHP, encoding='utf-8')
    htaccess = (ROOT / 'public' / '.htaccess').read_text(encoding='utf-8')
    htaccess = htaccess.replace('    RewriteEngine On\n\n', '    RewriteEngine On\n\n' + HTACCESS_HTTPS, 1)
    assert 'Always use HTTPS' in htaccess, 'could not patch .htaccess'
    (PUBLIC / '.htaccess').write_text(htaccess, encoding='utf-8')

    print('5/6 production .env + database.sql + guide')
    app_key = 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode()
    admin_password = secrets.token_urlsafe(12)
    (APP / '.env').write_text(ENV_TEMPLATE.format(app_key=app_key, admin_password=admin_password), encoding='utf-8')
    shutil.copy2(ROOT / 'database' / 'cpanel_schema.sql', BUILD / 'database.sql')
    shutil.copy2(ROOT / 'database' / 'cpanel_upgrade_login.sql', BUILD / 'database-upgrade.sql')
    shutil.copy2(ROOT / 'DEPLOY_CPANEL.md', BUILD / 'DEPLOY_CPANEL.md')

    print('6/6 zip')
    if ZIP.exists():
        ZIP.unlink()
    with zipfile.ZipFile(ZIP, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for path in sorted(BUILD.rglob('*')):
            if path.is_file():
                z.write(path, path.relative_to(BUILD).as_posix())  # forward slashes, so it unpacks correctly on Linux
    size = ZIP.stat().st_size / 1024 / 1024
    print(f'\nDone: {ZIP}  ({size:.1f} MB)')
    print(f'Admin password written to meetspace-app/.env : {admin_password}')


if __name__ == '__main__':
    main()
