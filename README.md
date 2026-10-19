# WW Solar local WordPress

This project runs WordPress 7.1 locally at <http://localhost:9090> or the port set in the variables.

The `wordpress/` directory is mounted directly into the WordPress container. On the first start, the official image copies the complete WordPress installation into that directory, allowing its core, themes, and plugins to be edited from a local IDE.

## Start

1. Review local database values in `.env`. To create a fresh configuration from the template, run `cp .env.dev.example .env` and set unique local passwords.

2. Start the services:
   
   ```bash
   docker compose --env-file .env -f compose.dev.yaml up -d
   ```

3. Open <http://localhost:9090> and finish the WordPress installation.

Docker Desktop shows the WordPress container as `wp-tremenda-flor`. The database is stored in Docker's named `wp-tremenda_flor_mariadb_data` volume, so it survives restarts and `docker compose down`.

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
