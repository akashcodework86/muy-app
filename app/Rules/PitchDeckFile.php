<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Accept PDF, PPT, and PPTX even when the server's fileinfo database
 * labels a legacy .ppt as application/x-ole-storage and a .pptx as application/zip.
 */
class PitchDeckFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        if (! $this->passes($value)) {
            $fail('Pitch deck must be PDF or PowerPoint (PPT/PPTX).');
        }
    }

    private function passes(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'ppt', 'pptx'], true)) {
            return false;
        }

        $guessed = strtolower((string) $file->guessExtension());
        if ($guessed === $extension) {
            return true;
        }

        $path = $file->getPathname();
        if ($path === '' || ! is_readable($path)) {
            return false;
        }

        return match ($extension) {
            'pdf' => $this->isPdf($path),
            'ppt' => $this->isLegacyPowerPoint($path),
            'pptx' => $this->isPptx($path),
            default => false,
        };
    }

    private function isPdf(string $path): bool
    {
        $head = file_get_contents($path, false, null, 0, 1024);

        return is_string($head) && str_contains($head, '%PDF-');
    }

    private function isLegacyPowerPoint(string $path): bool
    {
        $head = file_get_contents($path, false, null, 0, 8);
        if ($head !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return false;
        }

        return $this->contains($path, 'PowerPoint Document');
    }

    private function isPptx(string $path): bool
    {
        $head = file_get_contents($path, false, null, 0, 2);
        if ($head !== 'PK' || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }

        $found = $zip->locateName('ppt/presentation.xml', ZipArchive::FL_NOCASE) !== false;
        $zip->close();

        return $found;
    }

    private function contains(string $path, string $needle): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $utf16 = implode('', array_map(
            static fn (string $char): string => $char."\x00",
            str_split($needle),
        ));
        $needles = [$needle, $utf16];
        $carry = '';

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 65536);
                if (! is_string($chunk) || $chunk === '') {
                    break;
                }

                $haystack = $carry.$chunk;
                foreach ($needles as $candidate) {
                    if (str_contains($haystack, $candidate)) {
                        return true;
                    }
                }

                $carry = substr($haystack, -80);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
