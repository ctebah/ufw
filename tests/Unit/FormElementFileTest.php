<?php
namespace Ufw\Tests\Unit;

use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\TestCase;
use Ufw\Form\Base as FormBase;
use Ufw\Form\Element\File;
use Ufw\Registry;
use Ufw\Tests\Support\ApplicationFixtures;

class FormElementFileTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testSetFormSetsMultipartEncodingWhenFormPresent(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $file = new File('doc');
        $form = new class(array('action' => '/up')) extends FormBase {
            public function init()
            {
            }
        };
        $file->setForm($form);
        $this->assertSame('multipart/form-data', $form->getAttrib('enctype'));
        $file->setForm(null);
        $this->assertSame('multipart/form-data', $form->getAttrib('enctype'));
    }

    public function testReceiveFileSkipsWhenAlreadyStoredOrMissingUpload(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $file = new File('doc');
        $file->setStoredFilename('already/stored.pdf');
        $file->receiveFile();
        $this->assertSame('already/stored.pdf', $file->getStoredFilename());

        $empty = new File('empty');
        $empty->receiveFile();
        $this->assertNull($empty->getStoredFilename());
    }

    public function testReceiveFileSkipsNoFileUploadWithoutValue(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $stream = fopen('php://temp', 'w+b');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_NO_FILE, '', 'application/octet-stream');
        $file = new File('optional');
        $file->setUploadedFile($upload);
        $file->receiveFile();
        $this->assertNull($file->getStoredFilename());
    }

    public function testReceiveFileSkipsWhenUploadedFileIsLegacyMetaArray(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $meta = array(
            'storedFilename' => 'internal/ab/cd/hash.pdf',
            'clientFilename' => 'existing.pdf',
            'size' => 123,
        );
        $file = new File('doc');
        $file->setUploadedFile($meta)->setStoredFilename($meta['storedFilename']);
        $file->setValue($meta['clientFilename']);
        $file->receiveFile('internal');
        $this->assertSame($meta['storedFilename'], $file->getStoredFilename());
        $this->assertSame($meta, $file->getMeta());
    }

    public function testReceiveFileStoresUploadWithPathPrefixAndMeta(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-file-' . uniqid('', true);
        mkdir($root);
        ApplicationFixtures::bareWithController(array('storage' => $root));

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'payload');
        rewind($stream);
        $upload = new UploadedFile($stream, 7, UPLOAD_ERR_OK, 'report.pdf', 'application/pdf');
        $file = new File('doc');
        $file->setValue($upload);
        $file->receiveFile('2026/11');
        $stored = $file->getStoredFilename();
        $this->assertNotNull($stored);
        $this->assertStringStartsWith('2026/11/', $stored);
        $meta = $file->getMeta();
        $this->assertSame('report.pdf', $meta['clientFilename']);
        $this->assertSame(7, $meta['size']);
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stored));

        $this->removeTree($root);
    }

    public function testSetValueAcceptsStringOrUploadedFile(): void
    {
        ApplicationFixtures::bareWithController();
        $file = new File('doc');
        $file->setValue('legacy-name.pdf');
        $this->assertSame('legacy-name.pdf', $file->getValue());

        $stream = fopen('php://temp', 'r');
        $upload = new UploadedFile($stream, 1, UPLOAD_ERR_OK, 'u.txt', 'text/plain');
        $file->setValue($upload);
        $this->assertSame('u.txt', $file->getValue());
        $this->assertSame($upload, $file->getUploadedFile());
    }

    public function testGetMetaReturnsEmptyWithoutStoredFile(): void
    {
        ApplicationFixtures::bareWithController();
        $file = new File('doc');
        $this->assertSame(array(), $file->getMeta());
    }

    public function testGetBaseStorageDirUsesConfigOrThrowsWhenAppMissing(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => '/var/ufw-storage'));
        $file = new File('doc');
        $this->assertSame('/var/ufw-storage', $file->getBaseStorageDir());

        ApplicationFixtures::tearDownRegistry();
        $this->expectException(\RuntimeException::class);
        (new File('x'))->getBaseStorageDir();
    }

    public function testGetBaseStorageDirReturnsEmptyWhenStorageNotConfigured(): void
    {
        ApplicationFixtures::bareWithController(array());
        $file = new File('doc');
        $this->assertSame('', $file->getBaseStorageDir());
    }

    public function testFileUploadValidatorAcceptsMatchingExtension(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $field = new File('doc');
        $field->setAttrib('accept', '.pdf, .png');
        $upload = new UploadedFile('php://temp', 10, UPLOAD_ERR_OK, 'paper.PDF', 'application/pdf');
        $field->setUploadedFile($upload);
        $validator = new \Ufw\Form\Validator\FileUpload($field);
        $this->assertTrue($validator->isValid($upload));
    }

    public function testFileUploadValidatorAcceptsWhenAcceptAttributeEmpty(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $field = new File('doc');
        $upload = new UploadedFile('php://temp', 10, UPLOAD_ERR_OK, 'notes.txt', 'text/plain');
        $field->setUploadedFile($upload);
        $validator = new \Ufw\Form\Validator\FileUpload($field);
        $this->assertTrue($validator->isValid($upload));
    }

    public function testFileUploadValidatorAcceptsUploadedFileWithoutField(): void
    {
        ApplicationFixtures::bareWithController();
        $upload = new UploadedFile('php://temp', 10, UPLOAD_ERR_OK, 'doc.pdf', 'application/pdf');
        $validator = new \Ufw\Form\Validator\FileUpload();
        $this->assertTrue($validator->isValid($upload));
    }

    public function testFileUploadValidatorTreatsFailedAcceptSplitAsEmptyList(): void
    {
        ApplicationFixtures::bareWithController(array('storage' => sys_get_temp_dir()));
        $field = new File('doc');
        $field->setAttrib('accept', '.pdf');
        $upload = new UploadedFile('php://temp', 10, UPLOAD_ERR_OK, 'paper.pdf', 'application/pdf');
        $field->setUploadedFile($upload);
        $validator = new FileUploadPregSplitProbe($field);
        $validator->failNextAcceptSplit = true;
        $this->assertFalse($validator->isValid($upload));
    }

    private function removeTree($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

class FileUploadPregSplitProbe extends \Ufw\Form\Validator\FileUpload
{
    /** @var bool */
    public $failNextAcceptSplit = false;

    protected function pregSplitAcceptList($acceptAttrib)
    {
        if ($this->failNextAcceptSplit) {
            $this->failNextAcceptSplit = false;

            return false;
        }

        return parent::pregSplitAcceptList($acceptAttrib);
    }
}
