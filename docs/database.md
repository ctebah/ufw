# Database connections

← [Documentation index](README.md)

## Configuration

`Ufw\Db\Db` is a **singleton**. Connection definitions are loaded from your app (typically **`APPLICATION_PATH/config/dbconns.php`**) into `$db_connections` keyed by **connection identifier strings** (`conn_ident`), e.g. `master_server`, `reports`, `shard_2`.

Each entry usually includes host, user, password, optional `dbname`, and optional **`collation`** / **`time_zone`** applied on connect. **`Db::connect()`** always uses **`mysqli_connect`** with an explicit TCP port: **`3306`** when **`port`** is omitted or empty, otherwise the configured value; optional **`socket`** is passed as the sixth argument (empty string if omitted). A non-empty **`dbname`** is the fourth argument to **`mysqli_connect`**, which sets the default database for that connection - no separate **`mysqli_select_db`** call is required.

## Choosing a connection

Almost every `Db` method that runs SQL accepts **`$conn_ident`**:

- If omitted, the operation uses the **last used** connection (tracked internally) – rely on this only when you are sure of ordering.
- For **full control**, pass **`$conn_ident` explicitly** on every `query`, `simple_insert`, `simple_select_query`, `fetch_all`, etc.

## Records

`Ufw\Model\Record\Base` defines:

```php
protected static $dbconnIdent = 'master_server';
```

Override per table/record class so **each entity declares its connection** without scattering magic strings in queries.

## Helpers

- **`simple_insert` / `simple_update` / `simple_delete` / `simple_select_query`**: build SQL from arrays; values are escaped via the DB layer – still prefer clarity and review for injection safety.
- **`fetch_all`**: can hydrate rows into `Record` subclasses when the class extends `Model\Record\Base`.
- **Query logging** (debugging only): define the constant **`DBLOG_ENV`** as truthy in your **local / dev** bootstrap (not `config/app.php` you ship to production). It enables shutdown-time SQL logging - leave undefined or false in prod.

## Errors

`query()` logs failed statements to `application/log/error_dblog.tmp` and returns `false`. Errno `1062` (duplicate key) is not logged. `2006` / `2013` retry the connection once, using the ident of the connection that ran the statement. A call that omitted `conn_ident` still reconnects that connection, not an empty map key. A failed `connect` is logged the same way and returns `false` (or throws, when the flag below is on). `SET NAMES` and `time_zone` run only after the connection exists, so a refused connect does not become a `TypeError` on PHP 8.

`Db::setThrowOnError(true)` keeps that log, then throws `\Ufw\Db\QueryException` (including `1062`). The default stays off. The mysqli report mode stays `MYSQLI_REPORT_OFF`, including on PHP 8.1+ where mysqli would otherwise throw on its own.

The first `Db` constructed in the process reads an optional top-level `___opts` array from `config/dbconns.php` and then removes that key from the connection map. `___opts['throwOnError']` sets the flag; omit it to keep the silent default. `___opts['logFile']` replaces `application/log/error_dblog.tmp`; `false` or `''` turns the file off. `___opts['logPost']` defaults to true and includes `$_POST` in that log; `false` skips the POST block. The log line format otherwise stays the same. Further keys can be added under `___opts` without becoming a `conn_ident`. `setThrowOnError()` still changes the flag later in the same process.

`config/app.php` key `db.class` names a subclass of `\Ufw\Db\Db`. `getDb()` on the record, model, and controller returns that subclass when the application config is already loaded. `\Ufw\Db\Db::instance()` itself always builds `\Ufw\Db\Db`. Omit the key, or call `getDb()` before `setConfig()`, and `getDb()` stays on `\Ufw\Db\Db`.

## Mental model

One façade (`Db::instance()`), **multiple logical databases**, **explicit `conn_ident`** when it matters. No hidden “default ORM connection” – you stay responsible for which pipe you open.

---

**Next:** [Models and records](models-and-records.md) – service layer vs `Record`, Solr pointer.
