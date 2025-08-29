# Copilot Instructions — Code Review Guide (PHP • Drop-in • cPanel/VPS • No Composer)

**Goal:** Keep this repo truly drop-in (shared hosting friendly). Unzip, visit `/install`, finish setup. Copilot: review PRs with that in mind.

---

## 1) Project Context (assumptions for reviews)
- **PHP:** 8.1+ (works on 8.2/8.3). Apache+PHP (cPanel) and Apache/Nginx+PHP-FPM (VPS).
- **No Composer.** All deps are vendored inside `app/libs/` (or `vendor/` if present) and namespaced or prefixed to avoid conflicts.
- **Routing:** Front controller (`public/index.php`) with `.htaccess` on Apache; Nginx sample provided.
- **Config:** Installer writes `config.php` (array) under `app/config/`. No secrets in Git.
- **DB:** MySQL/MariaDB via PDO (prepared statements only).
- **Writable dirs:** `storage/` (logs, cache, uploads); `app/runtime/` if needed.
- **Tasks:** Cron via cPanel UI or system cron (documented command).
- **Mail:** SMTP only (PHPMailer or tiny bundled client). No `mail()` in production.

---

## 2) Installer Contract (what “drop-in” means)
Copilot should verify PRs keep these true:

- Visiting `/install`:
  - [ ] Detects PHP version & required extensions (pdo_mysql, mbstring, intl, json, curl, openssl, gd/imagick if used, zip).
  - [ ] Checks folder permissions for `storage/` (and creates subdirs).
  - [ ] Prompts for DB, app URL, admin user, SMTP (optional).
  - [ ] Writes `app/config/config.php` atomically (temp file + rename), sets secure perms.
  - [ ] Runs idempotent SQL migrations from `install/sql/` (record applied migrations).
  - [ ] Optionally writes `.htaccess` / shows Nginx snippet if missing.
  - [ ] Secures `/install` (deletes or drops a `.installed` sentinel that blocks reruns).

- **No external build steps.** No `composer`, no Node, no Docker required.

---

## 3) What to Block in Code Review (hard fails)
- Secrets or credentials in repo, examples, or screenshots.
- Raw SQL built via string concatenation (must use PDO prepared statements).
- Direct echo of unsanitized request data; missing output escaping.
- Uploads without MIME/extension checks, size limits, randomized names, and storage outside web root (or protected via `.htaccess`).
- Hard-coded absolute paths or hostnames.
- Fatal `die()/var_dump()` left in non-test code.
- Stack traces exposed in production; error display must be off.
- Features that require root or server-level changes not available on cPanel.

---

## 4) Portability Checklist (cPanel ↔ VPS)
- [ ] Works with `.htaccess` only (no httpd.conf edits).  
- [ ] Alternative Nginx server block provided in `/deploy/`.  
- [ ] `.user.ini` included for overridable limits on shared hosting.  
- [ ] Relative paths via `__DIR__` and `$_SERVER['DOCUMENT_ROOT']` guards.  
- [ ] PHP extensions validated by installer; graceful “feature off” if missing.

---

## 5) Minimal Config Patterns (Copilot should enforce)

**`app/config/config.php` (written by installer)**
```php
<?php
return [
  'env'         => 'production', // or 'development'
  'app_url'     => 'https://example.com',
  'db' => [
    'dsn'      => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'user'     => 'dbuser',
    'password' => '***',
    'options'  => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
  ],
  'mail' => [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'username'  => 'user',
    'password'  => '***',
    'encryption'=> 'tls',
    'from'      => ['no-reply@example.com' => 'App'],
  ],
  'security' => [
    'csrf_key'  => 'random-32-bytes',
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
  ],
  'paths' => [
    'storage' => __DIR__ . '/../../storage',
  ],
];
```

**Apache `.htaccess` (in `/public`)**
```apacheconf
Options -Indexes
RewriteEngine On
RewriteBase /

# Front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Basic security headers
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "no-referrer-when-downgrade"
  Header always set Permissions-Policy "interest-cohort=()"
</IfModule>
```

