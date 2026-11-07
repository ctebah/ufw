<?php

namespace Solarium;

/**
 * Analysis-only stand-in. Solarium is an application dependency, not a UFW package.
 * This file is listed in phpstan.neon.dist scanFiles and is not autoloaded.
 */
class Client
{
    public function createUpdate(): QueryType\Update\Query
    {
        return new QueryType\Update\Query();
    }

    /**
     * @param QueryType\Update\Query $update
     * @param string|null $endpoint
     * @return mixed
     */
    public function update($update, $endpoint = null)
    {
    }
}

namespace Solarium\QueryType\Update;

class Query
{
    /**
     * @param int|string $id
     * @return $this
     */
    public function addDeleteById($id)
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function addCommit()
    {
        return $this;
    }

    /**
     * @param mixed $fields
     * @return object
     */
    public function createDocument($fields)
    {
        return (object) [];
    }

    /**
     * @param array<int, object> $documents
     * @param bool $overwrite
     * @param int|null $commitWithin
     * @return $this
     */
    public function addDocuments($documents, $overwrite = true, $commitWithin = null)
    {
        return $this;
    }
}
