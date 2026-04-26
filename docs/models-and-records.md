# Models and records

← [Documentation index](README.md)

## Record (`Ufw\Model\Record\Base`)

A **Record** is an active-record-like object around **one primary key shape** – usually **one SQL table**, but not always (Solr documents use **`Record\Solr`**; see below).

- **`$tableName`** – table name for the default MySQL path.
- **`$primaryKeyField`** – string or array for composite keys.
- **`$dbconnIdent`** – which `Db` connection to use (see [database.md](database.md)).
- **`$isNewRecord`** – whether `save()` should INSERT or UPDATE.

**Lifecycle hooks** on the record: `beforeSave`, `afterSave`, `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`, plus `beforeDataSet` / `afterDataSet` for incoming data.

**Behaviours** – reusable objects that attach to those same events (idea **in the spirit of Yii’s `behaviors()`**): declare them in **`behaviours()`** on the record class. See **[record-behaviours-solr.md](record-behaviours-solr.md)** for detail, bundled behaviours, and Solr.

Hydration from MySQL: `static::dbfactory($row)`; `Db::fetch_object` / `fetch_all` set `isNewRecord` to false when loading.

**`ReadOnlyRecord`** (`Ufw\Model\Record\ReadOnlyRecord`) – abstract base that overrides persistence so `save()` / `delete()` always throw (e.g. rows mapped from read-only SQL views). Older code may have extended a class named `ReadOnly`; that name is invalid on **PHP 8.1+** because `readonly` is a reserved keyword, so use **`ReadOnlyRecord`** instead.

## Model (`Ufw\Model\Base`)

A **Model** is the **domain/service layer** for a feature:

- Declares **`$recordClassName`** pointing to the concrete `Record` class (often `\Model\Record\YourTable`).
- Gets **`getDb()`** via `Db::instance()` and **`getApplication()`** via `Registry`.
- Typically exposes `getUser()` and feature-specific methods (`one($id)`, `search()`, …).

**Pattern:** controllers call **models**; models use **records** and `Db` for persistence. You can skip models for trivial CRUD, but they pay off when logic grows.

## Relations (1–1, 1–N, N–N)

UFW’s **`Record\Base`** ships a small **hook surface** for data that is not plain columns on the main row. Defaults are no-ops; you override where your schema needs it.

| Hook / API | Role |
|------------|------|
| **`getRelationsData()`** | Return an array merged into **`getData()`** (e.g. nested keys for forms or APIs). Default: `[]`. |
| **`readRelationsData()`** | Load related state from the DB after the main row exists (e.g. fill child lists). Default: returns `$this`. |
| **`saveRelationsData()`** | Persist related state after the main row **`insert`/`update`** succeeds. Default: returns `$this`. |
| **`save($withRelationsData = false, …)`** | When **`true`**, calls **`saveRelationsData()`** after a successful main save. |

**1–1** (e.g. profile extends user): usually a **foreign key on the same table** or a second table you treat as one logical row - store fields on one **`Record`** or load the second row in the **model**; no framework magic required.

**1–N** (children owned by parent): load children in **`readRelationsData()`** or in the **model**; insert/update/delete children in **`saveRelationsData()`** or dedicated model methods - use **`Db::simple_*`** with the same **`conn_ident`** as the parent record.

**N–N** (pivot table): **`protected function saveSimpleRelation($tableName, $currentTableRefField, $relationRefField, $newRelationValues)`** syncs a pivot (e.g. `a_b`) to a **set of related IDs**: inserts missing links, deletes removed ones, keyed by the current record’s primary key. It is meant for the classic two-column pivot shape; anything richer (ordering, payload columns) belongs in custom **`saveRelationsData()`** logic.

**Forms:** relation fields often appear on the same form as the main entity - validate with **`Form\Base`**, then map POST data into **`getRelationsData()`** / **`saveRelationsData()`** overrides. See **[forms-and-validation.md](forms-and-validation.md)**.

**End-to-end examples** (CRUD, listings, optional extras) may live outside core UFW - e.g. **[ufw-extras](https://github.com/ctebah/ufw-extras)** recipes/demos when published.

## Record\Solr and non-SQL persistence

**`Ufw\Model\Record\Solr`** extends `Record\Base` and persists to **Apache Solr** (Solarium): `getSolrJson()`, `getSolrClient()`, `insert`/`update`/`delete` map to Solr update/delete – not mysqli. A search document can be a first-class **record** alongside MySQL rows in the same app.

Details: **[record-behaviours-solr.md](record-behaviours-solr.md)**.

## If `Record\Base` is not your taste

UFW does **not** require every row to flow through `Ufw\Model\Record\Base`. That class is a **default** active-record-style path: behaviours, SQL/Solr hooks, `Db` integration. If you prefer **plain arrays**, **DTOs**, **another ORM**, or a **thinner** base – use them in your **models** and call `Db` (or something else) directly. Many apps mix **one** custom record base for core tables and **ad hoc** queries elsewhere. **Replace** the base in *your* namespace, not by forking the framework.

---

**Next:** [Records: behaviours & Solr](record-behaviours-solr.md) – behaviours, Solr lifecycle, concrete patterns.
