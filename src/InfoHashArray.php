<?php
namespace Ufw;

/**
 * {@see InfoHash} variant fed from array-shaped rows: {@see addEntry()} / {@see add_entry_array()} populate {@see $info} and {@see $hash}.
 *
 * Each hash key maps to a list of id values (unlike scalar mapping in {@see InfoHash}).
 */
class InfoHashArray extends InfoHash
{

    /**
     * @param array-key $hashKey
     * @param array-key $idKey
     * @return void
     */
    protected function appendIdToHashBucket($hashKey, $idKey)
    {
        if (!array_key_exists($hashKey, $this->hash) || !is_array($this->hash[$hashKey])) {
            $this->hash[$hashKey] = array();
        }
        $bucket = $this->hash[$hashKey];
        $bucket[] = $idKey;
        $this->hash[$hashKey] = $bucket;
    }

    /**
     * @param object $object
     * @return $this
     */
    public function addEntry($object)
    {
        $id = $this->id_field_name;
        $name = $this->hash_field_name;
        $idKey = $this->writeArrayKey($object->$id);
        $this->info[$idKey] = $object;
        if (is_array($x = $object->$name)) {
            foreach ($x as $value) {
                $this->appendIdToHashBucket($this->writeArrayKey($value), $idKey);
            }
        } else {
            $this->appendIdToHashBucket($this->writeArrayKey($object->$name), $idKey);
        }
        return $this;
    }

    /**
     * @param object $object
     * @return $this
     * @deprecated
     */
    public function add_entry($object)
    {
        return $this->addEntry($object);
    }

    /**
     * @param array<mixed, mixed> $object
     * @return $this
     */
    public function add_entry_array($object)
    {
        $id = $this->id_field_name;
        $name = $this->hash_field_name;
        $idKey = $this->writeArrayKey($object[$id]);
        $this->info[$idKey] = (object) $object;
        if (is_array($x = $object[$name])) {
            foreach ($x as $value) {
                $this->appendIdToHashBucket($this->writeArrayKey($value), $idKey);
            }
        } else {
            $this->appendIdToHashBucket($this->writeArrayKey($object[$name]), $idKey);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function rehash()
    {
        $name = $this->hash_field_name;
        $this->hash = array();
        if ($this->info) {
            foreach ($this->info as $key => $value) {
                $this->appendIdToHashBucket($this->writeArrayKey($value->$name), $key);
            }
        }
        return $this;
    }
}
