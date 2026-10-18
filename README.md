<p align="center"><img src="brand/technest-logo.png" alt="TechNest" width="262"></p>

# TechNest

*Technology, simplified.* An electronics store (laptops, phones, PCs, accessories) built on Magento Open Source 2.4.9, with English/USD and Vietnamese/VND store views.

The project has two goals: show what was learned about Magento 2 (configuration, custom modules, theming), and serve as a base for later work. The full specification, decisions and phase plan are in [CLAUDE.md](CLAUDE.md).

[![CI](https://github.com/Dunglouis/Magento-Project/actions/workflows/ci.yml/badge.svg?branch=dev)](https://github.com/Dunglouis/Magento-Project/actions/workflows/ci.yml)

The environment runs on Docker, based on [markshust/docker-magento](https://github.com/markshust/docker-magento) 53.1.0.

| Component | Version |
|---|---|
| Magento Open Source | 2.4.9 (with sample data) |
| PHP | 8.5 |
| MariaDB | 11.8 |
| OpenSearch | 3 |
| Valkey (Redis replacement) | 9.1 |
| RabbitMQ | 4.2 |
| nginx | 1.28 |

## 1. Requirements

- Windows 11 with WSL2 (Ubuntu), Linux, or macOS.
- Docker Desktop with WSL Integration enabled for your Ubuntu distro.
- **At least 6 GB of RAM** allocated to Docker. `bin/start` exits immediately if there is less.
- On Windows, set the WSL memory limit in `C:\Users\<your-user>\.wslconfig`:

  ```ini
  [wsl2]
  memory=6600MB
  swap=2GB
  ```

  Then run `wsl --shutdown` in PowerShell and reopen Ubuntu.
- An [Adobe Commerce Marketplace](https://commercemarketplace.adobe.com/customer/accessKeys/) account to get a Public Key and Private Key. Composer needs them to download Magento.
- About 20 GB of free disk space.

## 2. Repository layout

```
.
├── .github/workflows/   CI (GitHub Actions)
├── bin/                 container helper scripts (bin/start, bin/magento, bin/composer...)
├── brand/               logo, mark and favicon (SVG/PNG/ICO)
├── compose*.yaml        container definitions
├── env/*.env.example    environment variable templates (passwords masked)
├── lib/, template/      docker-magento support files
├── scripts/qa.sh        static checks + unit tests (see section 6)
├── tests/qa/            PHPCS and PHPStan configuration
├── CLAUDE.md            project specification and phase plan
└── src/                 Magento code
    ├── composer.json    pins the Magento version and extra packages (vi_VN language pack)
    ├── composer.lock
    ├── app/code/TechNest/   custom modules
    ├── app/design/          custom themes
    └── app/etc/config.php   list of enabled modules
```

Git tracks only custom code. `vendor/`, `pub/`, `generated/`, `var/` and `app/etc/env.php` are regenerated during installation, so they are not in the repository. `app/etc/env.php` holds database passwords and the encryption key: never commit it.

### Custom modules

| Module | Purpose |
|---|---|
| `TechNest_Base` | `en` and `vi` store views, locales, USD/VND currencies and exchange rate, store name (data patches) |

More modules are added phase by phase; see the table in [CLAUDE.md](CLAUDE.md#6-custom-module).

## 3. Installation from scratch

Run every command in the Ubuntu (WSL) terminal, not in PowerShell.

### 3.1. Clone the repository

```bash
mkdir -p ~/Sites
git clone https://github.com/Dunglouis/Magento-Project.git ~/Sites/magento
cd ~/Sites/magento
```

### 3.2. Create `.env` files from the templates

Each `env/*.env.example` file is a template for a real `.env` file. Docker needs the real files to start.

```bash
for f in env/*.env.example; do cp -n "$f" "${f%.example}"; done
```

Then open each file and replace every `changeme` value with a password of your choice:

| File | Variables to set |
|---|---|
| `env/db.env` | `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_INTEGRATION_ROOT_PASSWORD`, `MYSQL_INTEGRATION_PASSWORD` |
| `env/magento.env` | `MAGENTO_ADMIN_PASSWORD` (at least 7 characters, letters and numbers) |
| `env/rabbitmq.env` | `RABBITMQ_DEFAULT_PASS` |

The real `.env` files are blocked by `.gitignore`, so passwords never reach the repository.

### 3.3. Download and install Magento

`bin/download` only runs when the `src/` directory does not exist. Move the custom code aside first:

```bash
mv src src.repo
bin/download community 2.4.9
bin/setup technest.test
```

- `bin/download` downloads Magento into the container. On the first run it asks for your Adobe Public Key and Private Key.
- `bin/setup` installs the database, creates an SSL certificate, adds `technest.test` to the hosts file and enables developer mode. It takes 20 to 40 minutes on a slow machine.

### 3.4. Install sample data and disable 2FA

```bash
bin/init
```

This script installs the Luma sample data, disables two-factor authentication for the admin and extends the admin session lifetime.

### 3.5. Restore the custom code

```bash
cp -r src.repo/app/code/. src/app/code/
cp -r src.repo/app/design/. src/app/design/
cp src.repo/app/etc/config.php src/app/etc/
cp src.repo/composer.json src.repo/composer.lock src/
bin/composer install
bin/copyfromcontainer vendor
bin/magento setup:upgrade
bin/magento indexer:reindex
bin/magento cache:flush
```

`setup:upgrade` runs the data patches of `TechNest_Base`: it renames the default store view to `en`, creates the `vi` store view and sets up currencies and locales.

Check that `git status` is clean, then remove the temporary directory:

```bash
git status
rm -rf src.repo
```

### 3.6. On Windows: hosts file and SSL certificate

`bin/setup` only edits the Ubuntu hosts file. Browsers on Windows read a separate one. Open Notepad as Administrator, open `C:\Windows\System32\drivers\etc\hosts` (switch the file filter to *All Files*) and add:

```
127.0.0.1 technest.test
```

Then run `ipconfig /flushdns` in PowerShell.

The SSL certificate is signed by a local mkcert authority that lives in the `app` container. Windows does not trust it until you install it:

```bash
# Ubuntu (WSL): copy the authority to your Windows Downloads folder
docker cp "$(bin/docker-compose ps -q app)":/root/.local/share/mkcert/rootCA.pem \
  /mnt/c/Users/$(powershell.exe -NoProfile -Command '$env:USERNAME' | tr -d '\r')/Downloads/technest-rootCA.pem
```

```powershell
# PowerShell (no admin needed): trust it for your Windows user, answer Yes
certutil -user -addstore Root "$env:USERPROFILE\Downloads\technest-rootCA.pem"
```

Close every browser window and reopen it. Firefox needs `security.enterprise_roots.enabled = true` in `about:config`.

## 4. URLs

| URL | Purpose |
|---|---|
| https://technest.test | Storefront. Switch language (English / Tiếng Việt) at the top right |
| https://technest.test/?___store=vi | Storefront, Vietnamese store view (prices in VND) |
| https://technest.test/admin | Admin panel. Credentials come from `env/magento.env` |
| http://technest.test:1080 | Mailcatcher, shows emails sent by Magento |
| http://localhost:8080 | phpMyAdmin, database browser |

## 5. Daily commands

| Command | What it does |
|---|---|
| `bin/start` | Start the containers |
| `bin/stop` | Stop the containers |
| `bin/restart` | Restart the containers |
| `bin/status` | Show container status |
| `bin/magento setup:upgrade` | Run after adding a module or changing XML or db_schema files |
| `bin/magento setup:di:compile` | Regenerate DI code (usually only needed in production mode) |
| `bin/magento cache:flush` | Flush all caches |
| `bin/magento indexer:reindex` | Rebuild indexes (prices, categories, search) |
| `bin/cron start` | Enable cron inside the container |
| `bin/log` | Tail the Magento logs |
| `bin/composer require <package>` | Install a package, then run `bin/copyfromcontainer vendor` |
| `bin/xdebug enable` | Turn on Xdebug, then press F5 in VS Code ("Listen for XDebug") |

`src/app/code`, `src/app/design`, `src/app/etc`, `src/var`, `src/generated`, `src/composer.*` and `tests/qa` are synced directly with the container. `src/vendor` on the host is only a copy for the IDE to read. Do not edit it.

To recreate containers, use `bin/docker-compose up -d` (or `bin/restart`), never plain `docker compose up`: plain `docker compose` skips `compose.dev.yaml`, so the containers lose the mounts above.

## 6. Code quality and workflow

### Checks

```bash
scripts/qa.sh            # PHPCS (Magento2 standard) + PHPStan (level 6) + PHPUnit
scripts/qa.sh --compile  # same, plus setup:di:compile (about 3 minutes)
```

The script checks only `src/app/code/TechNest`. PHPUnit runs once a module has a `Test/Unit` folder. CI runs the same script on every push and pull request to `dev` and `main`.

### Branches and commits

| Branch | Role |
|---|---|
| `main` | Stable, deployed to the demo server |
| `dev` | Integration. Feature branches start here and are merged back by pull request |
| `feature/<name>` | One phase or one module, for example `feature/data-seeder` |

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `docs:`, `chore:`, `ci:`, with an optional scope such as `feat(base): ...`.

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| `bin/start` reports `opensearch is unhealthy` | The healthcheck timed out on a slow machine. Wait 60 seconds and run `bin/start` again |
| `rabbitmq exited (1)` with `.erlang.cookie: eacces` in the log | Run `bin/stop`, then `bin/start` |
| `phpfpm` fails with `error mounting ... to rootfs at "/var/www/html/app/code"` | A mounted folder is missing on the host. Run `mkdir -p src/app/code`, then `bin/docker-compose up -d --force-recreate app phpfpm phpfpm-xdebug` |
| Browser says "Your connection is not private" | The mkcert authority is not trusted on Windows. See section 3.6 |
| Port 80 is already in use (Windows) | Stop and disable the IIS services `W3SVC` and `WAS` |
| Composer reports `Could not resolve host` | Container DNS is flaky. Run the command again |
| Docker reports WSL integration stopped | Open Docker Desktop, wait for "Engine running", then open Ubuntu |
| VS Code shows `Undefined type '...Factory'` | Factories are generated at runtime. Run `bin/magento setup:di:compile`, then *Intelephense: Index workspace* |
| Blank page or HTTP 500 | Check `bin/log` and `src/var/report/` |
