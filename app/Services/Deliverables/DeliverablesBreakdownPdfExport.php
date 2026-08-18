<?php

namespace App\Services\Deliverables;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DeliverablesBreakdownPdfExport
{
    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, mixed>|null  $row
     */
    public function download(array $breakdown, ?array $row, string $serial, string $scopeLabel, string $periodLabel): Response
    {
        if (! class_exists(Dompdf::class)) {
            abort(503, 'PDF export is not available on this server.');
        }

        $html = View::make('deliverables.breakdown-pdf', [
            'breakdown' => $breakdown,
            'serial' => $serial,
            'scopeLabel' => $scopeLabel,
            'periodLabel' => $periodLabel,
            'target' => is_array($row) ? ($row['target'] ?? null) : null,
            'achievementPct' => is_array($row) ? ($row['achievement_pct'] ?? null) : null,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            'A4',
            ($breakdown['source_type'] ?? '') === 'market_linkage_incubatees' ? 'landscape' : 'portrait',
        );
        $dompdf->render();

        $slug = str_replace('.', '-', $serial);
        $fileName = 'deliverables-breakdown-'.$slug.'-'.now()->format('Ymd').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }
}
