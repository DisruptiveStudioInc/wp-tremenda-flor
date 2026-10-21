# WW Solar local WordPress

This project runs WordPress 7.1 locally at <http://localhost:9090> or the port set in the variables.

The WordPress service uses the official PHP 8.3 Apache image, pinned by digest to a verified build containing PHP 8.3.36. This release fixes CVE-2026-109618 and CVE-2026-109619; see the [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-5). PHP comes from this Docker image, independently of the WordPress files mounted from the host.

The `wordpress/` directory is mounted directly into the WordPress container. On the first start, the official image copies the complete WordPress installation into that directory, allowing its core, themes, and plugins to be edited from a local IDE.

## Start

1. Review local database values in `.env`. To create a fresh configuration from the template, run `cp .env.dev.example .env` and set unique local passwords.

2. Start the services:
   
   ```bash
   docker compose --env-file .env -f compose.dev.yaml up -d
   ```

3. Open <http://localhost:9090> and finish the WordPress installation.

Docker Desktop shows the WordPress container as `wp-tremenda-flor`. The database is stored in Docker's named `wp-tremenda_flor_mariadb_data` volume, so it survives restarts and `docker compose down`.

## Apply the PHP security update

For an existing installation, pull the pinned image and recreate the WordPress container:

```bash
docker compose --env-file .env -f compose.dev.yaml pull wordpress
docker compose --env-file .env -f compose.dev.yaml up -d wordpress
docker compose --env-file .env -f compose.dev.yaml exec wordpress php -v
```

Verify that PHP reports 8.3.36. A container restart alone does not replace its image. These commands retain the database volume and the mounted site files.

For future PHP updates, pull `wordpress:7.1-php8.3-apache`, verify its PHP version, and update the digest in `compose.dev.yaml` before recreating the container. The digest pin must change to receive newer security releases.

## Stop and reset

Stop the stack without deleting site data:

```bash
docker compose down
```

To remove the local database and start the installer again, run:

```bash
docker compose down --volumes
```

This does not remove the host `wordpress/` files. To fully recreate WordPress files as well, stop the stack, remove the contents of `wordpress/`, then start it again.
