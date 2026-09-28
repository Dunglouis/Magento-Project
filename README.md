# Magento 2 Learning Project

A hands-on project for learning Magento 2 by building real features. All custom code lives in the `Louis_Brand` module (product brand management) and the `Louis/learning` theme.

The environment runs on Docker, based on [markshust/docker-magento](https://github.com/markshust/docker-magento) 53.1.0.

| Component | Version |
|---|---|
| Magento Open Source | 2.4.9 |
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
├── bin/                 container helper scripts (bin/start, bin/magento, bin/composer...)
├── compose*.yaml        container definitions
├── env/*.env.example    environment variable templates (passwords masked)
├── lib/, template/      docker-magento support files
└── src/                 Magento code
    ├── composer.json    pins the Magento version and any extra packages
    ├── composer.lock
    ├── app/code/        custom modules (Louis_Brand)
    └── app/design/      custom themes (Louis/learning)
```

Git tracks only custom code. `vendor/`, `pub/`, `generated/`, `var/` and `app/etc/env.php` are regenerated during installation, so they are not in the repository.

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
bin/setup magento.test
```

- `bin/download` downloads Magento into the container. On the first run it asks for your Adobe Public Key and Private Key.
- `bin/setup` installs the database, creates an SSL certificate, adds `magento.test` to the hosts file and enables developer mode. It takes 20 to 40 minutes on a slow machine.

### 3.4. Install sample data and disable 2FA

```bash
bin/init
```

This script installs the Luma sample data, disables two-factor authentication for the admin and extends the admin session lifetime.

### 3.5. Restore the custom code

```bash
cp -r src.repo/app/code/. src/app/code/
cp -r src.repo/app/design/. src/app/design/
bin/magento setup:upgrade
bin/magento cache:flush
```

If the repository requires extra Composer packages, also copy `composer.json` and `composer.lock`, then reinstall:

```bash
cp src.repo/composer.json src.repo/composer.lock src/
bin/composer install
bin/copyfromcontainer vendor
```

Check that `git status` is clean, then remove the temporary directory:

```bash
git status
rm -rf src.repo
```

### 3.6. On Windows: add the domain to the hosts file

`bin/setup` only edits the Ubuntu hosts file. Browsers on Windows read a separate one. Open Notepad as Administrator, edit `C:\Windows\System32\drivers\etc\hosts` and add:

```
127.0.0.1 magento.test
```

## 4. URLs

| URL | Purpose |
|---|---|
| https://magento.test | Storefront |
| https://magento.test/admin | Admin panel. Credentials come from `env/magento.env` |
| http://magento.test:1080 | Mailcatcher, shows emails sent by Magento |
| http://localhost:8080 | phpMyAdmin, database browser |

## 5. Daily commands

| Command | What it does |
|---|---|
| `bin/start` | Start the containers |
| `bin/stop` | Stop the containers |
| `bin/status` | Show container status |
| `bin/magento setup:upgrade` | Run after adding a module or changing XML or db_schema files |
| `bin/magento setup:di:compile` | Regenerate DI code (usually only needed in production mode) |
| `bin/magento cache:flush` | Flush all caches |
| `bin/magento indexer:reindex` | Rebuild indexes (prices, categories, search) |
| `bin/cron start` | Enable cron inside the container |
| `bin/log` | Tail the Magento logs |
| `bin/composer require <package>` | Install a package, then run `bin/copyfromcontainer vendor` |

`src/app/code`, `src/app/design`, `src/app/etc`, `src/var`, `src/generated` and `src/composer.*` are synced directly with the container. `src/vendor` on the host is only a copy for the IDE to read. Do not edit it.

## 6. Troubleshooting

| Symptom | Fix |
|---|---|
| `bin/start` reports `opensearch is unhealthy` | The healthcheck timed out on a slow machine. Wait 60 seconds and run `bin/start` again |
| `rabbitmq exited (1)` with `.erlang.cookie: eacces` in the log | Run `bin/stop`, then `bin/start` |
| Port 80 is already in use (Windows) | Stop and disable the IIS services `W3SVC` and `WAS` |
| Composer reports `Could not resolve host` | Container DNS is flaky. Run the command again |
| Docker reports WSL integration stopped | Open Docker Desktop, wait for "Engine running", then open Ubuntu |
| Blank page or HTTP 500 | Check `bin/log` and `src/var/report/` |
