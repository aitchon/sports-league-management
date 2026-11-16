---
name: deploy-fastdetroit
description: >-
  Uploads new and updated frontend and admin files from the fastdetroit
  project to public_html/fastdetroit on itchonfamily.com via FTP. Use when
  the user asks to deploy, upload, publish, or FTP fastdetroit, the admin
  folder, or new PHP files to the remote web host.
---

# Deploy fastdetroit to the web host

The live site is `https://itchonfamily.com/fastdetroit/`. Upload only web files from the project root (frontend) and `admin/`. Do not build a zip and do not upload the database.

## Run the upload

From the **project root**:

```bash
python3 scripts/upload_new_files_ftp.py
```

Preview without sending files:

```bash
python3 scripts/upload_new_files_ftp.py --dry-run
```

Success ends with `Uploaded N file(s) to ...`.

The script uploads a file when it is missing on the host, or when the local copy is newer. It leaves a remote file alone when the host copy is newer.

### What is uploaded

- Frontend: `.php`, `.css`, `.js`, and image files directly in the project root
- Admin: those same types directly in `admin/`
- Remote directory: `public_html/fastdetroit` (so `admin/foo.php` lands at `public_html/fastdetroit/admin/foo.php`)

### What is never uploaded

- `.env`, `.env.production`, and other credential files
- `config/ftp.local.json`
- `db/`, `*.sql`, `*.md`
- `.git`, `.cursor`, `scripts/`

Do not hand-upload with `curl` or paste the FTP password into a command. Use the script.

## One-time setup

1. Copy `config/ftp.example.json` to `config/ftp.local.json`.
2. Set `host`, `user`, and `password` from the WebHostingPad FTP account used for itchonfamily.com (same host as the reunion deploy).
3. Keep `remote_dir` as `public_html/fastdetroit` and `tls` as `false` unless the host starts requiring FTPS.
4. Never commit `ftp.local.json`.

## If the script fails

- **Missing config**: copy the example file and fill it in. Stop if it is still missing.
- **530 / login**: wrong user, password, or host. Reset the FTP password in cPanel.
- **Cannot cd**: `remote_dir` must be `public_html/fastdetroit` from the FTP home directory.
- **TLS errors**: this account uses plain FTP (`tls: false`).
- **Firewall**: try another network or cPanel File Manager.

After PHP files are on the host, database changes are separate. Run `admin/setup_pickleball_2026.sql` in phpMyAdmin against `aitchon_basketball_league`. The upload does not do that.
