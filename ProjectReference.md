# Backup/Restore App — Project Reference (Milestones 1 → 3)

This document is the **single source of truth** for the Backup/Restore app as delivered through **Milestone 3**. It captures scope, decisions, architecture, file layout, APIs, installer/update/cleanup flows, security model, hosting constraints (shared hosting/cPanel + VPS), and the implementation details as they exist **at the end of Milestone 3**.

> The app is a lightweight PHP application using the **Flight** micro-framework (single PHP file), **SQLite** (pdo_sqlite), **ZipArchive**, and a minimal **AWS S3 SigV4 presigner** (no SDK). It is designed to work on common **cPanel/Bluehost shared hosting** while remaining portable to VPS environments.

---

## Table of Contents

1. Goals & Non‑Goals  
2. Milestones Summary  
3. Key Design Decisions  
4. System Architecture  
5. Directory Layout  
6. Runtime Requirements  
7. Configuration Model  
8. Installer / Update / Cleanup  
9. Security Model  
10. Backup Flow  
11. Restore Flow  
12. HTTP API  
13. Shared Hosting (cPanel) Guide  
14. VPS Guide  
15. Error Handling & Observability  
16. Performance & Resource Use  
17. Compatibility & Portability  
18. Operational Runbook  
19. Known Limitations / Future Work  
20. Appendix

---

## Goals & Non‑Goals

### Goals
- **Portable backup tool** to create **ZIP archives** with a **manifest** and **sha256** checksum.
- **Offsite‑first**: store backups **only on S3 (or S3‑compatible)** by default.
- **Zero CLI dependencies** for core flows on shared hosting (no tar/gsutil/awscli required).
- **Simple installer** in the browser that runs **exactly once** and sets secure permissions.
- **Update** page (auth required) to modify configuration.
- **Cleanup** page to wipe configuration and optionally metadata DB, enabling a fresh install.
- **Small footprint**: no heavy frameworks or AWS SDK; single‑file Flight framework.

### Non‑Goals (as of Milestone 3)
- UI for browsing files/dirs server‑side (paths are provided to API).
- Incremental or differential backups; deduplication.
- Per‑file hashes in the manifest (archive‑level checksum only).
- Scheduling/cron orchestration within the app itself.
- Multi‑tenant admin or user accounts (single bearer token only).
- GPG encryption at rest for archives (S3‑side encryption may be used externally).

---

## Milestones Summary

### Milestone 1 (Foundations)
- Established project constraints: **shared hosting compatibility** (Bluehost/cPanel).
- Chosen stack: **Flight (PHP)**, **SQLite (pdo_sqlite)**, **ZipArchive**, **cURL**.
- Configuration to be kept **outside web root** with strict perms (**0600**), ideally at `~/.backupapp/config.php`.
- **Offsite S3 storage** as default; local copies optional.
- Authentication via **single bearer token** stored in config and mirrored to DB (for rotation).

### Milestone 2 (Core API & S3)
- Implemented **/api/backup** (ZIP + manifest + sha256) → upload to S3 via **presigned PUT** (custom SigV4).
- Implemented **/api/restore** (download presigned GET, checksum verify, extract; optional DB restore hook).
- **SQLite** metadata DB: `backups` + `tokens` tables.
- **Token rotation** endpoint with **grace period** for old token.
- Basic list/inspect endpoints for backups.

### Milestone 3 (UX & Ops polish)
- **Browser Installer** (`/install`) that **runs exactly once**:
  - Writes `~/.backupapp/config.php` with **0600** and directory **0700**.
  - Validates S3 reachability.
  - Provides **manual fix instructions** for cPanel/VPS if perms can’t be enforced.
  - Optional fallback to **0640** (explicit opt‑in).
- **Update** page (`/update`, auth required): edit settings and **Fix Permissions** button.
- **Cleanup** page (`/cleanup`): wipe config; if already installed, requires token; optional wipe of SQLite metadata.
- Ensured everything is **cPanel/Bluehost‑friendly**.

### Implementation Gaps After Milestone 3

- The installer writes `config.php` with `0600` but lacks interactive guidance for manual permission fixes and does not offer the documented `0640` fallback.
- `POST /install` does not prevent repeated submissions when a configuration already exists, allowing the installer to run more than once.
- The repository still ships with a placeholder `Flight.php`; the official Flight micro‑framework has not been integrated yet.

---

## Key Design Decisions

1. **ZIP over TAR** for shared hosting portability  
   - `ZipArchive` ships widely with PHP; avoids shelling to `tar`.  
   - **Memory‑safe** by writing to **disk** in a temp directory (not buffering entire archive in memory).  
   - TAR support can be added later under a feature flag; ZIP remains the default.

