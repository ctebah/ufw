<?php
namespace Ufw\Model\Record;

/**
 * Solr-backed record: builds documents via {@see getSolrJson()}, sends updates through {@see getSolrClient()} and
 * {@see getSolrConnection()}, with optional {@see $commitWithin} / {@see $addCommit} / {@see $overwrite} tuning.
 *
 * @property-read int|string|null $id Primary key in row data (declare other Solr/document fields on concrete subclasses).
 */
abstract class Solr extends Base
{
    /**
     * @var string|null
     */
    protected $solrConnection = null;

    /**
     * @var \Solarium\Client|null
     */
    protected $solrClient = null;

    /**
     * Add (hard) auto-commit after each update
     *
     * @var boolean
     */
    protected $addCommit = false;

    /**
     * milliseconds to commitWithin for update command (or null)
     *
     * @var int
     */
    protected $commitWithin = 60000;

    /**
     * update command, whether to overwrite existing data
     *
     * @var boolean
     */
    protected $overwrite = true;

    /**
     * @return mixed
     */
    abstract public function getSolrJson();

    /**
     *
     * @return \Solarium\Client
     */
    abstract public function getSolrClient();

    /**
     * @param array<string, mixed> $data
     */
    public function __construct($data = array())
    {
        parent::__construct($data);
    }

    /**
     *
     * @return string|null
     */
    public function getSolrConnection()
    {
        return $this->solrConnection;
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function _delete($opts = [])
    {
        $id = $this->id;
        if (!is_int($id) && !is_string($id)) {
            return false;
        }
        $client = $this->getSolrClient();
        $update = $client->createUpdate();
        $update->addDeleteById($id);
        $update->addCommit();
        return $client->update($update, $this->getSolrConnection());
    }

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    protected function insert($opts = [])
    {
        $client = $this->getSolrClient();
        $update = $client->createUpdate();
        $document = $update->createDocument($this->getSolrJson());
        $update->addDocuments([$document], $this->getOverwrite(), $this->getCommitWithin());
        if ($this->getAddCommit()) {
            $update->addCommit();
        }
        $client->update($update, $this->getSolrConnection());

        return $this;
    }

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    protected function update($opts = [])
    {
        return $this->insert($opts);
    }

    /**
     * @return bool
     */
    public function getOverwrite()
    {
        return $this->overwrite;
    }

    /**
     * @return $this
     */
    public function setOverwrite(bool $value)
    {
        $this->overwrite = !!$value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAddCommit()
    {
        return $this->addCommit;
    }

    /**
     * @return $this
     */
    public function setAddCommit(bool $value)
    {
        $this->addCommit = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getCommitWithin()
    {
        return $this->commitWithin;
    }

    /**
     * @return $this
     */
    public function setCommitWithin(int $value)
    {
        $this->commitWithin = $value;
        return $this;
    }
}
