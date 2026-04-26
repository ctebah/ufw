<?php
namespace Ufw\Model\Record;

/**
 * Solr-backed record: builds documents via {@see getSolrJson()}, sends updates through {@see getSolrClient()} and
 * {@see getSolrConnection()}, with optional {@see $commitWithin} / {@see $addCommit} / {@see $overwrite} tuning.
 */
abstract class Solr extends Base
{
    protected $solrConnection = null;

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

    abstract public function getSolrJson();

    /**
     *
     * @return \Solarium\Client
     */
    abstract public function getSolrClient();

    public function __construct($data = array())
    {
        parent::__construct($data);
    }

    /**
     *
     * @return string
     */
    public function getSolrConnection()
    {
        return $this->solrConnection;
    }

    protected function _delete($opts = [])
    {
        $this->onBeforeDelete();
        $client = $this->getSolrClient();
        $update = $client->createUpdate();
        $update->addDeleteById($this->id);
        $update->addCommit();
        $result = $client->update($update, $this->getSolrConnection());
        $this->onAfterDelete($result);
        
        return $result;
    }

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

    protected function update($opts = [])
    {
        return $this->insert($opts);
    }

    public function getOverwrite()
    {
        return $this->overwrite;
    }

    public function setOverwrite(bool $value)
    {
        $this->overwrite = !!$value;
        return $this;
    }

    public function getAddCommit()
    {
        return $this->addCommit;
    }

    public function setAddCommit(bool $value)
    {
        $this->addCommit = $value;
        return $this;
    }

    public function getCommitWithin()
    {
        return $this->commitWithin;
    }

    public function setCommitWithin(int $value)
    {
        $this->commitWithin = $value;
        return $this;
    }
}
