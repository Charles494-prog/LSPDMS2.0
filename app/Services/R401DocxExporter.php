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

class R401DocxExporter
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function generate(array $header, array $rows): string
    {
        $templatePath = resource_path('templates/R4-01-EDITABLE.docx');
        if (! is_file($templatePath)) {
            throw new RuntimeException('The editable R4-01 Word template is missing.');
        }

        $outputPath = ExportTempFiles::path('R4-01');
        try {
            $template = new PharData($templatePath);
            $documentXml = $template['word/document.xml']->getContent();
            $updatedXml = $this->populateDocument($documentXml, $header, $rows);
            $output = new PharData($outputPath, 0, null, Phar::ZIP);
            $prefix = 'phar://'.str_replace('\\', '/', $templatePath).'/';

            foreach (new RecursiveIteratorIterator($template) as $entry) {
                $entryPath = str_replace('\\', '/', $entry->getPathName());
                if (! str_starts_with($entryPath, $prefix)) {
                    throw new RuntimeException('The R4-01 Word template contains an unreadable archive entry.');
                }
                $relativePath = substr($entryPath, strlen($prefix));
                $content = $relativePath === 'word/document.xml' ? $updatedXml : $entry->getContent();
                $output->addFromString($relativePath, $content);
            }
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

    private function populateDocument(string $xml, array $header, array $rows): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to read the editable R4-01 Word template.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);
        $tables = $xpath->query('//w:tbl');
        if (! $tables || $tables->length < 4) {
            throw new RuntimeException('The editable R4-01 Word template has an unexpected table layout.');
        }

        $body = $xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('The editable R4-01 Word template has no document body.');
        }
        $pageNodes = [];
        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'sectPr') {
                break;
            }
            $pageNodes[] = $child;
        }
        $templateTables = array_slice(iterator_to_array($tables), 0, 4);
        $pages = $rows === [] ? [['rows' => []]] : $rows;
        $pageTables = [$templateTables];
        for ($pageIndex = 1; $pageIndex < count($pages); $pageIndex++) {
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

        foreach ($pageTables as $pageIndex => $tablesForPage) {
            if (count($tablesForPage) !== 4) {
                throw new RuntimeException('The editable R4-01 Word template page could not be duplicated.');
            }
            if ($pageIndex > 0) {
                $this->addPageBreakBeforeTitle($document, $xpath, $tablesForPage[0]);
            }
            $pageData = $pages[$pageIndex];
            $pageHeader = array_replace($header, [
                'name' => $pageData['maysakop_name'] ?? '',
                'assigned_number' => $pageData['assigned_number'] ?? '',
                'page_number' => $pageData['page_number'] ?? 1,
                'signatures' => array_replace($header['signatures'] ?? [], [
                    0 => $pageData['kalihim_name'] ?? ($header['signatures'][0] ?? ''),
                    2 => $pageData['maysakop_name'] ?? '',
                ]),
            ]);
            $this->populatePage(
                $document,
                $xpath,
                $tablesForPage,
                $pageHeader,
                array_slice($pageData['rows'] ?? [], 0, 20)
            );
        }

        $this->enforceTahomaBold($document, $xpath);

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('Unable to generate the populated R4-01 Word document.');
        }

        return $xml;
    }

    private function populatePage(
        DOMDocument $document,
        DOMXPath $xpath,
        array $tables,
        array $header,
        array $rows
    ): void {
        $titleRows = $xpath->query('./w:tr', $tables[0]);
        $this->setCell($document, $xpath, $titleRows->item(0), 2, (($header['kalagayan'] ?? null) === 'MS' ? '☑' : '☐').' MS');
        $this->setCell($document, $xpath, $titleRows->item(0), 3, (($header['kalagayan'] ?? null) === 'UWP' ? '☑' : '☐').' UWP');

        $metadataRows = $xpath->query('./w:tr', $tables[1]);
        $this->setCell(
            $document,
            $xpath,
            $metadataRows->item(0),
            0,
            'DISTRITO: '.trim((string) ($header['district'] ?? ''))
        );
        $this->setCell(
            $document,
            $xpath,
            $metadataRows->item(0),
            1,
            'Dcode: '.trim((string) ($header['dcode'] ?? ''))
        );
        $this->setCell(
            $document,
            $xpath,
            $metadataRows->item(0),
            3,
            'PANGALAN: '.trim((string) ($header['name'] ?? ''))
        );
        $this->setCell(
            $document,
            $xpath,
            $metadataRows->item(0),
            4,
            'Assigned No.: '.trim((string) ($header['assigned_number'] ?? ''))
        );
        $this->setCell($document, $xpath, $metadataRows->item(0), 6, 'Buwan');
        $this->setCell($document, $xpath, $metadataRows->item(0), 7, 'Taon');
        $this->setCell(
            $document,
            $xpath,
            $metadataRows->item(1),
            0,
            'LOKAL: '.trim((string) ($header['locale'] ?? ''))
        );
        $this->setCell(
            $document,
            $xpath,
            $metadataRows->item(1),
            1,
            'Lcode: '.trim((string) ($header['lcode'] ?? ''))
        );
        $this->setCell($document, $xpath, $metadataRows->item(1), 5, (string) ($header['month'] ?? ''));
        $this->setCell($document, $xpath, $metadataRows->item(1), 6, (string) ($header['year'] ?? ''));

        $recordRows = $xpath->query('./w:tr', $tables[2]);
        if (! $recordRows || $recordRows->length < 2) {
            throw new RuntimeException('The editable R4-01 Word template has no detail rows.');
        }
        $this->setRepeatHeader($document, $xpath, $recordRows->item(0));

        foreach ($rows as $index => $row) {
            $tableRow = $recordRows->item($index + 1);
            $cellValues = [
                (string) (((int) ($header['page_number'] ?? 1) - 1) * 20 + $index + 1),
                (string) ($row['code'] ?? ''),
                (string) ($row['name'] ?? ''),
            ];
            foreach (array_slice(str_split((string) ($row['registry'] ?? '')), 0, 13) as $digit) {
                $cellValues[] = $digit;
            }
            $cellValues = array_pad($cellValues, 16, '');
            $cellValues[] = (string) ($row['gender'] ?? '');
            $cellValues[] = (string) ($row['purok_grupo'] ?? '');
            $cellValues[] = (string) ($row['kapisanan'] ?? '');
            $cellValues[] = (string) ($row['date_of_baptism'] ?? '');
            $cellValues[] = (string) ($row['doctrinator'] ?? '');

            foreach ($cellValues as $cellIndex => $value) {
                $this->setCell(
                    $document,
                    $xpath,
                    $tableRow,
                    $cellIndex,
                    $value,
                    null,
                    $cellIndex === 2 ? 'left' : 'center'
                );
            }
            if (($row['code'] ?? 'A') !== 'A') {
                $this->highlightMovementCode($document, $xpath, $tableRow);
            }
        }

        $signatureRows = $xpath->query('./w:tr', $tables[3]);
        $signatureLabels = ['Kalihim', 'Pangulong Diakono / KSP', 'Maysakop', 'Pastor / Destinado'];
        foreach ($signatureLabels as $index => $label) {
            $name = trim((string) ($header['signatures'][$index] ?? ''));
            $this->setCell(
                $document,
                $xpath,
                $signatureRows->item(0),
                $index,
                $name === '' ? $label : mb_strtoupper($name)."\n".$label,
                28
            );
        }
    }

    private function addPageBreakBeforeTitle(DOMDocument $document, DOMXPath $xpath, DOMElement $titleTable): void
    {
        $paragraph = $xpath->query('.//w:p', $titleTable)?->item(0);
        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('The editable R4-01 Word template has no title paragraph.');
        }
        $properties = $xpath->query('./w:pPr', $paragraph)?->item(0);
        if (! $properties instanceof DOMElement) {
            $properties = $document->createElementNS(self::WORD_NAMESPACE, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $properties->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:pageBreakBefore'));
    }

    private function enforceTahomaBold(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//w:rPr | //w:pPr/w:rPr | //w:docDefaults/w:rPrDefault/w:rPr') as $properties) {
            if (! $properties instanceof DOMElement) {
                continue;
            }

            $fonts = null;
            $bold = null;
            $complexBold = null;
            foreach ($properties->childNodes as $child) {
                if (! $child instanceof DOMElement || $child->namespaceURI !== self::WORD_NAMESPACE) {
                    continue;
                }
                if ($child->localName === 'rFonts') {
                    $fonts = $child;
                } elseif ($child->localName === 'b') {
                    $bold = $child;
                } elseif ($child->localName === 'bCs') {
                    $complexBold = $child;
                }
            }
            if (! $fonts instanceof DOMElement) {
                $fonts = $document->createElementNS(self::WORD_NAMESPACE, 'w:rFonts');
                $properties->insertBefore($fonts, $properties->firstChild);
            }
            foreach (['ascii', 'hAnsi', 'cs', 'eastAsia'] as $fontAttribute) {
                $fonts->setAttributeNS(self::WORD_NAMESPACE, 'w:'.$fontAttribute, 'Tahoma');
            }
            if (! $bold instanceof DOMElement) {
                $properties->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:b'));
            }
            if (! $complexBold instanceof DOMElement) {
                $properties->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:bCs'));
            }
        }
    }

    private function setRepeatHeader(DOMDocument $document, DOMXPath $xpath, ?DOMNode $row): void
    {
        if (! $row) {
            throw new RuntimeException('The editable R4-01 Word template is missing its detail header.');
        }
        $properties = $xpath->query('./w:trPr', $row)?->item(0);
        if (! $properties instanceof DOMElement) {
            $properties = $document->createElementNS(self::WORD_NAMESPACE, 'w:trPr');
            $row->insertBefore($properties, $row->firstChild);
        }
        if ($xpath->query('./w:tblHeader', $properties)?->length) {
            return;
        }
        $repeat = $document->createElementNS(self::WORD_NAMESPACE, 'w:tblHeader');
        $repeat->setAttributeNS(self::WORD_NAMESPACE, 'w:val', 'true');
        $properties->appendChild($repeat);
    }

    private function highlightMovementCode(DOMDocument $document, DOMXPath $xpath, ?DOMNode $row): void
    {
        if (! $row) {
            throw new RuntimeException('The editable R4-01 Word template is missing a movement code cell.');
        }

        $cell = $xpath->query('./w:tc', $row)?->item(1);
        if (! $cell instanceof DOMElement) {
            throw new RuntimeException('The editable R4-01 Word template is missing a movement code cell.');
        }
        foreach ($xpath->query('.//w:r', $cell) as $run) {
            if (! $run instanceof DOMElement) {
                continue;
            }
            $runProperties = $xpath->query('./w:rPr', $run)?->item(0);
            if (! $runProperties instanceof DOMElement) {
                $runProperties = $document->createElementNS(self::WORD_NAMESPACE, 'w:rPr');
                $run->insertBefore($runProperties, $run->firstChild);
            }
            $fontColor = $xpath->query('./w:color', $runProperties)?->item(0);
            if (! $fontColor instanceof DOMElement) {
                $fontColor = $document->createElementNS(self::WORD_NAMESPACE, 'w:color');
                $runProperties->appendChild($fontColor);
            }
            $fontColor->setAttributeNS(self::WORD_NAMESPACE, 'w:val', 'C62828');
        }
    }

    private function setCell(
        DOMDocument $document,
        DOMXPath $xpath,
        ?DOMNode $row,
        int $index,
        string $value,
        ?int $firstSegmentSize = null,
        ?string $alignment = null
    ): void
    {
        if (! $row) {
            throw new RuntimeException('The editable R4-01 Word template is missing a required row.');
        }
        $cells = $xpath->query('./w:tc', $row);
        $cell = $cells?->item($index);
        if (! $cell instanceof DOMElement) {
            throw new RuntimeException('The editable R4-01 Word template is missing a required cell.');
        }

        $paragraphs = $xpath->query('./w:p', $cell);
        $paragraph = $paragraphs?->item(0);
        if (! $paragraph instanceof DOMElement) {
            $paragraph = $document->createElementNS(self::WORD_NAMESPACE, 'w:p');
            $cell->appendChild($paragraph);
        }
        if ($alignment !== null) {
            $paragraphProperties = $xpath->query('./w:pPr', $paragraph)?->item(0);
            if (! $paragraphProperties instanceof DOMElement) {
                $paragraphProperties = $document->createElementNS(self::WORD_NAMESPACE, 'w:pPr');
                $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
            }
            $justification = $xpath->query('./w:jc', $paragraphProperties)?->item(0);
            if (! $justification instanceof DOMElement) {
                $justification = $document->createElementNS(self::WORD_NAMESPACE, 'w:jc');
                $paragraphProperties->appendChild($justification);
            }
            $justification->setAttributeNS(self::WORD_NAMESPACE, 'w:val', $alignment);
        }

        $runProperties = $xpath->query('.//w:r/w:rPr', $paragraph)?->item(0)?->cloneNode(true);
        foreach (iterator_to_array($cell->childNodes) as $child) {
            if ($child === $paragraph || ($child instanceof DOMElement && $child->localName === 'tcPr')) {
                continue;
            }
            $cell->removeChild($child);
        }
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if ($child instanceof DOMElement && $child->localName === 'pPr') {
                continue;
            }
            $paragraph->removeChild($child);
        }

        $segments = explode("\n", $value);
        foreach ($segments as $segmentIndex => $segment) {
            if ($segmentIndex > 0) {
                $breakRun = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
                $breakRun->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:br'));
                $paragraph->appendChild($breakRun);
            }
            $run = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
            $segmentProperties = $runProperties instanceof DOMElement
                ? $runProperties->cloneNode(true)
                : $document->createElementNS(self::WORD_NAMESPACE, 'w:rPr');
            if ($segmentIndex === 0 && $firstSegmentSize !== null) {
                $size = $xpath->query('./w:sz', $segmentProperties)?->item(0);
                if (! $size instanceof DOMElement) {
                    $size = $document->createElementNS(self::WORD_NAMESPACE, 'w:sz');
                    $segmentProperties->appendChild($size);
                }
                $size->setAttributeNS(self::WORD_NAMESPACE, 'w:val', (string) $firstSegmentSize);
                $complexSize = $xpath->query('./w:szCs', $segmentProperties)?->item(0);
                if (! $complexSize instanceof DOMElement) {
                    $complexSize = $document->createElementNS(self::WORD_NAMESPACE, 'w:szCs');
                    $segmentProperties->appendChild($complexSize);
                }
                $complexSize->setAttributeNS(self::WORD_NAMESPACE, 'w:val', (string) $firstSegmentSize);
            }
            if ($runProperties) {
                $run->appendChild($segmentProperties);
            } else {
                $run->appendChild($segmentProperties);
            }
            $text = $document->createElementNS(self::WORD_NAMESPACE, 'w:t');
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $text->appendChild($document->createTextNode($segment));
            $run->appendChild($text);
            $paragraph->appendChild($run);
        }
    }
}
