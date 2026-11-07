<?php
namespace Ufw\Tests\Unit;

/**
 * Exposes {@see \Ufw\UploadedTmpFile} hooks for disk-path / stream behaviour in tests.
 */
class UploadedTmpFileDiskMoveProbe extends \Ufw\UploadedTmpFile
{
    private $forceNoDiskPath = false;

    /** @var bool|null */
    private $forceCliStreamWrite = null;

    public function forceNoDiskPathProperty($flag)
    {
        $this->forceNoDiskPath = (bool) $flag;

        return $this;
    }

    /**
     * @param bool $useCliWhenTrue null restores default SAPI detection
     * @return $this
     */
    public function forceCliStreamWrite($useCliWhenTrue)
    {
        $this->forceCliStreamWrite = $useCliWhenTrue === null ? null : (bool) $useCliWhenTrue;

        return $this;
    }

    /** @var bool|null */
    private $forceShouldWriteStream = null;

    /**
     * @param bool|null $write null restores default behaviour
     * @return $this
     */
    public function forceShouldWriteStreamToTarget($write)
    {
        $this->forceShouldWriteStream = $write;

        return $this;
    }

    protected function shouldWriteStreamToTarget($sourcePath)
    {
        if ($this->forceShouldWriteStream !== null) {
            return $this->forceShouldWriteStream;
        }

        return parent::shouldWriteStreamToTarget($sourcePath);
    }

    protected function usesCliStreamWrite()
    {
        if ($this->forceCliStreamWrite !== null) {
            return $this->forceCliStreamWrite;
        }

        return parent::usesCliStreamWrite();
    }

    protected function diactorosUploadedFileDefinesDiskPathProperty(\ReflectionClass $uploadedFileReflection): bool
    {
        if ($this->forceNoDiskPath) {
            return false;
        }

        return parent::diactorosUploadedFileDefinesDiskPathProperty($uploadedFileReflection);
    }

    /** @var bool */
    public $forceRenameFailure = false;

    /** @var bool */
    public $forceCopyFailure = false;

    /**
     * @param bool $fail
     * @return $this
     */
    public function withForcedRenameFailure($fail = true)
    {
        $this->forceRenameFailure = (bool) $fail;

        return $this;
    }

    /**
     * @param bool $fail
     * @return $this
     */
    public function withForcedCopyFailure($fail = true)
    {
        $this->forceCopyFailure = (bool) $fail;

        return $this;
    }

    protected function renameUploadedPath($sourcePath, $targetPath)
    {
        if ($this->forceRenameFailure) {
            return false;
        }

        return parent::renameUploadedPath($sourcePath, $targetPath);
    }

    protected function copyUploadedPath($sourcePath, $targetPath)
    {
        if ($this->forceCopyFailure) {
            return false;
        }

        return parent::copyUploadedPath($sourcePath, $targetPath);
    }
}
