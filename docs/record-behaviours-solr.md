# Records in depth: behaviours, Solr, lifecycle

← [Documentation index](README.md)

## Behaviours (Yii-flavoured idea)

**Behaviours** are small, reusable objects that hook into the same **lifecycle events** as your `Record` subclass – think **Yii’s `behaviors()`**, not PSR interfaces: one record class composes cross-cutting rules without inheritance soup.

- Define **`behaviours()`** on your record class: return an array mapping **id → behaviour** (object or `['class' => MyBehaviour::class, 'option' => …]`).
- Each behaviour extends **`Ufw\Model\Behaviour\Base`** and implements the event methods it cares about (`beforeSave`, `afterInsert`, `beforeDataSet`, …). The base class lists supported **method names** in `$behaviourMethods`.
- The record’s **`onBeforeSave`**, **`onAfterInsert`**, etc. **run behaviours first**, then your own `beforeSave` / `afterInsert` on the record – order is predictable.

**Bundled examples** (under `Ufw\Model\Behaviour`):

| Behaviour | Role |
|-----------|------|
| **`Json`** | Serialize/deserialize selected attributes as JSON in/out of scalar DB columns (`serialAttributes`). |
| **`ReadAfterSave`** | After a successful save, re-read the row from SQL by PK so defaults/triggers on the server are visible in memory. |
| **`DefaultFieldValues`**, **`InsertId`**, etc. | Housekeeping around inserts and defaults. |

**Why use them:** timestamps, soft-delete flags, normalizing JSON columns, audit fields – **one behaviour**, many record classes.

---

## Not every record is a SQL row

**`Ufw\Model\Record\Base`** is the common lifecycle (`save`, `delete`, hooks, behaviours). **Persistence is pluggable:**

- **MySQL (default path):** `insert` / `update` / `_delete` talk to **`Ufw\Db\Db`** and `getTableName()`.
- **Apache Solr:** **`Ufw\Model\Record\Solr`** extends `Record\Base` and implements **`getSolrJson()`** + **`getSolrClient()`** (Solarium). Inserts/updates send documents to Solr; delete removes by id. Options like **`commitWithin`**, **`addCommit`**, **`overwrite`** tune indexing.

So a “record” in UFW means **one identifiable thing with a save/delete story** – **not** “always a mysqli table”. App-level classes often subclass **`Record\Solr`** for search documents and **`Record\Base`** (or `Simple`) for relational data; both can share **behaviours** where it makes sense.

---

## Lifecycle (mental model)

1. **`setData` / `beforeDataSet`** – behaviours can normalize incoming arrays (e.g. JSON strings → arrays).
2. **`save`** – `onBeforeSave` → insert or update branch → `onAfterInsert` / `onAfterUpdate` → `onAfterSave`.
3. **`delete`** – `onBeforeDelete` → `_delete` → `onAfterDelete`.

Override **`insert` / `update` / `_delete`** in subclasses to change **where** data lives (SQL vs Solr); keep **hooks** for shared rules.

---

## Real-world–style patterns (abstracted)

These mirror how large UFW apps (e.g. admin + API) tend to use records:

1. **Relational + search:** MySQL record is **source of truth**; a job or `afterSave` behaviour pushes a **Solr document** (or a separate `Record\Solr` updated in the model layer).
2. **JSON in a column:** Attach **`Behaviour\Json`** with `serialAttributes` so PHP works with arrays while the DB stores a string.
3. **Fresh row after insert:** **`ReadAfterSave`** when triggers/default expressions must be visible immediately.

No app-specific table names here – only the **shape** of the idea.

---

**Next:** [InfoHash](info-hash.md) – in-memory collections with secondary indexes.
