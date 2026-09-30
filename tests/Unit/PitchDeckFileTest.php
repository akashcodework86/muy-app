<?php

namespace Tests\Unit;

use App\Rules\PitchDeckFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use ZipArchive;

class PitchDeckFileTest extends TestCase
{
    public function test_accepts_pdf_reported_as_pdf(): void
    {
        $file = UploadedFile::fake()->create('deck.pdf', 20, 'application/pdf');

        $this->assertDeckPasses($file);
    }

    public function test_accepts_legacy_ppt_when_fileinfo_reports_ole_storage(): void
    {
        $file = UploadedFile::fake()
            ->createWithContent('investor-deck.ppt', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1PowerPoint Document")
            ->mimeType('application/x-ole-storage');

        $this->assertDeckPasses($file);
    }

    public function test_accepts_pptx_when_fileinfo_reports_zip(): void
    {
        $file = UploadedFile::fake()
            ->createWithContent('investor-deck.pptx', $this->minimalPptx())
            ->mimeType('application/zip');

        $this->assertDeckPasses($file);
    }

    public function test_accepts_real_review_template_reported_as_zip(): void
    {
        $template = base_path('resources/templates/review-ppt/muy-review-2026.pptx');
        $this->assertFileExists($template);

        $file = UploadedFile::fake()
            ->createWithContent('muy-review.pptx', (string) file_get_contents($template))
            ->mimeType('application/zip');

        $this->assertDeckPasses($file);
    }

    public function test_rejects_zip_renamed_to_pptx(): void
    {
        $file = UploadedFile::fake()
            ->createWithContent('notes.pptx', $this->zipWith('readme.txt', 'hello'))
            ->mimeType('application/zip');

        $this->assertDeckFails($file);
    }

    public function test_rejects_word_document_renamed_to_ppt(): void
    {
        $file = UploadedFile::fake()
            ->createWithContent('notes.ppt', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1WordDocument")
            ->mimeType('application/x-ole-storage');

        $this->assertDeckFails($file);
    }

    public function test_rejects_text_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'hello')->mimeType('text/plain');

        $this->assertDeckFails($file);
    }

    private function assertDeckPasses(UploadedFile $file): void
    {
        $validator = Validator::make(
            ['deck_file' => $file],
            ['deck_file' => ['required', 'file', new PitchDeckFile]],
        );

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    private function assertDeckFails(UploadedFile $file): void
    {
        $validator = Validator::make(
            ['deck_file' => $file],
            ['deck_file' => ['required', 'file', new PitchDeckFile]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Pitch deck must be PDF or PowerPoint (PPT/PPTX).',
            $validator->errors()->first('deck_file'),
        );
    }

    private function minimalPptx(): string
    {
        return $this->zipWith('ppt/presentation.xml', '<p:presentation/>');
    }

    private function zipWith(string $name, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'deck');
        $this->assertNotFalse($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE) === true);
        $zip->addFromString($name, $contents);
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($bytes);

        return $bytes;
    }
}
