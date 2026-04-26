<?php
namespace Ufw\Form\Validator;

/**
 * Uploaded file checks (PHP upload errors, allowed extensions) for {@see \Ufw\Form\Element\File}.
 */
class FileUpload extends Base
{

    const UPLOAD_ERROR = 'uploadError';

    const UNSUPPORTED_EXT = 'ext';

    protected $messageTemplates = array(
        self::UPLOAD_ERROR => "Upload error. Please try again.",
        self::UNSUPPORTED_EXT => "File '%filename%' type is not supported, please upload one of %ext% files"
    );

    /**
     * 
     * @var \Ufw\Form\Element\File
     */
    protected $field = null;

    protected $accepts = '';

    protected $filename = '';

    protected $messageVariables = array(
        'ext' => 'accepts',
        'filename' => 'filename'
    );

    public function __construct($field = null)
    {
        $this->field = $field;
    }

    public function isValid($value, $context = null)
    {
        if ($this->field && ($this->field instanceof \Ufw\Form\Element\File)) {
            $value = $this->field->getUploadedFile();
        }
        
        if (!$value) {
            return true;
        }
        
        if (is_array($value)) {
            ;
        } elseif ($value instanceof \Laminas\Diactoros\UploadedFile) {
            /**
             *
             * @var \Laminas\Diactoros\UploadedFile $value
             */
            $this->filename = $value->getClientFilename();
            if ($value->getError() && $this->filename) {
                $this->error(self::UPLOAD_ERROR);
                return false;
            }
            
            if ($this->field && $this->field->getAttrib('accept') && $this->filename) {
                $accepts = preg_split('~,\s*~', $this->accepts = strtolower($this->field->getAttrib('accept')));
                $ext = strtolower(strrchr($this->filename, '.'));
                if (!in_array($ext, $accepts)) {
                    $this->error(self::UNSUPPORTED_EXT);
                    return false;
                }
            }
        }
        return true;
    }
}
