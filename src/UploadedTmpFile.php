<?php

namespace Ufw;

use InvalidArgumentException;
use RuntimeException;

/**
 * PSR-7 uploaded file with UFW-specific {@see moveTo()}: supports CLI/non-SAPI paths, optional delete-on-move
 * vs copy, and stream fallback when no on-disk temp path exists.
 */
class UploadedTmpFile extends \Laminas\Diactoros\UploadedFile
{

    /**
     * @var bool
     */
    protected $deleteOnMove = true;

    /**
     *
     * @var bool
     */
    protected $moved = false;

    /**
     *
     * {@inheritdoc}
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     * @param string $targetPath
     *            Path to which to move the uploaded file.
     * @throws \RuntimeException if the upload was not successful.
     * @throws \InvalidArgumentException if the $path specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *         the second or subsequent call to the method.
     */
    public function moveTo($targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot move file; already moved!');
        }

        if ($this->getError() !== UPLOAD_ERR_OK) {
            $code = $this->getError();
            $messages = self::ERROR_MESSAGES;
            $label = isset($messages[$code]) ? $messages[$code] : 'Unknown upload error';
            throw new RuntimeException(sprintf('Cannot retrieve stream due to upload error: %s', $label));
        }

        if ($targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException(sprintf('The target directory `%s` does not exists or is not writable', $targetDirectory));
        }

        $sourcePath = $this->resolveSourceFilePath();
        if ($this->shouldWriteStreamToTarget($sourcePath)) {
            $this->writeFile($targetPath);
        } else {
            if ($sourcePath === null || $sourcePath === '' || !is_file($sourcePath)) {
                throw new RuntimeException('Cannot move file; uploaded source path is missing');
            }
            if ($this->getDeleteOnMove()) {
                if (false === $this->renameUploadedPath($sourcePath, $targetPath)) {
                    throw new RuntimeException('Error occurred while moving uploaded file');
                }
            } else {
                if (false === $this->copyUploadedPath($sourcePath, $targetPath)) {
                    throw new RuntimeException('Error occurred while moving uploaded file');
                }
            }
        }

        $this->moved = true;
    }

    /**
     * @param string $sourcePath
     * @param string $targetPath
     * @return bool
     */
    protected function renameUploadedPath($sourcePath, $targetPath)
    {
        return rename($sourcePath, $targetPath);
    }

    /**
     * @param string $sourcePath
     * @param string $targetPath
     * @return bool
     */
    protected function copyUploadedPath($sourcePath, $targetPath)
    {
        return copy($sourcePath, $targetPath);
    }

    /**
     * CLI and missing on-disk source use stream copy; SAPI + path uses rename/copy.
     *
     * @param string|null $sourcePath
     * @return bool
     */
    protected function shouldWriteStreamToTarget($sourcePath)
    {
        if ($this->usesCliStreamWrite()) {
            return true;
        }

        return $sourcePath === null || $sourcePath === '';
    }

    /**
     * Whether the current SAPI should always stream-copy uploads (PHPUnit/CLI).
     *
     * @return bool
     */
    protected function usesCliStreamWrite()
    {
        return strncmp(PHP_SAPI, 'cli', 3) === 0;
    }

    /**
     * On-disk temp path from Laminas parent (private `file` property).
     *
     * @return string|null
     */
    private function resolveSourceFilePath()
    {
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if (!$this->diactorosUploadedFileDefinesDiskPathProperty($ref)) {
            return null;
        }
        $prop = $ref->getProperty('file');
        $prop->setAccessible(true);
        $value = $prop->getValue($this);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Whether Laminas {@see \Laminas\Diactoros\UploadedFile} exposes the on-disk `file` property (override in tests for legacy layouts).
     *
     * @param \ReflectionClass<\Laminas\Diactoros\UploadedFile> $uploadedFileReflection
     */
    protected function diactorosUploadedFileDefinesDiskPathProperty(\ReflectionClass $uploadedFileReflection): bool
    {
        return $uploadedFileReflection->hasProperty('file');
    }

    /**
     * Write internal stream to given path
     *
     * @param string $path
     */
    private function writeFile($path): void
    {
        $handle = @fopen($path, 'wb+');
        if (false === $handle) {
            throw new RuntimeException('Unable to write to designated path');
        }

        $stream = $this->getStream();
        $stream->rewind();
        while (!$stream->eof()) {
            fwrite($handle, $stream->read(4096));
        }

        fclose($handle);
    }

    /**
     * @return bool
     */
    public function getDeleteOnMove()
    {
        return $this->deleteOnMove;
    }

    /**
     * @param bool $deleteOnMove
     * @return $this
     */
    public function setDeleteOnMove($deleteOnMove)
    {
        $this->deleteOnMove = $deleteOnMove;
        return $this;
    }
}
