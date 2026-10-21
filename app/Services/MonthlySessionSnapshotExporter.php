<?php

namespace App\Services;

use App\Models\GlobalVariable;
use App\Models\MonthlyMovementSession;
use App\Models\Purok;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Phar;
use PharData;
use RuntimeException;

class MonthlySessionSnapshotExporter
{
    public function __construct(
        private readonly MonthlyResumenReport $resumenReport,
        private readonly R401ReportBuilder $r401ReportBuilder,
        private readonly ResumenDocxExporter $resumenDocxExporter,
        private readonly R401DocxExporter $r401DocxExporter
    ) {}

    public function generate(MonthlyMovementSession $session, bool $reconcileToCurrentRecords = false): array
    {
        if ($session->period_month === null) {
            throw new RuntimeException('Cannot create a monthly archive without a session month.');
        }

        $month = Carbon::parse($session->period_month)->startOfMonth();
        $globalVariables = GlobalVariable::query()->first();
        $signatories = [
            'head_lsp' => trim((string) ($globalVariables?->head_lsp ?? '')),
            'ksp_pd' => trim((string) ($globalVariables?->ksp_pd ?? '')),
            'pastor_destinado' => trim((string) ($globalVariables?->pastor_destinado ?? '')),
        ];
        $report = $this->resumenReport->generate($month, true, $reconcileToCurrentRecords);
        $locale = trim((string) ($globalVariables?->locale ?? ''));
        $lcode = trim((string) ($globalVariables?->lcode ?? ''));
        $resumenData = [
            'report' => $report,
            'locale' => $locale,
            'lcode' => $lcode,
            'signatories' => $signatories,
        ];
        $puroks = Purok::query()->orderBy('purok_number')->get(['purok_number', 'purok_name']);
        $statusReports = [];
        $docxPaths = [];
        $mergedPath = storage_path('app/monthly-forms/MONTHLY-'.bin2hex(random_bytes(12)).'.docx');
        try {
            $docxPaths[] = $this->resumenDocxExporter->generate($report, $locale, $lcode, $signatories);
            foreach (['MS', 'UWP'] as $status) {
                $rows = $this->r401ReportBuilder->build('all', $status, $session);
                $kalihimName = $status === 'UWP'
                    ? ($globalVariables?->kalihim_uwp ?: $globalVariables?->kalihim_ms ?: '')
                    : ($globalVariables?->kalihim_ms ?: $globalVariables?->kalihim_uwp ?: '');
                $usePurokSecretary = (bool) ($globalVariables?->r401_use_purok_secretary ?? false);
                $reportPages = array_map(function (array $page) use ($usePurokSecretary, $kalihimName): array {
                    $page['kalihim_name'] = $usePurokSecretary
                        ? $page['secretary_name']
                        : $kalihimName;

                    return $page;
                }, $rows['pages']);
                $statusReports[$status] = [
                    'month' => $month,
                    'reportRows' => $rows['rows'],
                    'reportPages' => $reportPages,
                    'puroks' => $puroks,
                    'selectedPurok' => 'all',
                    'selectedStatus' => $status,
                    'globalVariables' => $globalVariables,
                    'locale' => trim((string) ($globalVariables?->locale ?? '')),
                    'lcode' => trim((string) ($globalVariables?->lcode ?? '')),
                    'dcode' => trim((string) ($globalVariables?->dcode ?? '')),
                    'district' => trim((string) ($globalVariables?->district ?? '')),
                    'kalihimName' => trim((string) $kalihimName),
                ];
                $docxPaths[] = $this->r401DocxExporter->generate([
                    'district' => trim((string) ($globalVariables?->district ?? '')),
                    'dcode' => trim((string) ($globalVariables?->dcode ?? '')),
                    'locale' => $locale,
                    'lcode' => $lcode,
                    'month' => mb_strtoupper($month->format('F')),
                    'year' => $month->format('Y'),
                    'kalagayan' => $status,
                    'signatures' => [
                        $kalihimName,
                        $globalVariables?->ksp_pd,
                        '',
                        $globalVariables?->pastor_destinado,
                    ],
                ], $reportPages);
            }
            $this->mergeDocuments($docxPaths, $mergedPath);
            $contents = file_get_contents($mergedPath);
            if ($contents === false) {
                throw new RuntimeException('Unable to read the combined monthly session DOCX.');
            }

            $preview = view('forms.monthly-session-snapshot-preview', [
                'month' => $month,
                'resumenData' => $resumenData,
                'statusReports' => $statusReports,
            ])->render();

            return ['contents' => $contents, 'preview' => $preview];
        } finally {
            foreach ([...$docxPaths, $mergedPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function mergeDocuments(array $documentPaths, string $outputPath): void
    {
        if ($documentPaths === []) {
            throw new RuntimeException('No monthly report documents were generated.');
        }

        $baseDocument = new PharData($documentPaths[0]);
        $baseXml = $baseDocument['word/document.xml']->getContent();
        $target = new DOMDocument('1.0', 'UTF-8');
        $target->preserveWhiteSpace = true;
        if (! $target->loadXML($baseXml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to read the generated Resumen DOCX.');
        }
        $targetXPath = new DOMXPath($target);
        $targetXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $targetBody = $targetXPath->query('/w:document/w:body')->item(0);
        if (! $targetBody instanceof DOMElement) {
            throw new RuntimeException('The generated Resumen DOCX has no document body.');
        }

        foreach (array_slice($documentPaths, 1) as $documentPath) {
            $sourceDocument = new PharData($documentPath);
            $source = new DOMDocument('1.0', 'UTF-8');
            if (! $source->loadXML($sourceDocument['word/document.xml']->getContent(), LIBXML_NONET)) {
                throw new RuntimeException('Unable to read a generated R4-01 DOCX.');
            }
            $sourceXPath = new DOMXPath($source);
            $sourceXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $sourceBody = $sourceXPath->query('/w:document/w:body')->item(0);
            if (! $sourceBody instanceof DOMElement) {
                throw new RuntimeException('A generated R4-01 DOCX has no document body.');
            }

            $sectionProperties = $targetXPath->query('./w:sectPr', $targetBody)->item(0);
            $pageBreak = $target->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:p'
            );
            $run = $target->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
            $break = $target->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br');
            $break->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:type', 'page');
            $run->appendChild($break);
            $pageBreak->appendChild($run);
            $targetBody->insertBefore($pageBreak, $sectionProperties);

            foreach ($sourceBody->childNodes as $node) {
                if ($node instanceof DOMElement && $node->localName === 'sectPr') {
                    continue;
                }
                $targetBody->insertBefore($target->importNode($node, true), $sectionProperties);
            }
        }

        $mergedXml = $target->saveXML();
        if ($mergedXml === false) {
            throw new RuntimeException('Unable to combine the monthly report documents.');
        }

        $output = new PharData($outputPath, 0, null, Phar::ZIP);
        $prefix = 'phar://'.str_replace('\\', '/', $documentPaths[0]).'/';
        foreach (new \RecursiveIteratorIterator($baseDocument) as $entry) {
            $entryPath = str_replace('\\', '/', $entry->getPathName());
            if (! str_starts_with($entryPath, $prefix)) {
                throw new RuntimeException('The generated Resumen DOCX contains an unreadable archive entry.');
            }
            $relativePath = substr($entryPath, strlen($prefix));
            $output->addFromString(
                $relativePath,
                $relativePath === 'word/document.xml' ? $mergedXml : $entry->getContent()
            );
        }
        unset($output, $baseDocument);
    }
}
