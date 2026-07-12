# Zana Local Setup

This is the verified local setup path that currently works for `/Users/karinachanmane/Projects/zana/zana-commerce`.

## Verified Versions

| Requirement | Value |
|---|---|
| PHP | 8.5.5 |
| Composer | 2.9.7 |
| Laravel | 12.58.0 |
| Database | MySQL via socket `/tmp/mysql.sock` |
| Node helper | Express-based bridge in `/node` |
| App URL | `http://127.0.0.1:8000` |
| Node URL | `http://127.0.0.1:8888` |

## Local Files Adjusted

| File | Purpose |
|---|---|
| `.env` | Local app URL, DB socket, DB name, Node bridge URL/token |
| `node/.env` | Local Node bridge URL/token |
| `storage/installed` | Marks installer complete so middleware stops redirecting to `/install` |

## Required Local Values

| Key | Local value |
|---|---|
| `APP_URL` | `http://127.0.0.1:8000` |
| `DB_HOST` | `localhost` |
| `DB_DATABASE` | `wadesk_local` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | empty |
| `DB_SOCKET` | `/tmp/mysql.sock` |
| `SERVER_URL` | `http://127.0.0.1:8888` |
| `NODE_WEBHOOK_TOKEN` | shared secret synced between app and node |

## Exact Local Run Commands

```bash
cd /Users/karinachanmane/Projects/zana/zana-commerce
composer install
npm install
cd node && npm install && cd ..
php artisan key:generate
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```bash
cd /Users/karinachanmane/Projects/zana/zana-commerce/node
node index.js
```

Optional worker terminal:

```bash
cd /Users/karinachanmane/Projects/zana/zana-commerce
php artisan queue:listen --tries=1 --timeout=0
```

## Database Steps

```bash
mysql -uroot --socket=/tmp/mysql.sock
CREATE DATABASE IF NOT EXISTS wadesk_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

The schema was already present locally. One narrow migration was applied during execution:

```bash
php artisan migrate --path=database/migrations/2026_07_05_000000_create_contact_tag_table.php --force
```

Do not run all pending migrations blindly yet. `php artisan migrate:status` still shows unrelated pending Instagram and later patch migrations that should be reviewed individually.

## Verified Local Status

| Check | Result |
|---|---|
| `/login` | `200 OK` |
| `/admin` while logged out | Redirects to `/login` |
| `/team-inbox` while logged out | Redirects to `/login` |
| Node bridge root | `200 OK` |
| Installer loop | Fixed by `storage/installed` |

## Verified Login Credentials

| User | Email | Password | Workspace |
|---|---|---|---|
| Admin/test | `test@example.com` | `password` | Test User Admin |
| Zuri owner | `zuri.owner@example.com` | `password` | Zuri Beauty Store |
| Nairobi owner | `nairobi.owner@example.com` | `password` | Nairobi Fashion House |
| Zuri agent | `zuri.agent@example.com` | `password` | Zuri Beauty Store |
| Nairobi agent | `nairobi.agent@example.com` | `password` | Nairobi Fashion House |

## Common Errors and Fixes

| Error | Cause | Fix |
|---|---|---|
| Every route redirects to `/install` | `storage/installed` missing | Restore/create install marker after local bootstrap |
| Contact tags return `500` | `contact_tag` table missing | Run only `2026_07_05_000000_create_contact_tag_table` |
| Node bridge logs Baileys pair failures | Old unofficial session state | Ignore for official-only launch work or clean the node session later |
| App cannot connect to DB | Socket or DB values wrong | Use `/tmp/mysql.sock` and `wadesk_local` |

## Final Local URL

- App: `http://127.0.0.1:8000`
- Node helper: `http://127.0.0.1:8888`

## Local Readiness

WADesk is running locally and is ready for controlled post-audit work.
