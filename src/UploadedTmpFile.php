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

    protected $deleteOnMove = true;

    /**
     *
     * @var bool
     */
    protected $moved = false;

    /**
     *
     * @var null|string
     */
    protected $file;

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
            throw new RuntimeException(sprintf('Cannot retrieve stream due to upload error: %s', self::ERROR_MESSAGES[$this->getError()]));
        }

        if (!is_string($targetPath) || empty($targetPath)) {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException(sprintf('The target directory `%s` does not exists or is not writable', $targetDirectory));
        }

        $sapi = PHP_SAPI;
        switch (true) {
            case (empty($sapi) || 0 === strpos($sapi, 'cli') || !$this->file):
                // Non-SAPI environment, or no filename present
                $this->writeFile($targetPath);
                break;
            default:
                // SAPI environment, with file present
                if ($this->getDeleteOnMove()) {
                    if (false === rename($this->file, $targetPath)) {
                        throw new RuntimeException('Error occurred while moving uploaded file');
                    }
                } else {
                    if (false === copy($this->file, $targetPath)) {
                        throw new RuntimeException('Error occurred while moving uploaded file');
                    }
                }
                break;
        }

        $this->moved = true;
    }

    /**
     * Write internal stream to given path
     *
     * @param string $path
     */
    private function writeFile($path): void
    {
        $handle = fopen($path, 'wb+');
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

    public function getDeleteOnMove()
    {
        return $this->deleteOnMove;
    }

    public function setDeleteOnMove($deleteOnMove)
    {
        $this->deleteOnMove = $deleteOnMove;
        return $this;
    }
}
