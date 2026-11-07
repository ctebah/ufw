<?php
namespace Ufw\Form\Validator;

/**
 * Uploaded file checks (PHP upload errors, allowed extensions) for {@see \Ufw\Form\Element\File}.
 */
class FileUpload extends Base
{

    const UPLOAD_ERROR = 'uploadError';

    const UNSUPPORTED_EXT = 'ext';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::UPLOAD_ERROR => "Upload error. Please try again.",
        self::UNSUPPORTED_EXT => "File '%filename%' type is not supported, please upload one of %ext% files"
    );

    /**
     * @var \Ufw\Form\Element\File|null
     */
    protected $field = null;

    /**
     * @var string
     */
    protected $accepts = '';

    /**
     * @var string
     */
    protected $filename = '';

    /**
     * @var array<string, string>
     */
    protected $messageVariables = array(
        'ext' => 'accepts',
        'filename' => 'filename'
    );

    /**
     * @param \Ufw\Form\Element\File|null $field
     */
    public function __construct($field = null)
    {
        $this->field = $field;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if ($this->field !== null) {
            $value = $this->field->getUploadedFile();
        }

        if (!$value) {
            return true;
        }

        if (is_array($value)) {
            return true;
        }

        if (!$value instanceof \Laminas\Diactoros\UploadedFile) {
            return true;
        }

        $this->filename = (string) $value->getClientFilename();
        if ($value->getError() && $this->filename !== '') {
            $this->error(self::UPLOAD_ERROR);
            return false;
        }

        if ($this->field !== null && $this->filename !== '') {
            $acceptAttrib = $this->field->getAttrib('accept');
            if (is_string($acceptAttrib) && $acceptAttrib !== '') {
                $this->accepts = strtolower($acceptAttrib);
                $accepts = $this->splitAcceptExtensions($acceptAttrib);
                $ext = strtolower((string) strrchr($this->filename, '.'));
                if ($ext !== '' && !in_array($ext, $accepts)) {
                    $this->error(self::UNSUPPORTED_EXT);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param string $acceptAttrib
     * @return list<string>
     */
    protected function splitAcceptExtensions($acceptAttrib)
    {
        $accepts = $this->pregSplitAcceptList($acceptAttrib);
        if ($accepts === false) {
            return array();
        }

        return $accepts;
    }

    /**
     * @param string $acceptAttrib
     * @return list<string>|false
     */
    protected function pregSplitAcceptList($acceptAttrib)
    {
        return preg_split('~,\s*~', strtolower($acceptAttrib));
    }
}
