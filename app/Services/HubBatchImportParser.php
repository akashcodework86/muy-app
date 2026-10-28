<?php

namespace App\Services;

use App\Support\HubBatchImportNormalizer as Normalizer;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use RuntimeException;

class HubBatchImportParser
{
    private const ALIASES = [
        'application_no' => ['appno', 'applicationno', 'applicationnumber', 'appnumber', 'applicationid'],
        'mobile' => ['mobile', 'mobileno', 'mobilenumber', 'phone', 'phoneno', 'phonenumber', 'contact', 'contactno'],
        'name' => ['name', 'applicantname', 'fullname', 'incubateename'],
    ];

    /** @return array{sheet:string,header_row:int,headers:array<int,string>,mapping:array<string,int|null>,rows:list<array<string,mixed>>} */
    public function parse(UploadedFile $file, array $overrides = []): array
    {
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $sample = (string) file_get_contents($file->getRealPath(), false, null, 0, 4096);
            $delimiter = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
            $reader = new Csv;
            $reader->setDelimiter($delimiter);
            $reader->setEnclosure('"');
        } else {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
        }
        $reader->setReadDataOnly(true);
        $book = $reader->load($file->getRealPath());
        $best = null;

        foreach ($book->getWorksheetIterator() as $sheet) {
            $maxColumn = min(80, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
            for ($r = 1; $r <= min(5, $sheet->getHighestRow()); $r++) {
                $headers = [];
                for ($c = 1; $c <= $maxColumn; $c++) {
                    $headers[$c] = trim((string) $sheet->getCell([$c, $r])->getFormattedValue());
                }
                $mapping = $this->detectMapping($headers, $overrides);
                $score = collect($mapping)->filter()->count();
                if ($best === null || $score > $best['score']) {
                    $best = compact('sheet', 'r', 'headers', 'mapping', 'score');
                }
            }
        }

        if (! $best || ($best['mapping']['application_no'] === null && $best['mapping']['mobile'] === null)) {
            throw new RuntimeException('Could not find Application No or Mobile header in the first 5 rows. Use the mapping fields and try again.');
        }

        $rows = [];
        $seenApps = [];
        $sheet = $best['sheet'];
        for ($r = $best['r'] + 1; $r <= $sheet->getHighestRow(); $r++) {
            $get = fn (string $key) => $best['mapping'][$key] ? trim((string) $sheet->getCell([$best['mapping'][$key], $r])->getFormattedValue()) : '';
            $app = $get('application_no');
            $mobile = $get('mobile');
            $name = $get('name');
            if ($app === '' && $mobile === '' && $name === '') {
                continue;
            }
            $normalizedApp = Normalizer::applicationNo($app);
            $duplicate = $normalizedApp !== '' && isset($seenApps[$normalizedApp]);
            if ($normalizedApp !== '') {
                $seenApps[$normalizedApp] = true;
            }
            $rows[] = [
                'excel_row' => $r,
                'application_no' => $app,
                'mobile' => $mobile,
                'name' => $name,
                'duplicate_app_no_in_file' => $duplicate,
            ];
            if (count($rows) > 500) {
                throw new RuntimeException('Import is limited to 500 data rows per file.');
            }
        }

        return [
            'sheet' => $sheet->getTitle(),
            'header_row' => $best['r'],
            'headers' => $best['headers'],
            'mapping' => $best['mapping'],
            'rows' => $rows,
        ];
    }

    /** @return array<string,int|null> */
    private function detectMapping(array $headers, array $overrides): array
    {
        $mapping = [];
        foreach (self::ALIASES as $key => $aliases) {
            $mapping[$key] = null;
            $override = Normalizer::header($overrides[$key] ?? '');
            foreach ($headers as $column => $header) {
                $normalized = Normalizer::header($header);
                if (($override !== '' && $normalized === $override) || ($override === '' && in_array($normalized, $aliases, true))) {
                    $mapping[$key] = (int) $column;
                    break;
                }
            }
        }

        return $mapping;
    }
}