**`.user.ini` (repo root or `/public`)**
```ini
display_errors=0
log_errors=1
memory_limit=256M
max_execution_time=60
upload_max_filesize=10M
post_max_size=12M
```

**Tiny Autoloader (no Composer)**
```php
spl_autoload_register(function ($class) {
  $prefix = 'App\';
  $base = __DIR__ . '/../app/src/';
  if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
  $rel = str_replace('\', '/', substr($class, strlen($prefix))) . '.php';
  $file = $base . $rel;
  if (is_file($file)) require $file;
});
```

---

## 6) Repo Layout (Copilot verifies references)
```
/public/          # web root (index.php, .htaccess)
/install/         # installer UI + /sql migrations + .installed sentinel
/app/src/         # namespaced PHP code (App\*)
/app/libs/        # bundled libs (namespaced/prefixed)
/app/config/      # config.php (generated)
/storage/         # logs, cache, uploads (writable)
/deploy/          # nginx.conf example, cron examples
```

---

## 7) Security & Reliability (specific asks for Copilot)
- **Inputs/Outputs:** Identify unvalidated `$_GET/$_POST/$_FILES` usage; require filter/validation layer. Flag missing HTML escaping in views.
- **CSRF:** Ensure tokens on state-changing routes; verify session/cookie flags (Secure, HttpOnly, SameSite).
- **Auth:** `password_hash()` / `password_verify()` only; no custom crypto.
- **DB:** Ensure all queries are prepared; call out any dynamic ORDER/LIMIT without whitelisting.
- **Uploads:** Verify MIME (finfo), extension whitelist, max size, random filenames, and non-executable storage. Add `.htaccess` in upload dirs to deny script execution.
- **Errors/Logs:** Central error handler logs to `storage/logs/` (no PII); production disables display_errors.
- **Rate limiting:** If endpoints invite abuse, suggest simple limiter (e.g., IP+route in storage/cache).

---

## 8) Performance (within shared hosting limits)
- Avoid N+1 queries; add indexes for new predicates (document in migration).
- Cache read-heavy results (opcode/APCu if available; otherwise filesystem cache).
- Offload long tasks to cron (document command in `/deploy/CRON.md`).

---

## 9) PR Checklist (Copilot must comment if missing)
- [ ] Installer still fully works: fresh install + re-run blocked by sentinel.
- [ ] `install/sql/` migrations are idempotent and logged (e.g., `_migrations` table).
- [ ] `app/config/config.php` keys documented; new settings added to installer UI.
- [ ] `.htaccess`/Nginx examples updated if routes change.
- [ ] Writable dirs created by installer; perms not world-writable.
- [ ] No new direct superglobal usage without validation wrapper.
- [ ] All DB changes via prepared statements.
- [ ] Upload surfaces hardened (if touched).
- [ ] Error handling: no debug output in production path.
- [ ] Docs: `/README` and `/deploy/*` updated for any operational change.
- [ ] Cron examples adjusted if new scheduled tasks were added.

---

## 10) Copilot Review Prompts (ready to paste)
- “List any queries built via string concatenation; rewrite with PDO prepared statements.”
- “Find endpoints that mutate state and verify CSRF protection is applied.”
- “Locate all direct `$_GET/$_POST/$_FILES` reads; propose validation and sanitization wrappers.”
- “Scan upload handlers for MIME/extension checks, size limits, randomized names, and non-executable storage.”
- “Identify places that echo user data; ensure escaping in templates/views.”
- “Check installer writes `config.php` atomically and locks out re-install via `.installed` or equivalent.”
- “Verify `.htaccess`/Nginx config matches the current routes and public dir.”
- “Flag functions >40 lines or classes >300 lines; suggest extractions to keep things maintainable.”
- “Confirm production disables `display_errors` and central error handler logs to `storage/`.”

---

## 11) What “Good” Looks Like
- Fresh unzip → `/install` completes without server tweaks.
- No secrets in Git; config written by installer with safe perms.
- Security controls explicit and test-able; errors never leak details.
- DB access is exclusively via prepared statements.
- Docs match reality; examples actually work on cPanel and a vanilla VPS.

*(Keep this file short. Link deeper guidance from `/docs/` if needed.)*
