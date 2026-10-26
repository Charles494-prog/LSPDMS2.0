<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Phar;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class ResumenDocxExporter
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const CODES = ['A', 'B', 'C+', 'C-', 'D', 'DD', 'E', 'G', 'H'];

    public function generate(array $report, string $locale, string $lcode, array $signatories = []): string
    {
        $templatePath = resource_path('templates/RESUMEN-EDITABLE.docx');
        if (! is_file($templatePath)) {
            throw new RuntimeException('The editable Resumen Word template is missing.');
        }

        $outputPath = ExportTempFiles::path('RESUMEN');

        try {
            $template = new PharData($templatePath);
            $xml = $template['word/document.xml']->getContent();
            $updatedXml = $this->populateDocument($xml, $report, $locale, $lcode, $signatories);
            $output = new PharData($outputPath, 0, null, Phar::ZIP);
            // Buffer the entries and deflate them in one write so the DOCX is not stored uncompressed.
            $output->startBuffering();
            $prefix = 'phar://'.str_replace('\\', '/', $templatePath).'/';
            foreach (new RecursiveIteratorIterator($template) as $entry) {
                $entryPath = str_replace('\\', '/', $entry->getPathName());
                if (! str_starts_with($entryPath, $prefix)) {
                    throw new RuntimeException('The editable Resumen template contains an unreadable archive entry.');
                }
                $relativePath = substr($entryPath, strlen($prefix));
                $output->addFromString(
                    $relativePath,
                    $relativePath === 'word/document.xml' ? $updatedXml : $entry->getContent()
                );
            }
            $output->compressFiles(Phar::GZ);
            $output->stopBuffering();
            unset($output, $template);
        } catch (Throwable $exception) {
            unset($output, $template);
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
            throw $exception;
        }

        return $outputPath;
    }

    private function populateDocument(
        string $xml,
        array $report,
        string $locale,
        string $lcode,
        array $signatories
    ): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to read the editable Resumen Word template.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('The editable Resumen Word template has no document body.');
        }
        $initialTables = $xpath->query('//w:tbl');
        if (! $initialTables || $initialTables->length !== 3) {
            throw new RuntimeException('The editable Resumen Word template has an unexpected table layout.');
        }

        $pageNodes = [];
        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'sectPr') {
                break;
            }
            $pageNodes[] = $child;
        }
        $rows = $report['rows']->all();
        $pageRows = $rows === [] ? [[]] : array_chunk($rows, 16);
        $pageTables = [array_slice(iterator_to_array($initialTables), 0, 3)];
        for ($pageIndex = 1; $pageIndex < count($pageRows); $pageIndex++) {
            $clonedTables = [];
            foreach ($pageNodes as $node) {
                $clone = $node->cloneNode(true);
                $body->insertBefore($clone, $xpath->query('./w:sectPr', $body)->item(0));
                if ($clone instanceof DOMElement && $clone->localName === 'tbl') {
                    $clonedTables[] = $clone;
                }
            }
            $pageTables[] = $clonedTables;
        }

        foreach ($pageTables as $pageIndex => $tables) {
            if (count($tables) !== 3) {
                throw new RuntimeException('The editable Resumen template page could not be duplicated.');
            }
            if ($pageIndex > 0) {
                $this->addPageBreakBeforeTable($document, $xpath, $tables[0]);
            }
            $this->populatePage(
                $document,
                $xpath,
                $tables[1],
                $tables[2],
                $report,
                $locale,
                $lcode,
                $signatories,
                $pageRows[$pageIndex],
                $pageIndex === count($pageTables) - 1
            );
        }

        $updatedXml = $document->saveXML();
        if ($updatedXml === false) {
            throw new RuntimeException('Unable to generate the populated Resumen Word document.');
        }

        return $updatedXml;
    }

    private function populatePage(
        DOMDocument $document,
        DOMXPath $xpath,
        DOMElement $reportTable,
        DOMElement $signatureTable,
        array $report,
        string $locale,
        string $lcode,
        array $signatories,
        array $rows,
        bool $includeTotals
    ): void {
        $monthText = mb_strtoupper($report['month']->format('F Y'));
        $tableRows = $xpath->query('./w:tr', $reportTable);
        if (! $tableRows || $tableRows->length < 21) {
            throw new RuntimeException('The editable Resumen Word template has no complete report rows.');
        }
        $headerRow = $tableRows->item(0);
        $this->setCell($document, $xpath, $headerRow, 0, 'LOKAL: '.$locale);
        $this->setCell($document, $xpath, $headerRow, 1, 'LCODE: '.$lcode);
        $this->setCell($document, $xpath, $headerRow, 2, 'PARA SA BUWAN NG: '.$monthText);
        $this->setCell($document, $xpath, $headerRow, 4, 'PARA SA BUWAN NG: '.$monthText);
        for ($index = 0; $index < 16; $index++) {
            $tableRow = $tableRows->item($index + 4);
            $row = $rows[$index] ?? null;
            $this->setCell($document, $xpath, $tableRow, 0, (string) ($row['maysakop'] ?? ''));
            $this->setCell($document, $xpath, $tableRow, 1, (string) ($row['purok_label'] ?? ''));
            foreach (self::CODES as $codeIndex => $code) {
                $this->setCell(
                    $document,
                    $xpath,
                    $tableRow,
                    $codeIndex + 2,
                    $row === null ? '' : (string) $row['ms'][$code]
                );
                $this->setCell(
                    $document,
                    $xpath,
                    $tableRow,
                    $codeIndex + 12,
                    $row === null ? '' : (string) $row['uwp'][$code]
                );
            }
            $this->setCell(
                $document,
                $xpath,
                $tableRow,
                11,
                $row === null ? '' : (string) $row['ms_total']
            );
            $this->setCell(
                $document,
                $xpath,
                $tableRow,
                21,
                $row === null ? '' : (string) $row['uwp_total']
            );
        }

        $totalRow = $tableRows->item(20);
        $this->setCell($document, $xpath, $totalRow, 0, $includeTotals ? 'KABUUAN' : '');
        $this->setCell($document, $xpath, $totalRow, 1, '');
        foreach (self::CODES as $codeIndex => $code) {
            $this->setCell(
                $document,
                $xpath,
                $totalRow,
                $codeIndex + 2,
                $includeTotals ? (string) $report['totals']['ms'][$code] : ''
            );
            $this->setCell(
                $document,
                $xpath,
                $totalRow,
                $codeIndex + 12,
                $includeTotals ? (string) $report['totals']['uwp'][$code] : ''
            );
        }
        $this->setCell(
            $document,
            $xpath,
            $totalRow,
            11,
            $includeTotals ? (string) $report['totals']['ms_total'] : ''
        );
        $this->setCell(
            $document,
            $xpath,
            $totalRow,
            21,
            $includeTotals ? (string) $report['totals']['uwp_total'] : ''
        );

        $signatureRow = $xpath->query('./w:tr', $signatureTable)?->item(0);
        $signatureLabels = [
            'KALIHIM SA LSP',
            'KATIWALA SA PAGPAPATIBAY (KSP)',
            'PASTOR NG LOKAL',
        ];
        $signatureNames = [
            $signatories['head_lsp'] ?? '',
            $signatories['ksp_pd'] ?? '',
            $signatories['pastor_destinado'] ?? '',
        ];
        foreach ($signatureLabels as $index => $label) {
            $name = trim((string) $signatureNames[$index]);
            $this->setCell(
                $document,
                $xpath,
                $signatureRow,
                $index,
                $name === '' ? $label : mb_strtoupper($name)."\n".$label
            );
        }
    }

    private function addPageBreakBeforeTable(DOMDocument $document, DOMXPath $xpath, DOMElement $table): void
    {
        $paragraph = $xpath->query('.//w:p', $table)?->item(0);
        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('The editable Resumen template has no title paragraph.');
        }
        $properties = $xpath->query('./w:pPr', $paragraph)?->item(0);
        if (! $properties instanceof DOMElement) {
            $properties = $document->createElementNS(self::WORD_NAMESPACE, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $properties->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:pageBreakBefore'));
    }

    private function setCell(
        DOMDocument $document,
        DOMXPath $xpath,
        ?DOMNode $row,
        int $index,
        string $value
    ): void {
        if (! $row) {
            throw new RuntimeException('The editable Resumen Word template is missing a required row.');
        }
        $cells = $xpath->query('./w:tc', $row);
        $cell = $cells?->item($index);
        if (! $cell instanceof DOMElement) {
            throw new RuntimeException('The editable Resumen Word template is missing a required cell.');
        }
        $paragraph = $xpath->query('./w:p', $cell)?->item(0);
        if (! $paragraph instanceof DOMElement) {
            $paragraph = $document->createElementNS(self::WORD_NAMESPACE, 'w:p');
            $cell->appendChild($paragraph);
        }
        $runProperties = $xpath->query('.//w:r/w:rPr', $paragraph)?->item(0)?->cloneNode(true);
        foreach (iterator_to_array($cell->childNodes) as $child) {
            if ($child === $paragraph || ($child instanceof DOMElement && $child->localName === 'tcPr')) {
                continue;
            }
            $cell->removeChild($child);
        }
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if (! ($child instanceof DOMElement && $child->localName === 'pPr')) {
                $paragraph->removeChild($child);
            }
        }

        foreach (explode("\n", $value) as $index => $line) {
            if ($index > 0) {
                $breakRun = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
                $breakRun->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:br'));
                $paragraph->appendChild($breakRun);
            }
            $run = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
            if ($runProperties instanceof DOMElement) {
                $run->appendChild($runProperties->cloneNode(true));
            }
            $text = $document->createElementNS(self::WORD_NAMESPACE, 'w:t');
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $text->appendChild($document->createTextNode($line));
            $run->appendChild($text);
            $paragraph->appendChild($run);
        }
    }
}
