# GitHub Kanban

A lightweight Kanban board for GitHub Issues, organized by milestone. Columns are driven by `status:*` labels; all state is stored in GitHub — no separate project database needed.

---

## Table of Contents

- [Requirements](#requirements)
- [Build](#build)
- [Run (Local Development)](#run-local-development)
- [Test](#test)
- [Deploy](#deploy)
- [Distribution](#distribution)
- [Environment Reference](#environment-reference)
- [Architecture Notes](#architecture-notes)

---

## Requirements

| Component | Minimum version |
|-----------|----------------|
| PHP       | 8.1            |
| MySQL     | 5.7 / MariaDB 10.5 |
| Apache    | 2.4 with `mod_rewrite` |
| PHP extensions | `pdo_mysql`, `openssl`, `curl` |

No Composer dependencies — the project is intentionally dependency-free.

---

## Build

"Build" for this project means generating a production-ready archive and creating the required GitHub OAuth App credentials.

### 1. Register a GitHub OAuth App

1. Go to **GitHub → Settings → Developer settings → OAuth Apps → New OAuth App**
2. Fill in:
   - **Application name**: GitHub Kanban (or your chosen name)
   - **Homepage URL**: `https://your-domain.com`
   - **Authorization callback URL**: `https://your-domain.com/auth/callback`
3. Copy the **Client ID** and generate a **Client Secret**

### 2. Configure the environment

```bash
cp .env.example .env
```

Edit `.env` and fill in every value. Generate the two secrets with:

```bash
# SESSION_SECRET — 64 random hex characters
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# ENCRYPT_KEY — base64-encoded 32-byte key for AES-256-CBC
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

### 3. Create the database schema

```bash
mysql -u root -p < schema.sql
```

Or connect to an existing database and run `schema.sql` manually.

---

## Run (Local Development)

### Option A — PHP built-in server (quickest)

The built-in server doesn't support `.htaccess` rewriting, so you need to handle routing explicitly with a router script.

Create `router.php` in the project root:

```php
<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$map = [
    '/'                 => 'public/index.php',
    '/app'              => 'public/index.php',
    '/auth/login'       => 'auth/login.php',
    '/auth/callback'    => 'auth/callback.php',
    '/auth/logout'      => 'auth/logout.php',
    '/api/me'           => 'api/me.php',
    '/api/repos'        => 'api/repos.php',
    '/api/milestones'   => 'api/milestones.php',
    '/api/issues'       => 'api/issues.php',
    '/api/issue_update' => 'api/issue_update.php',
];
if (isset($map[$uri])) {
    require $map[$uri];
} else {
    http_response_code(404);
    echo '404 Not Found';
}
```

Then start the server:

```bash
php -S localhost:8080 router.php
```

Open `http://localhost:8080` in your browser.

> **Note:** Set `GITHUB_CALLBACK_URL=http://localhost:8080/auth/callback` in `.env` and update your GitHub OAuth App's callback URL to match.

### Option B — Apache with `mod_rewrite` (matches production)

Point your Apache `VirtualHost` `DocumentRoot` to the project root:

```apache
<VirtualHost *:80>
    ServerName kanban.local
    DocumentRoot /path/to/GitHub-Project-Management

    <Directory /path/to/GitHub-Project-Management>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable the required modules and restart Apache:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Add `kanban.local` to `/etc/hosts`:

```
127.0.0.1  kanban.local
```

Then open `http://kanban.local`.

---

## Test

The project has no automated test suite yet. The following is the recommended manual verification checklist before deploying any change.

### Authentication flow

- [ ] `/auth/login` redirects to GitHub's OAuth page
- [ ] After approving, GitHub redirects back and a session cookie (`kanban_session`) is set
- [ ] `/api/me` returns `200` with `login` and `csrf_token`
- [ ] `/auth/logout` clears the cookie and redirects to `/`
- [ ] Accessing `/api/repos` without a cookie returns `401`

### OAuth state / CSRF

- [ ] Manually tampering with the `state` query parameter in the callback URL returns `400`
- [ ] POSTing to `/api/issue_update` without the `X-CSRF-Token` header returns `403`
- [ ] POSTing with a wrong CSRF token returns `403`

### Repository and milestone listing

- [ ] `/api/repos` returns a non-empty JSON array for an account with repositories
- [ ] `/api/milestones?repo=owner/name` returns milestones for a repo that has them
- [ ] An invalid `repo` value returns `400`

### Kanban board

- [ ] `/api/issues?repo=owner/name&milestone=1` groups issues by `status:*` label
- [ ] Issues without any `status:*` label appear in the **Todo** column
- [ ] Drag-and-drop moves a card to another column and the label is updated on GitHub
- [ ] Dropping a card on its own column makes no API call
- [ ] Closing/reopening an issue updates the card styling and GitHub state

### Ensure Labels

- [ ] Clicking **Ensure Labels** creates the four `status:*` labels in a repo that has none
- [ ] Clicking again on a repo that already has the labels reports `0 created` without error

### Security checks

- [ ] `lib/` directory returns `403` when accessed via HTTP
- [ ] `.env` returns `403` when accessed via HTTP
- [ ] No access token appears in any API response or page source

---

## Deploy

### Prerequisites on the server

```bash
# Debian / Ubuntu
sudo apt install apache2 php php-mysql php-curl libapache2-mod-php mysql-server
sudo a2enmod rewrite
```

### Steps

1. **Clone or upload the project** to the server:

   ```bash
   git clone https://github.com/your-org/GitHub-Project-Management.git /var/www/kanban
   ```

2. **Set ownership and permissions:**

   ```bash
   sudo chown -R www-data:www-data /var/www/kanban
   sudo chmod -R 755 /var/www/kanban
   sudo chmod 640 /var/www/kanban/.env   # only owner + group readable
   ```

3. **Create and populate `.env`** (do not commit this file):

   ```bash
   sudo -u www-data cp /var/www/kanban/.env.example /var/www/kanban/.env
   sudo -u www-data nano /var/www/kanban/.env
   ```

4. **Import the database schema:**

   ```bash
   mysql -u root -p < /var/www/kanban/schema.sql
   ```

5. **Configure Apache:**

   ```apache
   <VirtualHost *:443>
       ServerName your-domain.com
       DocumentRoot /var/www/kanban

       SSLEngine on
       SSLCertificateFile    /etc/letsencrypt/live/your-domain.com/fullchain.pem
       SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem

       <Directory /var/www/kanban>
           AllowOverride All
           Require all granted
       </Directory>

       # Redirect HTTP → HTTPS
       ErrorLog  ${APACHE_LOG_DIR}/kanban-error.log
       CustomLog ${APACHE_LOG_DIR}/kanban-access.log combined
   </VirtualHost>

   <VirtualHost *:80>
       ServerName your-domain.com
       Redirect permanent / https://your-domain.com/
   </VirtualHost>
   ```

6. **Enable the site and reload Apache:**

   ```bash
   sudo a2ensite kanban
   sudo systemctl reload apache2
   ```

7. **Issue a TLS certificate** (if not already done):

   ```bash
   sudo certbot --apache -d your-domain.com
   ```

8. **Update your GitHub OAuth App** callback URL to `https://your-domain.com/auth/callback`.

### Updating a live deployment

```bash
cd /var/www/kanban
git pull origin master
# If schema changes are included:
mysql -u root -p kanban < schema.sql
sudo systemctl reload apache2
```

### Periodic session cleanup (optional cron)

Expired sessions are harmless but accumulate over time. Add to root crontab:

```cron
0 3 * * * mysql -u kanban_user -pPASSWORD kanban -e "DELETE FROM sessions WHERE expires_at < NOW();"
```

---

## Distribution

To package a release archive without development/git artifacts:

```bash
# From the project root
VERSION=1.0.0
git archive --format=zip --prefix=kanban-${VERSION}/ HEAD \
  -o kanban-${VERSION}.zip

# Or as a tarball
git archive --format=tar.gz --prefix=kanban-${VERSION}/ HEAD \
  -o kanban-${VERSION}.tar.gz
```

The archive excludes `.git/` and any files listed in `.gitignore` (including `.env`).

### What the archive contains

```
kanban-1.0.0/
├── api/
├── auth/
├── lib/
├── public/
├── .env.example        ← recipients copy this to .env
├── .htaccess
├── schema.sql
└── README.md
```

### Docker (optional self-contained image)

Create a `Dockerfile` at the project root:

```dockerfile
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Apache needs AllowOverride All for .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
```

Build and run:

```bash
docker build -t github-kanban .

docker run -d \
  -p 8080:80 \
  -e GITHUB_CLIENT_ID=xxx \
  -e GITHUB_CLIENT_SECRET=xxx \
  -e GITHUB_CALLBACK_URL=http://localhost:8080/auth/callback \
  -e DB_HOST=host.docker.internal \
  -e DB_NAME=kanban \
  -e DB_USER=kanban \
  -e DB_PASS=secret \
  -e APP_URL=http://localhost:8080 \
  -e SESSION_SECRET=<64-char-hex> \
  -e ENCRYPT_KEY=<base64-32-bytes> \
  --name kanban \
  github-kanban
```

> When running in Docker, environment variables take precedence over `.env` if you set them via `-e` flags (the bootstrap loader only writes to `$_ENV` when the key isn't already set — adjust `lib/bootstrap.php` if needed).

---

## Environment Reference

| Variable               | Description |
|------------------------|-------------|
| `GITHUB_CLIENT_ID`     | OAuth App client ID |
| `GITHUB_CLIENT_SECRET` | OAuth App client secret |
| `GITHUB_CALLBACK_URL`  | Full URL of the `/auth/callback` endpoint |
| `DB_HOST`              | MySQL host (default `localhost`) |
| `DB_NAME`              | MySQL database name |
| `DB_USER`              | MySQL username |
| `DB_PASS`              | MySQL password |
| `APP_URL`              | Base URL of the application (no trailing slash) |
| `SESSION_SECRET`       | 64-char hex string; used to sign CSRF tokens |
| `ENCRYPT_KEY`          | Base64-encoded 32-byte key; used for AES-256-CBC token encryption |

---

## Architecture Notes

- **No Composer.** All code is vanilla PHP 8.1+; zero third-party packages.
- **Single-page app.** `public/index.php` serves one HTML shell; JavaScript handles all screen transitions.
- **Label-driven state.** Kanban column = `status:todo` / `status:in-progress` / `status:review` / `status:done` label on the GitHub issue. Moving a card removes the old label and adds the new one via `PATCH /repos/:owner/:repo/issues/:number`.
- **Token security.** GitHub OAuth tokens are AES-256-CBC encrypted before storage; they are decrypted in-process and never sent to the browser.
- **Stateless CSRF.** CSRF tokens are `HMAC-SHA256(session_token, SESSION_SECRET)` — no extra database column or server-side storage required.
- **Sessions.** Stored in MySQL with a 7-day TTL. The HTTP-only, `SameSite=Lax` cookie holds only the opaque session token.
