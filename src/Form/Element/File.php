<?php
namespace Ufw\Form\Element;

/** File upload input (`type=file`). */
class File extends Input
{
    protected $type = 'file';

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
     *
     * @var \Laminas\Diactoros\UploadedFile
     */
    protected $uploadedFile = null;

    protected $storedFilename = null;

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

    public function setForm(\Ufw\Form\Base $form = null)
    {
        parent::setForm($form);
        if ($form) {
            $form->setAttrib('enctype', 'multipart/form-data');
        }
        return $this;
    }

    public function receiveFile($pathPrefix = '')
    {
        if ($this->errors || $this->storedFilename || !$this->uploadedFile) {
            return $this;
        }
        
        if (!$this->value && ($this->uploadedFile->getError() == UPLOAD_ERR_NO_FILE)) {
            return $this;
        }
        
        if ($this->value && is_array($this->uploadedFile)) {
            return $this;
        }
        
        $baseStorageDir = $this->getBaseStorageDir();
        $ext = (new \Mimey\MimeTypes())->getExtension($this->uploadedFile->getClientMediaType());
        $ext = $ext ? ".{$ext}" : strrchr($this->uploadedFile->getClientFilename(), '.');
        $filename = hash('sha256', uniqid('', true)) . $ext;
        $this->storedFilename = ($pathPrefix ? $pathPrefix . '/' : '') . sprintf('%s/%s/%s', substr($filename, 0, 2), substr($filename, 2, 2), $filename);
        @mkdir($baseStorageDir . '/' . dirname($this->storedFilename), 0775, true);
        $this->uploadedFile->moveTo($baseStorageDir . '/' . $this->storedFilename);
        return $this;
    }

    public function getMeta()
    {
        if (!$this->storedFilename) {
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

    public function getBaseStorageDir()
    {
        return \Ufw\Registry::getInstance()->get('application')->getConfig()['storage'];
    }

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
     *
     * @return \Laminas\Diactoros\UploadedFile
     */
    public function getUploadedFile()
    {
        return $this->uploadedFile;
    }

    /**
     *
     * @param \Laminas\Diactoros\UploadedFile $uploadedFile
     */
    public function setUploadedFile($uploadedFile = null)
    {
        $this->uploadedFile = $uploadedFile;
        return $this;
    }

    public function getStoredFilename()
    {
        return $this->storedFilename;
    }

    public function setStoredFilename($storedFilename)
    {
        $this->storedFilename = $storedFilename;
        return $this;
    }
}
