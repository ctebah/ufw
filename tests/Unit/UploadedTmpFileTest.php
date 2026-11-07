<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\UploadedTmpFile;

class UploadedTmpFileTest extends TestCase
{
    public function testMoveToWritesStreamInCliEnvironment()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'payload');
        rewind($stream);

        $upload = new UploadedTmpFile($stream, 7, UPLOAD_ERR_OK, 'upload.txt', 'text/plain');
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-upload-' . bin2hex(random_bytes(4)) . '.txt';

        try {
            $upload->moveTo($target);
            $this->assertSame('payload', file_get_contents($target));
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testMoveToCannotRunTwice()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'x');
        rewind($stream);
        $upload = new UploadedTmpFile($stream, 1, UPLOAD_ERR_OK);
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-upload-twice-' . bin2hex(random_bytes(4)) . '.txt';
        try {
            $upload->moveTo($target);
            $this->expectException(\RuntimeException::class);
            $upload->moveTo($target);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testMoveToRejectsEmptyTargetPath()
    {
        $stream = fopen('php://memory', 'r+');
        $upload = new UploadedTmpFile($stream, 0, UPLOAD_ERR_OK);
        $this->expectException(\InvalidArgumentException::class);
        $upload->moveTo('');
    }

    public function testMoveToThrowsWhenUploadErrorSet(): void
    {
        $stream = fopen('php://memory', 'r+');
        $upload = new UploadedTmpFile($stream, 0, UPLOAD_ERR_PARTIAL, 'x.txt', 'text/plain');
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-upload-err-' . bin2hex(random_bytes(4)) . '.txt';
        $this->expectException(\RuntimeException::class);
        $upload->moveTo($target);
    }

    public function testDeleteOnMoveToggleIsFluent(): void
    {
        $stream = fopen('php://memory', 'r+');
        $upload = new UploadedTmpFile($stream, 0, UPLOAD_ERR_OK);
        $this->assertSame($upload, $upload->setDeleteOnMove(false));
        $this->assertFalse($upload->getDeleteOnMove());
    }

    public function testResolveSourceFilePathReturnsNullWhenDiskPropertyMissing(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        file_put_contents($source, 'disk');
        $stream = fopen($source, 'r');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceNoDiskPathProperty(true);
        $method = new \ReflectionMethod(\Ufw\UploadedTmpFile::class, 'resolveSourceFilePath');
        $method->setAccessible(true);
        $this->assertNull($method->invoke($upload));
        fclose($stream);
        @unlink($source);
    }

    public function testMoveToRenamesOnDiskSourceWhenNotUsingStreamWrite(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        file_put_contents($source, 'disk-payload');
        $stream = fopen($source, 'r');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 12, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false);
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-upload-disk-' . bin2hex(random_bytes(4)) . '.txt';
        try {
            $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
            if ($ref->hasProperty('file')) {
                $prop = $ref->getProperty('file');
                $prop->setAccessible(true);
                $prop->setValue($upload, $source);
            }
            $upload->moveTo($target);
            $this->assertFileExists($target);
            $this->assertSame('disk-payload', file_get_contents($target));
            $this->assertFileDoesNotExist($source);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
            if (is_file($source)) {
                unlink($source);
            }
        }
    }

    public function testMoveToRejectsUnwritableTargetDirectory(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Read-only directory fixture is unreliable on Windows.');
        }
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'x');
        rewind($stream);
        $upload = new UploadedTmpFile($stream, 1, UPLOAD_ERR_OK, 'f.txt', 'text/plain');
        $this->expectException(\RuntimeException::class);
        $upload->moveTo('/proc/ufw-not-writable/upload.txt');
    }

    public function testMoveToCopiesWhenDeleteOnMoveDisabled(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        file_put_contents($source, 'copied');
        $stream = fopen($source, 'r');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 8, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false)
            ->setDeleteOnMove(false);
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $prop->setValue($upload, $source);
        }
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-copied-' . bin2hex(random_bytes(4)) . '.txt';
        try {
            $upload->moveTo($target);
            $this->assertFileExists($target);
            $this->assertFileExists($source);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
            if (is_file($source)) {
                unlink($source);
            }
        }
    }

    public function testShouldWriteStreamWhenSourcePathMissing(): void
    {
        $stream = fopen('php://memory', 'r+');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 0, UPLOAD_ERR_OK))
            ->forceCliStreamWrite(false)
            ->forceNoDiskPathProperty(true);
        $method = new \ReflectionMethod(UploadedTmpFile::class, 'shouldWriteStreamToTarget');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($upload, null));
    }

    public function testMoveToThrowsWhenRenameFailsOnMissingSource(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        $path = $source;
        unlink($source);
        $stream = fopen('php://memory', 'r+');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false);
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $prop->setValue($upload, $path);
        }
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-fail-' . bin2hex(random_bytes(4)) . '.txt';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('uploaded source path is missing');
        $upload->moveTo($target);
    }

    public function testMoveToThrowsWhenRenameReturnsFalse(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        file_put_contents($source, 'payload');
        $stream = fopen($source, 'r');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false)
            ->withForcedRenameFailure();
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $prop->setValue($upload, $source);
        }
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-renfail-' . bin2hex(random_bytes(4)) . '.txt';
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Error occurred while moving uploaded file');
            $upload->moveTo($target);
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testMoveToThrowsWhenCopyFailsOnMissingSource(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        $path = $source;
        unlink($source);
        $stream = fopen('php://memory', 'r+');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false)
            ->setDeleteOnMove(false);
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $prop->setValue($upload, $path);
        }
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-copyfail-' . bin2hex(random_bytes(4)) . '.txt';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('uploaded source path is missing');
        $upload->moveTo($target);
    }

    public function testMoveToThrowsWhenCopyReturnsFalse(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-src-');
        file_put_contents($source, 'payload');
        $stream = fopen($source, 'r');
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false)
            ->setDeleteOnMove(false)
            ->withForcedCopyFailure();
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $prop->setValue($upload, $source);
        }
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-copyfail2-' . bin2hex(random_bytes(4)) . '.txt';
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Error occurred while moving uploaded file');
            $upload->moveTo($target);
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testMoveToThrowsWhenStreamWriteCannotOpenTarget(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'data');
        rewind($stream);
        $upload = new UploadedTmpFile($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain');
        $method = new \ReflectionMethod(UploadedTmpFile::class, 'writeFile');
        $method->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $method->invoke($upload, sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-no-such-dir-' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR . 'out.dat');
    }

    public function testMoveToThrowsWhenDiskMoveExpectedButSourcePathMissing(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'payload');
        rewind($stream);
        $upload = (new UploadedTmpFileDiskMoveProbe($stream, 4, UPLOAD_ERR_OK, 'f.txt', 'text/plain'))
            ->forceCliStreamWrite(false)
            ->forceNoDiskPathProperty(true)
            ->forceShouldWriteStreamToTarget(false);
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-missing-src-' . bin2hex(random_bytes(4)) . '.txt';
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('uploaded source path is missing');
            $upload->moveTo($target);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
