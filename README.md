# The Codex

A collaborative **D&D session-notes wiki** for the whole table. Players (and the GM)
write session notes and turn them into interlinked pages for NPCs, places,
organizations, and items — with Obsidian-style `[[wikilinks]]`, backlinks, infobox
panels, custom categories, multiple campaigns, and full page history.

Built with plain **PHP + MySQL**, shipped as a **Docker Compose** stack, with the image
published to **Docker Hub** via **GitHub Actions**.

---

## Quick start (local)

Requires Docker + Docker Compose.

```bash
cp .env.example .env      # then edit the passwords/secret
docker compose up --build -d
```

Open <http://localhost:8080>, register an account, and create your first campaign.

The database schema is applied automatically on first boot (see `docker/migrate.php`),
and MySQL data persists in the `codex_db` volume across restarts and image updates.

---

## Features

- **Accounts** — everyone at the table registers; passwords hashed with `password_hash`.
- **Multiple campaigns** — each user sees the campaigns they belong to; join others with
  an invite code. Old campaigns stay fully browsable.
- **Open wiki** — any campaign member can edit any page. Every save is snapshotted, so
  the full **history** is available and any version can be restored.
- **Custom categories** — each campaign starts with a default tree (Party, Sessions,
  NPCs → Enemies/Friendly, Organizations, Places, Points of Interest, Items) and members
  can add/rename/nest/delete freely.
- **`[[wikilinks]]` + picker** — type `[[` in the editor for autocomplete; link pages
  that don't exist yet ("red links") and they light up once the page is created.
- **Backlinks** — every page shows what links to it.
- **Infobox panels** — flexible key/value fields per page (Race → Human, Status → alive).
- **Rich-text editor** — self-contained (no build step); all stored HTML is sanitized
  with HTMLPurifier.

---

## Deploying + updating (production)

1. Push this repo to GitHub.
2. Add two repository **secrets** (Settings → Secrets and variables → Actions →
   *Secrets*, not Variables): `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN`. The token
   must have **Read & Write** permissions (a read-only token fails with
   `insufficient_scope`).
3. The image name is set in `.github/workflows/docker.yml` via `IMAGE_NAME`
   (default `robzwet/the-codex`) — change it if you fork/rename.
4. Push to `main` (or tag `v1.0.0`) — GitHub Actions builds and pushes the image.
5. On your server, set `DOCKER_IMAGE=robzwet/the-codex:latest` in `.env`, then:

```bash
docker compose pull && docker compose up -d
```

Roll back by pinning a previous image tag in `.env`.

### Database backups

```bash
docker compose exec db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" codex > backup.sql
```

> **Note:** Docker requires a host you control (a VPS or your own machine) — classic
> shared "HTML/PHP/SQL" hosting usually can't run containers.

---

## Project layout

```
public/            Web root (front controller, assets)
app/
  Controllers/     Request handlers
  Models/          Campaign, Category, Page (DB access)
  Lib/             Db, Auth, Csrf, Router, WikiLinks, Sanitizer, ...
  Views/           PHP templates (layouts, partials, pages)
db/schema.sql      Idempotent schema
docker/            Dockerfile entrypoint + migration runner
.github/workflows/ CI that builds + pushes to Docker Hub
```
