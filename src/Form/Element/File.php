<?php
namespace Ufw\Form\Element;

/** File upload input (`type=file`). */
class File extends Input
{
    /**
     * @var string
     */
    protected $type = 'file';

    /**
     * @var list<string>
     */
    protected $defaultOptionsToAttribs = [
        'name',
        'id',
        'placeholder',
        'class',
        'type',
        'required',
        'maxlength',
        'accept',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled'
    ];

    /**
     * @var \Laminas\Diactoros\UploadedFile|array<string, mixed>|null
     */
    protected $uploadedFile = null;

    /**
     * @var string|null
     */
    protected $storedFilename = null;

    /**
     * @return $this
     */
    public function init()
    {
        parent::init();
        $this->addValidator('fileUpload', [
            'options' => [
                'field' => $this
            ]
        ]);
        return $this;
    }

    /**
     * @param \Ufw\Form\Base|null $form
     * @return $this
     */
    public function setForm(\Ufw\Form\Base $form = null)
    {
        parent::setForm($form);
        if ($form) {
            $form->setAttrib('enctype', 'multipart/form-data');
        }
        return $this;
    }

    /**
     * @param string $pathPrefix
     * @return $this
     */
    public function receiveFile($pathPrefix = '')
    {
        if ($this->errors || $this->storedFilename || $this->uploadedFile === null) {
            return $this;
        }

        if (is_array($this->uploadedFile)) {
            return $this;
        }

        if (!$this->value && ($this->uploadedFile->getError() == UPLOAD_ERR_NO_FILE)) {
            return $this;
        }

        $baseStorageDir = $this->getBaseStorageDir();
        $mediaType = $this->uploadedFile->getClientMediaType();
        $ext = (new \Mimey\MimeTypes())->getExtension(is_string($mediaType) ? $mediaType : '');
        $clientFilename = $this->uploadedFile->getClientFilename();
        $ext = $ext ? ".{$ext}" : (is_string($clientFilename) ? (string) strrchr($clientFilename, '.') : '');
        $filename = hash('sha256', uniqid('', true)) . $ext;
        $this->storedFilename = ($pathPrefix ? $pathPrefix . '/' : '') . sprintf('%s/%s/%s', substr($filename, 0, 2), substr($filename, 2, 2), $filename);
        @mkdir($baseStorageDir . '/' . dirname($this->storedFilename), 0775, true);
        $this->uploadedFile->moveTo($baseStorageDir . '/' . $this->storedFilename);
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta()
    {
        if (!$this->storedFilename) {
            return [];
        }
        if (is_array($this->uploadedFile)) {
            return $this->uploadedFile;
        }
        if (!($this->uploadedFile instanceof \Laminas\Diactoros\UploadedFile)) {
            return [];
        }
        $baseStorageDir = $this->getBaseStorageDir();
        return [
            'storedFilename' => $this->storedFilename,
            'clientFilename' => $this->uploadedFile->getClientFilename(),
            'clientMediaType' => $this->uploadedFile->getClientMediaType(),
            'size' => filesize($baseStorageDir . '/' . $this->storedFilename),
            'ext' => strrchr($this->storedFilename, '.')
        ];
    }

    /**
     * @return string
     */
    public function getBaseStorageDir()
    {
        $application = \Ufw\Registry::getInstance()->get('application');
        if (!$application instanceof \Ufw\Application) {
            throw new \RuntimeException('Application is not registered.');
        }
        $config = $application->getConfig();
        if (is_array($config) && isset($config['storage']) && is_string($config['storage'])) {
            return $config['storage'];
        }
        return '';
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null)
    {
        if (is_string($value)) {
            parent::setValue($value, $context);
        } elseif ($value instanceof \Laminas\Diactoros\UploadedFile) {
            parent::setValue($value->getClientFilename(), $context);
            $this->setUploadedFile($value)->setStoredFilename(null);
        }
        return $this;
    }

    /**
     * @return \Laminas\Diactoros\UploadedFile|array<string, mixed>|null
     */
    public function getUploadedFile()
    {
        return $this->uploadedFile;
    }

    /**
     * @param \Laminas\Diactoros\UploadedFile|array<string, mixed>|null $uploadedFile
     * @return $this
     */
    public function setUploadedFile($uploadedFile = null)
    {
        $this->uploadedFile = $uploadedFile;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getStoredFilename()
    {
        return $this->storedFilename;
    }

    /**
     * @param string|null $storedFilename
     * @return $this
     */
    public function setStoredFilename($storedFilename)
    {
        $this->storedFilename = $storedFilename;
        return $this;
    }
}
