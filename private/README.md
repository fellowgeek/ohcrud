# Development infrastructure

[Project home](../README.md) · [Documentation](../docs/DOCUMENTATION.md)

Use [Getting started](../docs/getting-started.md#https-development-with-compose) for the complete HTTPS Compose setup, certificate generation, mounts, database configuration, and administrator provisioning. The development hostname is `ohcrud.test`.

- [Migrations](../docs/Migrations.md): schema provisioning and upgrade commands.
- [Deployment and testing](../docs/deployment-and-testing.md): production boundaries and test commands.
- [Test runner README](tests/README.md): disposable HTTP/HTTPS fixtures and focused checks.

Prefer `python3 private/tests/full_hurl.py` from the repository root for the full HTTPS suite. The legacy `hurl/test.sh` uses `ohcrud.loc` and requires a separately configured disposable database; it is not the Compose setup command.
