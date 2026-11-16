#!/usr/bin/env python3
"""Upload new or updated frontend and admin files to public_html/fastdetroit.

Frontend files are the web files in the project root. Admin files are the web
files in admin/. Credentials: config/ftp.local.json (gitignored).

A file is uploaded when it is missing on the host, or when the local copy is
newer than the remote copy. Remote files that are newer are left in place.
"""
import json
import sys
from datetime import datetime, timedelta, timezone
from ftplib import FTP, FTP_TLS, error_perm
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CONFIG = ROOT / "config" / "ftp.local.json"
EXTENSIONS = {".php", ".css", ".js", ".png", ".jpg", ".jpeg", ".gif", ".svg", ".webp", ".ico"}
SKIP_NAMES = {".env", ".env.production", ".htaccess"}


def collect_files():
    files = []
    for path in sorted(ROOT.iterdir()):
        if path.is_file() and include(path):
            files.append(path)
    admin = ROOT / "admin"
    if admin.is_dir():
        for path in sorted(admin.iterdir()):
            if path.is_file() and include(path):
                files.append(path)
    return files


def include(path: Path) -> bool:
    name = path.name
    if name in SKIP_NAMES or name.startswith(".env"):
        return False
    if name.endswith((".sql", ".md", ".bak", ".local.json", ".local.php")):
        return False
    return path.suffix.lower() in EXTENSIONS


def connect(cfg):
    host = cfg["host"]
    user = cfg["user"]
    password = cfg["password"]
    use_tls = bool(cfg.get("tls") or cfg.get("ftps"))
    ftp = FTP_TLS(host, timeout=30) if use_tls else FTP(host, timeout=30)
    ftp.login(user, password)
    if use_tls:
        ftp.prot_p()
    ftp.set_pasv(True)
    remote_dir = (cfg.get("remote_dir") or "public_html/fastdetroit").strip()
    if remote_dir and remote_dir not in ("/", "."):
        for part in [p for p in remote_dir.split("/") if p]:
            try:
                ftp.cwd(part)
            except error_perm as exc:
                print(f"Cannot cd to {part!r} — check remote_dir ({exc}).", file=sys.stderr)
                ftp.quit()
                sys.exit(1)
    return ftp


def remote_stamp(ftp, rel: str):
    try:
        response = ftp.sendcmd(f"MDTM {rel}")
    except error_perm as exc:
        if str(exc).startswith("550"):
            return None
        raise
    stamp = response.split()[-1]
    return datetime.strptime(stamp, "%Y%m%d%H%M%S").replace(tzinfo=timezone.utc)


def remote_size(ftp, rel: str):
    try:
        return ftp.size(rel)
    except error_perm:
        return None


def needs_upload(ftp, path: Path, rel: str):
    remote_mtime = remote_stamp(ftp, rel)
    if remote_mtime is None:
        return "new"
    local_mtime = datetime.fromtimestamp(path.stat().st_mtime, tz=timezone.utc)
    size = remote_size(ftp, rel)
    if size is not None and size != path.stat().st_size and local_mtime > remote_mtime:
        return "changed"
    if local_mtime > remote_mtime + timedelta(seconds=2):
        return "newer"
    return None


def ensure_admin_dir(ftp):
    try:
        ftp.cwd("admin")
        ftp.cwd("..")
    except error_perm:
        ftp.mkd("admin")


def main() -> None:
    dry_run = "--dry-run" in sys.argv
    if not CONFIG.is_file():
        print("Missing config/ftp.local.json — copy config/ftp.example.json and fill in credentials.")
        sys.exit(1)

    cfg = json.loads(CONFIG.read_text(encoding="utf-8"))
    ftp = connect(cfg)
    ensure_admin_dir(ftp)

    uploaded = []
    for path in collect_files():
        rel = path.relative_to(ROOT).as_posix()
        reason = needs_upload(ftp, path, rel)
        if reason is None:
            continue
        if dry_run:
            print(f"would upload ({reason}) {rel}")
            uploaded.append(rel)
            continue
        with path.open("rb") as handle:
            ftp.storbinary(f"STOR {rel}", handle)
        print(f"uploaded ({reason}) {rel}")
        uploaded.append(rel)

    ftp.quit()
    label = "Would upload" if dry_run else "Uploaded"
    print(f"{label} {len(uploaded)} file(s) to {cfg['host']} ({cfg.get('remote_dir') or 'public_html/fastdetroit'})")


if __name__ == "__main__":
    main()
