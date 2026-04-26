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

## Mental model

One façade (`Db::instance()`), **multiple logical databases**, **explicit `conn_ident`** when it matters. No hidden “default ORM connection” – you stay responsible for which pipe you open.

---

**Next:** [Models and records](models-and-records.md) – service layer vs `Record`, Solr pointer.
