# Yuva — WordPress Backend (Headless)

WordPress runs **headless**: it is a content + schema store that serves JSON to the
Next.js frontend (in `../frontend`) over the REST API. We do not use WordPress for
rendering. As full-stack developers we treat the content schema as **code** — field
groups are version-controlled JSON, authored/generated and pushed via git.

> **New here? Read this whole file once.** It explains the contract, the folder
> layout, how to run locally, how data flows to the frontend, and how deploys work.

---

## 1. What's tracked in this repo (and what isn't)

We track **only what we author**:

| Tracked in git | Not tracked (server/Docker-managed) |
| --- | --- |
| `wordpress/wp-content/themes/twentytwentyfive/` (incl. `scf-json/` schema) | WordPress core (`wp-admin`, `wp-includes`, …) |
| `wordpress/wp-content/mu-plugins/` (our must-use plugins) | Third-party plugins (SCF, Yoast, Classic Editor) |
| Docker + CI config, this README | `uploads/`, caches, the database, `.env` |

The `.gitignore` enforces this with a **deny-all + allow-list** on `wp-content`, so a
teammate can never accidentally commit a plugin update or an upload, and one person's
push can't delete another's work.

Third-party plugins are installed on the server (and present locally for development)
but are **not** in git. The ones we rely on:

- **Secure Custom Fields (SCF)** — the content schema engine (WordPress.org fork of ACF).
- **Yoast SEO** — SEO metadata, exposed to the frontend as `yoast_head_json`.
- **Classic Editor** — simpler admin editing.

---

## 2. The frontend contract (do not break this)

The frontend **defines** what the backend must return. The source of truth is the
frontend's parser/types, not anything here:

- `frontend/src/lib/parsers/home/home.parser.ts`
- `frontend/src/types/home/home.types.ts`
- `frontend/src/constants/wp.constants.ts`

What it expects:

- **Home page:** `GET /wp-json/wp/v2/pages?slug=home&scf_format=standard&_fields=scf,slug,title,yoast_head_json,seo,modified_gmt`
  - Fields come back under a **`scf`** key (our mu-plugin mirrors SCF's native `acf`
    key to `scf` and makes `scf_format` the formatting control).
  - 8 section groups (snake_case): `hero_section`, `stats_section`,
    `what_we_do_section`, `photo_carousel_section`, `large_banner_section`,
    `csr_partners_section`, `voices_section`, `our_reach_section`.
  - Images are returned slimmed to `{ url, alt }`.
- **Navbar / Footer (global):** `GET /wp-json/yuva/v1/navbar` and `/wp-json/yuva/v1/footer`.

If you add a field, add it to the SCF JSON **and** make sure the frontend parser reads it.

---

## 3. Folder layout

```
backend/
├── wordpress/wp-content/
│   ├── themes/twentytwentyfive/
│   │   └── scf-json/                 ← version-controlled SCF field groups (schema-as-code)
│   │       ├── pages/                  group_home_page_fields.json
│   │       ├── components/             (reusable component groups; empty for now)
│   │       └── options/                group_navbar_fields.json, group_footer_fields.json
│   └── mu-plugins/                   ← our must-use plugins (always-on backend logic)
│       ├── yuva-config.php             single site-wide config (CORS origins)
│       ├── scf-json-paths.php          points SCF at scf-json/{pages,components,options}
│       ├── scf-location-page-slug.php  "page_slug" location rule (env-portable, no page IDs)
│       ├── scf-rest-output.php         exposes `scf` key, slims media to {url,alt}, scf_format gate
│       ├── scf-json-sync-detect.php    makes the SCF "Sync" link appear on any JSON change
│       ├── scf-options-skeleton.php    returns field shape for unsaved options pages
│       ├── scf-navbar-options.php      Navbar options page + /yuva/v1/navbar route
│       ├── scf-footer-options.php      Footer options page + /yuva/v1/footer route
│       └── allow-svg-uploads.php       admin-only SVG uploads
├── docker-compose.yml                base stack (shared)
├── docker-compose.dev.yml            local dev (ports 8080/8081, phpMyAdmin, WP_DEBUG)
├── docker-compose.stg.yml            staging
├── docker-compose.prod.yml           production
├── docker-compose.test.yml           throwaway integration-test stack (ephemeral DB)
├── rundocker.sh / rundocker.cmd      start/stop helper (dev|stg|prod)
├── uploads.ini                       PHP upload limits
├── .env.example                      copy to .env and fill in
└── .github/workflows/main.yml        FTP deploy to Hostinger on push to main
```

---

## 4. Running locally

```bash
cp .env.example .env        # then edit .env with strong values
./rundocker.sh dev          # (Windows: rundocker.cmd dev)
```

- WordPress: <http://localhost:8080>  (admin at `/wp-admin`)
- phpMyAdmin: <http://localhost:8081>
- Stop: `./rundocker.sh dev down`

**First-time setup inside wp-admin:**

1. Activate the plugins: Secure Custom Fields, Yoast SEO, Classic Editor.
2. Create a Page with the slug **`home`** (Pages → Add New → set permalink to `home`).
3. SCF → Field Groups → click **Sync** on any group showing "Sync available". This
   loads our `scf-json/` definitions into the database.
4. Edit the `home` page → fill in the section fields → Update.
5. Global Settings: **Navbar** and **Footer** menus appear in wp-admin → fill them in.

> Schema lives in git; the **values** you type live in the database. After every
> deploy/pull, re-run **Sync** so the DB schema matches the repo.

---

## 5. The schema-as-code workflow

1. Author or AI-generate a field group as JSON in
   `themes/twentytwentyfive/scf-json/{pages|components|options}/` (one file per group).
   Or create/edit it in wp-admin → SCF writes the JSON back to the right subfolder
   automatically.
2. Commit + push → CI deploys it to the server.
3. On the target site: SCF → Field Groups → **Sync**.

Rules:
- One `.json` file per field group; filename = the group `key`.
- **Never change a field group's `key`** by hand — syncs match on it.
- Use the `page_slug` location rule (`{"param":"page_slug","operator":"==","value":"home"}`),
  not page IDs — IDs differ between environments.

---

## 6. Deploys (CI)

`.github/workflows/main.yml` deploys on push to `main` via FTPS to Hostinger.

- **Source of truth = repo.** Each job mirrors one folder (`themes/twentytwentyfive/`
  and `mu-plugins/`) with `dangerous-clean-slate: true`, so the server is made to match
  the repo exactly — deleted-in-git means deleted-on-server, no ghost files.
- It **never** touches `wp-content` as a whole, so uploads, third-party plugins, and
  core on the server are safe, and cPanel/Hostinger tools keep working.

Required repo secrets: `FTP_HOST`, `FTP_USER`, `FTP_PASS` (Settings → Secrets → Actions).

---

## 7. Testing the REST output

`docker-compose.test.yml` is a throwaway stack (ephemeral DB on tmpfs, port 18080) for
verifying the REST shape without touching dev/stg/prod:

```bash
docker compose -f docker-compose.test.yml up -d
# ... install WP, activate plugins, create the home page, Sync, then:
curl 'http://localhost:18080/wp-json/wp/v2/pages?slug=home&scf_format=standard&_fields=scf'
docker compose -f docker-compose.test.yml down -v   # wipes everything
```
