"""Inventory and package source + built assets only. Never connects to a database.

This intentionally omits vendor: the result is NOT a deployable production release.
Run from the project root: python docs/figma/qa/release-manifest.py
"""

import hashlib
import json
import os
import pathlib
import re
import subprocess
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[3]
GIT = ["git", "--git-dir=" + str(ROOT / ".git"), "--work-tree=" + str(ROOT)]


def digest(data):
    return hashlib.sha256(data).hexdigest()


def git(*args):
    return subprocess.check_output(GIT + list(args), cwd=ROOT).decode("utf-8")


def main():
    files = set()
    for directory in ["app", "config", "routes", "resources", "database/migrations",
                      "public/build", "public/dealer-registration", "public/hilux-brothers"]:
        files.update(p for p in (ROOT / directory).rglob("*") if p.is_file())
    for name in ["artisan", "composer.json", "composer.lock", "package.json", "package-lock.json",
                 "vite.config.js", ".env.example", "bootstrap/app.php", "bootstrap/providers.php",
                 "public/index.php", "public/.htaccess", "public/favicon.ico", "public/robots.txt",
                 "database/data/vietnam_administrative_units.json", "database/data/toyota_dealers.json"]:
        if (ROOT / name).is_file():
            files.add(ROOT / name)

    required = {"app/Services/SubmissionIntake.php", "app/Services/DemoSubmissionScope.php",
                "app/Http/Controllers/DealerRegistrationFinalController.php",
                "database/migrations/2026_11_03_000023_support_final_dealer_registration.php",
                "public/build/manifest.json", "database/data/vietnam_administrative_units.json"}
    names = {p.relative_to(ROOT).as_posix() for p in files}
    if not required <= names:
        raise RuntimeError("Missing required release files")
    rows = []
    for p in sorted(files):
        name = p.relative_to(ROOT).as_posix()
        if p.is_symlink() or not p.resolve().is_relative_to(ROOT):
            raise RuntimeError("Symlink/external path is not allowed")
        if any(x in name.split("/") for x in ["tests", "vendor", "node_modules", ".git", ".laravel-tools"]):
            raise RuntimeError("Forbidden release path")
        if p.name.startswith(".env") and name != ".env.example":
            raise RuntimeError("Environment secret file is not allowed")
        data = p.read_bytes()
        # Limited literal scan, not a claim that all possible secret formats are detectable.
        if re.search(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAKIA[0-9A-Z]{16}\b|APP_KEY=base64:[A-Za-z0-9+/]{40,}", data):
            raise RuntimeError("Possible secret literal in allowlisted file; review privately")
        rows.append({"path": name, "bytes": len(data), "sha256": digest(data)})

    lock = json.loads((ROOT / "composer.lock").read_text(encoding="utf-8"))
    installed = json.loads((ROOT / "vendor/composer/installed.json").read_text(encoding="utf-8"))
    dev_names = {p["name"] for p in lock.get("packages-dev", [])}
    local_dev = sorted(p["name"] for p in installed["packages"] if p["name"] in dev_names)
    run = os.environ.get("QA_RUN_ID", "latest")
    if not re.fullmatch(r"[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}", run):
        raise RuntimeError("Invalid QA_RUN_ID")
    directory = ROOT / "docs/figma/qa/artifacts" / run / "release"
    directory.mkdir(parents=True, exist_ok=True)
    archive = directory / "phase-2c-4c-source-NOT-DEPLOYABLE.zip"
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED) as output:
        for row in rows:
            item = zipfile.ZipInfo(row["path"], date_time=(2026, 11, 3, 0, 0, 0))
            item.compress_type = zipfile.ZIP_DEFLATED
            item.external_attr = 0o100644 << 16
            output.writestr(item, (ROOT / row["path"]).read_bytes())
    with zipfile.ZipFile(archive) as output:
        if output.testzip() is not None or set(output.namelist()) != names:
            raise RuntimeError("Archive integrity failed")
        for row in rows:
            if digest(output.read(row["path"])) != row["sha256"]:
                raise RuntimeError("Archive checksum mismatch")
    archive_hash = digest(archive.read_bytes())
    changes = []
    for entry in git("status", "--porcelain=v1", "-z", "--untracked-files=all").split("\0"):
        if entry:
            changes.append({"status": entry[:2], "path": entry[3:]})
    manifest = {
        "phase": "2C.4C", "decision": "NO-GO", "base_commit": git("rev-parse", "HEAD").strip(),
        "kind": "source-and-built-assets; NOT deployable; vendor no-dev is absent",
        "archive": archive.relative_to(ROOT).as_posix(), "archive_sha256": archive_hash,
        "archive_bytes": archive.stat().st_size, "file_count": len(rows),
        "vendor_no_dev": "NOT VERIFIED; existing workspace vendor is not packaged",
        "workspace_dev_packages_present": local_dev,
        "production_locked_packages": [{"name": p["name"], "version": p["version"]} for p in lock["packages"]],
        "exclusions": [".env local/secrets", "credentials", ".git", "vendor", "node_modules", "tests",
                       "docs/QA routers", "storage", "bootstrap/cache", "public/hot", "public/storage",
                       "database/seeders", "database/factories", "database/data/demo-avatars"],
        "literal_secret_scan": "PASS for limited patterns; allowlist is the primary packaging boundary",
        "migrations": [r for r in rows if r["path"].startswith("database/migrations/")],
        "working_tree_changes": changes, "files": rows,
    }
    target = directory / "release-manifest-2c-4c.json"
    target.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (target.parent / "release-artifact-2c-4c.sha256").write_text(
        archive_hash + "  " + archive.relative_to(ROOT).as_posix() + "\n", encoding="utf-8")
    print(json.dumps({"files": len(rows), "archive_bytes": archive.stat().st_size,
                      "sha256": archive_hash, "local_dev_packages": len(local_dev), "deployable": False}))


if __name__ == "__main__":
    main()