2. **S3 via presigned URLs (no SDK)**  
   - Avoids AWS SDK footprint.  
   - Uses **SigV4** presigner (GET & PUT).  
   - Compatible with AWS S3 **and** most S3‑compatible endpoints (Backblaze B2, MinIO) using **path‑style** when endpoint is custom.

3. **Offsite‑first**  
   - Default `S3_ONLY`; optional `LOCAL_AND_S3` for clients who need a local copy too.  
   - Intent is to **not waste hosting space** on backups.

4. **Config outside web root with strict perms**  
   - Target path: `~/.backupapp/config.php`  
   - Permissions: `config.php` = **0600**, `~/.backupapp/` = **0700**, `data/` = **0700**.  
   - If `0600` cannot be enforced (rare), **optional** `0640` fallback with explicit warning.

5. **Installer runs once**  
   - Blocks re‑entry if `config.php` exists.  
   - Configuration changes go through **/update** (auth required).  
   - **/cleanup** can reset the app (with confirmation and token if installed).

---

## System Architecture

```text
[ Browser (Admin) ]
      |  (HTML forms + CSRF)
      v
  /install  (no auth; once)    /update (auth)           /cleanup (auth if installed)
        \           |           /    \                            |
         \          |          /      \                           |
          \         v         v        \                          v
           +--> [CONFIG_DIR (config.php + app.db)] <---> [SQLite: tokens, backups]
                         |                                ^
                         v                                |
[ API Client ] --->  /api/* (Bearer token)  --------------+
       |                                           (inspect/list)
       v
   /api/backup  ==(ZipArchive)==> temp zip  --PUT-->  S3
   /api/restore <== GET presign --- download -- verify sha256 -- extract
```

---

## Directory Layout

```text
backup-app/
├─ public/
│  ├─ .htaccess                 # front controller routing
│  └─ index.php                 # resolves CONFIG_DIR/STORAGE_DIR/DB_PATH, boots Flight, registers routes
├─ app/
│  ├─ lib/flight/Flight.php     # single-file Flight (replace placeholder with real library)
│  ├─ bootstrap.php             # wiring (helpers/db/s3), data dir perms, Flight registry
│  ├─ helpers.php               # config writer, auth, CSRF, rrmdir, counters, HEAD helper
│  ├─ db.php                    # sqlite connect/bootstrap
│  ├─ s3.php                    # SigV4 presigner, PUT/GET helpers
│  ├─ controllers/
│  │  ├─ InstallController.php  # GET/POST /install (one-time)
│  │  ├─ UpdateController.php   # GET/POST /update (auth)
│  │  ├─ CleanupController.php  # GET/POST /cleanup (auth if installed)
│  │  ├─ BackupController.php   # /api/backup, /api/backups, /api/backup/:id, /download
│  │  └─ RestoreController.php  # /api/restore, /api/admin/rotate-token
│  └─ views/
│     ├─ layout_top.php         # shared HTML/CSS + CSRF field
│     ├─ layout_bottom.php
│     ├─ install.php
│     ├─ update.php
│     └─ cleanup.php
└─ data/                        # portable runtime root (config/, storage/)
```

---

## Runtime Requirements

- **PHP ≥ 8.0**
- PHP extensions: **`ZipArchive`**, **`curl`**, **`pdo_sqlite`**
- **SQLite** (file-based; no separate server)
- File permissions: ability to set `0600/0700` for config and private dir (common with PHP‑FPM/suPHP on cPanel)
- Outbound HTTPS egress to S3/endpoint

---

## Configuration Model

- Stored in **`CONFIG_DIR/config.php`** (`CONFIG_DIR` defaults to `<project root>/data/config` but can be overridden with `BACKUPAPP_CONFIG_DIR` or by defining the constant before bootstrap).
- PHP returns an array (no `.env`, no INI). Example:

```php
<?php
return [
  'app' => [
    'storage_mode'    => 'S3_ONLY',
    'archive_format'  => 'zip',
    'token'           => '***LONG_RANDOM***',
    'presign_expires' => 900,
    'basedir'         => '/home/bitnami/backups'
  ],
  's3' => [
    'bucket'     => 'my-bucket',
    'region'     => 'us-east-1',
    'endpoint'   => '',
    'access_key' => 'AKIA...',
    'secret_key' => '...'
  ],
  'db' => [
    'dump_cmd'    => "mysqldump -u... -p'...' db > {out}",
    'restore_cmd' => "mysql -u... -p'...' db < {in}"
  ],
  'installer' => [
    'installed_at' => '2025-08-21T12:34:56Z',
    'installed'    => true
  ]
];
```

