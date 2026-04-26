<?php
namespace Ufw;

/**
 * {@see InfoHash} variant fed from array-shaped rows: {@see addEntry()} / {@see add_entry_array()} populate {@see $info} and {@see $hash}.
 */
class InfoHashArray extends InfoHash
{

    function addEntry($object)
    {
        $id = $this->id_field_name;
        $name = $this->hash_field_name;
        $this->info[$object->$id] = $object;
        if (is_array($x = $object->$name)) {
            foreach ($x as $value) {
                $this->hash[$value][] = $object->$id;
            }
        } else {
            $this->hash[$object->$name][] = $object->$id;
        }
    }

    /**
     * @see @method addEntry
     * @deprecated
     */
    function add_entry($object)
    {
        return $this->addEntry($object);
    }
    
    public function add_entry_array($object)
    {
        $id = $this->id_field_name;
        $name = $this->hash_field_name;
        $this->info[$object[$id]] = (object) $object;
        if (is_array($x = $object[$name])) {
            foreach ($x as $value)
                $this->hash[$value][] = $object[$id];
        } else {
            $this->hash[$object[$name]][] = $object[$id];
        }
    }

    function rehash()
    {
        $name = $this->hash_field_name;
        $this->hash = array();
        if ($this->info) {
            foreach ($this->info as $key => $value)
                $this->hash[$value->$name][] = $key;
        }
    }
}
