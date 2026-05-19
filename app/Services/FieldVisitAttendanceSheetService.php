<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldVisitAttendanceSheetService
{
    public const DISK_FOLDER = 'field-visit-attendance-sheets';

    /** @var list<string> */
    public const HEADERS = [
        'Sr.No',
        'Name',
        'Gender (M/F)',
        'Mobile',
        'district',
        'block',
        'grampanchayat',
    ];

    /**
     * @return array{
     *     attendance_sheet_path: string,
     *     attendance_sheet_original_name: string,
     *     attendance_sheet_mime: string,
     *     attendance_sheet_size_bytes: int
     * }
     */
    public function storeUploadedFile(UploadedFile $file): array
    {
        $path = $file->store(self::DISK_FOLDER);

        return [
            'attendance_sheet_path' => $path,
            'attendance_sheet_original_name' => (string) $file->getClientOriginalName(),
            'attendance_sheet_mime' => (string) ($file->getClientMimeType() ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'attendance_sheet_size_bytes' => (int) ($file->getSize() ?? 0),
        ];
    }

    public function streamTemplateDownload(
        int $participantRows,
        string $districtName,
        string $blockName,
        string $gramPanchayatName,
    ): StreamedResponse {
        abort_if($participantRows <= 0, 422, 'Set participant counts before downloading the template.');

        $spreadsheet = $this->buildTemplateSpreadsheet(
            $participantRows,
            $districtName,
            $blockName,
            $gramPanchayatName,
        );

        $filename = 'muy-attendance-sheet-'.$participantRows.'-participants.xlsx';

        return response()->streamDownload(
            static function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    public function downloadStored(string $path, string $downloadName): StreamedResponse
    {
        abort_unless($path !== '' && Storage::exists($path), 404);

        return Storage::download($path, $downloadName !== '' ? $downloadName : basename($path));
    }

    /**
     * @throws ValidationException
     */
    public function assertValidUpload(
        UploadedFile $file,
        int $expectedTotal,
        int $expectedMale,
        int $expectedFemale,
        string $expectedDistrict,
        string $expectedBlock,
        string $expectedGramPanchayat,
    ): void {
        if ($expectedTotal <= 0) {
            return;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw ValidationException::withMessages([
                'attendance_sheet' => 'Upload the Excel file (.xlsx) downloaded from this system.',
            ]);
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'attendance_sheet' => 'Could not read the Excel file. Use the downloaded template.',
            ]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertHeaderRow($sheet);

        $errors = [];
        $maleCount = 0;
        $femaleCount = 0;
        $validRows = 0;
        $lastDataRow = $expectedTotal + 1;

        for ($row = 2; $row <= $lastDataRow; $row++) {
            $rowValues = $this->readRow($sheet, $row);
            $rowErrors = $this->validateDataRow(
                $row,
                $rowValues,
                $row - 1,
                $expectedDistrict,
                $expectedBlock,
                $expectedGramPanchayat,
            );

            if ($rowErrors !== []) {
                $errors = array_merge($errors, $rowErrors);

                continue;
            }

            $gender = $this->normalizeGender($rowValues['gender']);
            if ($gender === null) {
                $errors[] = 'Row '.$row.': Gender must be M or F.';

                continue;
            }

            $validRows++;
            if ($gender === 'M') {
                $maleCount++;
            } else {
                $femaleCount++;
            }
        }

        if ($validRows !== $expectedTotal) {
            $errors[] = 'The sheet must have exactly '.$expectedTotal.' complete participant row(s). Found '.$validRows.'.';
        }

        if ($maleCount !== $expectedMale) {
            $errors[] = 'Male count in sheet ('.$maleCount.') must match the form ('.$expectedMale.').';
        }

        if ($femaleCount !== $expectedFemale) {
            $errors[] = 'Female count in sheet ('.$femaleCount.') must match the form ('.$expectedFemale.').';
        }

        $extraRow = $this->firstExtraDataRow($sheet, $lastDataRow + 1);
        if ($extraRow !== null) {
            $errors[] = 'Remove extra data below row '.($expectedTotal + 1).' (unexpected data on row '.$extraRow.').';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'attendance_sheet' => array_values(array_unique($errors)),
            ]);
        }
    }

    private function buildTemplateSpreadsheet(
        int $participantRows,
        string $districtName,
        string $blockName,
        string $gramPanchayatName,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');

        foreach (self::HEADERS as $index => $header) {
            $col = chr(ord('A') + $index);
            $sheet->setCellValue($col.'1', $header);
            $sheet->getColumnDimension($col)->setWidth(match ($index) {
                0 => 8,
                1 => 28,
                2 => 14,
                3 => 14,
                default => 22,
            });
        }

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        for ($i = 1; $i <= $participantRows; $i++) {
            $row = $i + 1;
            $sheet->setCellValue('A'.$row, $i);
            $sheet->setCellValueExplicit('B'.$row, '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$row, '', DataType::TYPE_STRING);
            $sheet->setCellValue('E'.$row, $districtName);
            $sheet->setCellValue('F'.$row, $blockName);
            $sheet->setCellValue('G'.$row, $gramPanchayatName);
        }

        return $spreadsheet;
    }

    private function assertHeaderRow(Worksheet $sheet): void
    {
        foreach (self::HEADERS as $index => $expected) {
            $col = chr(ord('A') + $index);
            $actual = $this->normalizeHeader((string) $sheet->getCell($col.'1')->getValue());
            $expectedNorm = $this->normalizeHeader($expected);
            if ($actual !== $expectedNorm) {
                throw ValidationException::withMessages([
                    'attendance_sheet' => 'Use the attendance template from this page (column headers do not match).',
                ]);
            }
        }
    }

    /**
     * @return array{
     *     sr_no: string,
     *     name: string,
     *     gender: string,
     *     mobile: string,
     *     district: string,
     *     block: string,
     *     grampanchayat: string
     * }
     */
    private function readRow(Worksheet $sheet, int $row): array
    {
        return [
            'sr_no' => trim((string) $sheet->getCell('A'.$row)->getFormattedValue()),
            'name' => trim((string) $sheet->getCell('B'.$row)->getFormattedValue()),
            'gender' => trim((string) $sheet->getCell('C'.$row)->getFormattedValue()),
            'mobile' => trim((string) $sheet->getCell('D'.$row)->getFormattedValue()),
            'district' => trim((string) $sheet->getCell('E'.$row)->getFormattedValue()),
            'block' => trim((string) $sheet->getCell('F'.$row)->getFormattedValue()),
            'grampanchayat' => trim((string) $sheet->getCell('G'.$row)->getFormattedValue()),
        ];
    }

    /**
     * @param  array{
     *     sr_no: string,
     *     name: string,
     *     gender: string,
     *     mobile: string,
     *     district: string,
     *     block: string,
     *     grampanchayat: string
     * }  $values
     * @return list<string>
     */
    private function validateDataRow(
        int $excelRow,
        array $values,
        int $expectedSrNo,
        string $expectedDistrict,
        string $expectedBlock,
        string $expectedGramPanchayat,
    ): array {
        $errors = [];
        $prefix = 'Row '.$excelRow.': ';

        if ($values['name'] === '') {
            $errors[] = $prefix.'Name is required.';
        }

        $gender = $this->normalizeGender($values['gender']);
        if ($gender === null) {
            $errors[] = $prefix.'Gender must be M or F.';
        } else {
            $values['gender'] = $gender;
        }

        if ($values['mobile'] === '') {
            $errors[] = $prefix.'Mobile number is required.';
        } elseif (! $this->isValidMobile($values['mobile'])) {
            $errors[] = $prefix.'Mobile must be a valid 10-digit Indian number (starts with 6–9).';
        }

        if ($values['district'] === '') {
            $errors[] = $prefix.'District is required.';
        } elseif (! $this->textEquals($values['district'], $expectedDistrict)) {
            $errors[] = $prefix.'District must match "'.$expectedDistrict.'".';
        }

        if ($values['block'] === '') {
            $errors[] = $prefix.'Block is required.';
        } elseif (! $this->textEquals($values['block'], $expectedBlock)) {
            $errors[] = $prefix.'Block must match "'.$expectedBlock.'".';
        }

        if ($values['grampanchayat'] === '') {
            $errors[] = $prefix.'Gram panchayat is required.';
        } elseif (! $this->textEquals($values['grampanchayat'], $expectedGramPanchayat)) {
            $errors[] = $prefix.'Gram panchayat must match "'.$expectedGramPanchayat.'".';
        }

        if ($values['sr_no'] !== '' && (int) $values['sr_no'] !== $expectedSrNo) {
            $errors[] = $prefix.'Sr.No should be '.$expectedSrNo.'.';
        }

        return $errors;
    }

    private function firstExtraDataRow(Worksheet $sheet, int $startRow): ?int
    {
        $highest = (int) $sheet->getHighestRow();
        for ($row = $startRow; $row <= $highest; $row++) {
            $values = $this->readRow($sheet, $row);
            if ($this->rowHasAnyData($values)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function rowHasAnyData(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function normalizeGender(string $value): ?string
    {
        $v = Str::upper(trim($value));
        if ($v === 'M' || $v === 'MALE') {
            return 'M';
        }
        if ($v === 'F' || $v === 'FEMALE') {
            return 'F';
        }

        return null;
    }

    private function isValidMobile(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return (bool) preg_match('/^[6-9]\d{9}$/', $digits);
    }

    private function textEquals(string $a, string $b): bool
    {
        return Str::lower(trim($a)) === Str::lower(trim($b));
    }
}