Permissions enforced:
- `config.php` → 0600
- `CONFIG_DIR` → 0700
- `STORAGE_DIR` (defaults to `<project root>/data/storage`) → 0700

Environment overrides:
- `BACKUPAPP_CONFIG_DIR` (or defining `CONFIG_DIR`) sets the portable configuration root.
- `BACKUPAPP_STORAGE_DIR` (or defining `STORAGE_DIR`) selects where local copies/restores live.
- `BACKUPAPP_DB_PATH` (or defining `DB_PATH`) sets the SQLite file; defaults to `CONFIG_DIR/app.db`.

---

## Installer / Update / Cleanup

### Installer (/install)
- Runs once; no auth.
- Validates PHP extensions + S3 reachability.
- Writes config.php with strict perms (0600/0700).
- Optional opt-in for 0640 fallback.
- Mirrors token to DB.

### Update (/update)
- Requires bearer token.
- Allows editing S3, mode, DB commands, presign expiry.
- Provides “Fix Permissions” button.

### Cleanup (/cleanup)
- Requires bearer if installed.
- Confirms by typing WIPE.
- Deletes config.php and optional DB file.
- Allows re-running installer.

---

## Security Model

- Single bearer token.  
- Token rotation (/api/admin/rotate-token) with 30m grace.  
- CSRF tokens for installer/update/cleanup forms.  
- Config file locked at 0600 perms.  

---

## Backup Flow

1. /api/backup → POST JSON with paths + flags.  
2. Optionally run DB dump to temp.  
3. ZipArchive builds archive in temp.  
4. Manifest + sha256 generated.  
5. Upload .zip, .manifest.json, .sha256 to S3 via presigned PUT.  
6. Record metadata in SQLite.  
7. Optional local copy if LOCAL_AND_S3.  

---

## Restore Flow

1. /api/restore → POST JSON with backup_id, target, verify_only, restore_db.  
2. Presigned GET to fetch .zip.  
3. Verify sha256 checksum.  
4. If verify_only, stop. Else extract with ZipArchive.  
5. If restore_db = true, run restore_cmd with {in} placeholder replaced.  
6. Respond success.  

---

## HTTP API

- POST /api/backup  
- GET /api/backups  
- GET /api/backup/:id  
- GET /api/backup/:id/download  
- POST /api/restore  
- POST /api/admin/rotate-token  

Auth: `Authorization: Bearer <token>`

---

## Shared Hosting (cPanel) Guide

- Upload `backup-app/` into `public_html/`.  
- Replace placeholder Flight.php with official Flight library.  
- Visit /install and complete form.  
- If perms can’t be set automatically:  
  - File Manager → create `~/.backupapp/` (0700).  
  - Set config.php to 0600.  

---

## VPS Guide

```bash
install -d -m 700 -o www-data -g www-data /home/www-data/.backupapp
install -m 600 -o www-data -g www-data /path/to/config.php /home/www-data/.backupapp/config.php
```

---

## Error Handling & Observability

- JSON errors from API with HTTP codes.  
- Installer/update/cleanup show warnings inline.  
- Failures in DB dump/restore surfaced as 500 JSON.  

---

## Performance & Resource Use

- Archives streamed to disk (not in memory).  
- cURL streams to S3 with presigned PUT.  
- Minimal footprint: Flight + SQLite + custom helpers.  

---

## Compatibility & Portability

- cPanel/Bluehost safe.  
- Works with AWS, Backblaze B2, MinIO.  
- Default 0600/0700 perms. Optional 0640 fallback.  

---

## Operational Runbook

- Install via /install once.  
- Use /update for changes.  
- Use /cleanup to wipe config.  
- Rotate token periodically.  
- Test restores with verify_only.  

---

## Known Limitations / Future Work

The following items are not yet implemented and are slated for future milestones:

- No scheduling (cron).
- No incremental/differential backups.
- No per-file hashes.
- No GPG encryption.
- Limited restore path validation.
- Placeholder `Flight.php` still bundled; swap in official library.
- Installer improvements: enforce single execution and provide in-app permission remediation with optional `0640` fallback.

---

## Appendix

- Flight library: https://github.com/mikecao/flight  
- SQLite docs: https://www.sqlite.org/docs.html  
- AWS SigV4 signing: https://docs.aws.amazon.com/general/latest/gr/signature-version-4.html  

---
