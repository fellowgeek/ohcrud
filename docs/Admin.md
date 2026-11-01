# Admin API reference

[Documentation](DOCUMENTATION.md) · [CMS/admin workflow](cms-and-admin.md) · [Authentication](Users.md)

Source: [cAdmin.php](../public/app/controllers/cAdmin.php). All paths below use the default `/ohcrud` prefix. All `cAdmin` endpoints are POST, require permission level 1 or lower, and require `CSRF` in the JSON object body—even reads. The CMS admin UI itself requires permission exactly 1. Class and endpoint restrictions are cumulative.

## Tables

| Endpoint suffix under `/ohcrud/admin/` | Body fields in addition to `CSRF` |
| --- | --- |
| `getTableList/` | None |
| `getTableDetails/` | Optional `TABLE`; presence of `COLUMNS` requests column details |
| `getTableData/` | `TABLE`, `PAGE`, `LIMIT`; optional `ORDER`, `ORDER_BY` |
| `createTableRow/` | `TABLE` plus writable column values |
| `readTableRow/` | `TABLE`, `KEY_COLUMN`, `KEY_VALUE` |
| `updateTableRow/` | `TABLE`, `KEY_COLUMN`, `KEY_VALUE`, plus changed columns |
| `deleteTableRow/` | `TABLE`, `KEY_COLUMN`, `KEY_VALUE` |

```javascript
const response = await fetch('/ohcrud/admin/getTableData/', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        CSRF: __CSRF__, TABLE: 'Pages', PAGE: 1, LIMIT: 20,
        ORDER: 'DESC', ORDER_BY: 'ID'
    })
});
const result = await response.json();
```

`data` contains rows; `pagination` includes `totalRecords`, `totalPages`, `currentPage`, effective `limit`, next/previous flags, and `showing`. Limits are capped at 100, and requested pages are clamped to available results. Table-list display values may be shortened to 100 characters; use `readTableRow` for editing. `ORDER_BY` is checked against schema columns.

`AuthState` is excluded. Generic user creation/update is blocked in favor of dedicated operations. Generic table editing is a broad administrative capability, not an application validation layer. Audit fields are removed from generic write payloads. Generic delete physically removes rows; Files deletion also handles the upload, and Users deletion guards against deleting the last active level-1 administrator. This is not a comprehensive guarantee against every way to remove admin access: account status/permission changes need operational care.

## Users and secrets

| Endpoint suffix | Body fields/behavior |
| --- | --- |
| `createUserRow/` | `USERNAME`, `EMAIL`, `NAME`, `GROUP`, `PERMISSIONS`, `PASSWORD`; supply `STATUS: 1`, `TOTP: 0` for an active non-MFA account |
| `updateUserRow/` | `ID` plus changed fields; omit unchanged password |
| `getUserSecrets/` | `ID`; can return the API token; enrolled TOTP secret and QR fields are false |
| `refreshUserSecrets/` | `ID`, `SECRET_TYPE: 'TOKEN'` rotates token; `TOTP_SECRET` requests are rejected |

Usernames normalize to lowercase alphanumeric, length 3–32. Names accept letters, digits, and spaces up to 32 characters. Group/permission values must be positive. The admin password validator requires at least eight characters and uppercase, lowercase, and digit characters; choose stronger passwords for real accounts. The provisioning CLI separately requires at least 15 characters. Dedicated user endpoints hash supplied passwords; do not prehash them.

These admin user endpoints use their own validation/hashing rather than `Users::create()`. If an account lacks an API token, explicitly generate it through the token operation. TOTP enrollment/replacement/removal belongs to the authenticated owner's [account security flow](Users.md), not generic table fields. Changing security state invalidates affected sessions.

## Logs and SQL

| Endpoint suffix | Body fields/behavior |
| --- | --- |
| `getLogList/` | Lists configured log files |
| `getLogData/` | `LOG`, `PAGE`, `LIMIT`; paginated records, limit capped at 100 |
| `clearLog/` | `LOG`; clears the selected log |
| `runSQLQuery/` | `SQL`, optional `PAGE`, `LIMIT`; disabled unless the configuration switch is true |

Raw SQL is database-administrator access and can bypass application safeguards. Keep `__OHCRUD_ADMIN_ENABLE_SQL_EXECUTION__` false in production unless there is an explicitly controlled operational need. It is not a safe query sandbox.

## Related controllers

| Endpoint | Verb and contract |
| --- | --- |
| `/ohcrud/pages/save/` | POST; `CSRF`, `URL`, `TITLE`, `TEXT`, `STATUS`, optional `THEME`, `LAYOUT` |
| `/ohcrud/pages/restoreDeletePage/` | POST; `CSRF`, `URL`; toggles status |
| `/ohcrud/themes/getThemes/` | GET or POST; level 1; returns theme-to-layout map; no handler CSRF check |
| `/ohcrud/files/upload/` | POST multipart; level 1; top-level `CSRF`, file `0` |
| `/ohcrud/files/image/` | Public GET; `filename`, optional `w`, `h`, `q`, `rand` |

See [media limits](cms-and-admin.md#media-handling), [login endpoints](Users.md), and [Core response fields](Core.md).
