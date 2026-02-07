# Run with Docker Compose (development/test)

1. Copy `.env.example` to `.env` and customize if needed.

2. Build and start containers:

# Run with Docker Compose (development/test)

1. Copy `.env.example` to `.env` and customize if needed.

2. Build and start containers:

```bash
docker-compose up --build -d
```

3. Import the database (once) into the MySQL container:

```bash
# from project root
docker cp database/lagusan_coffee_db.sql $(docker-compose ps -q db):/tmp/lagusan_coffee_db.sql
docker exec -it $(docker-compose ps -q db) sh -c 'mysql -u root -p"${MYSQL_ROOT_PASSWORD}" ${MYSQL_DATABASE} < /tmp/lagusan_coffee_db.sql'
```

4. Open the app at http://localhost:8080 and phpMyAdmin at http://localhost:8081

Notes:
- `config.php` now reads DB and site values from environment variables, compatible with Docker.
- For production, set `SHOW_ERRORS=0` and secure your DB credentials.

CI / GitHub Container Registry:
- This repository includes a GitHub Actions workflow at `.github/workflows/docker-build.yml` that builds the Docker image and pushes it to GitHub Container Registry (GHCR) on pushes to `main`/`master`.
- To use GHCR, enable Packages for your account/org and optionally create a `PERSONAL_ACCESS_TOKEN` with `write:packages` if you want to push from other machines. The workflow uses `GITHUB_TOKEN` by default.

Pull the published image:
```bash
docker pull ghcr.io/<your-org-or-username>/lagusan-coffee-skydeck:latest
```
