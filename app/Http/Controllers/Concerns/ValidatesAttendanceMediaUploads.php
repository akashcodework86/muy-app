<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

trait ValidatesAttendanceMediaUploads
{
    /**
     * @return array<string, list<string>>
     */
    protected function attendanceMediaValidationRules(): array
    {
        return [
            'attendance_media' => ['nullable', 'array'],
            'attendance_media.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,mp4,mov,avi,mkv,doc,docx,xls,xlsx', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attendanceMediaUploadErrors(Request $request): array
    {
        $errors = [];

        foreach ((array) $request->file('attendance_media', []) as $index => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $errors['attendance_media.'.$index] = $this->describeFailedUpload($file);
        }

        return $errors;
    }

    protected function describeFailedUpload(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload limit of '.ini_get('upload_max_filesize').'.',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form upload limit of '.ini_get('post_max_size').'.',
            UPLOAD_ERR_PARTIAL => 'The file only partially uploaded. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not store the uploaded file.',
            default => 'The file could not be uploaded.',
        };
    }
}
