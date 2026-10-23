<?php

namespace App\Services;

use Phar;
use PharData;
use RuntimeException;
use Throwable;

class DelistmentDocxExporter
{
    public function generate(string $title, array $members, bool $history = false): string
    {
        $outputPath = ExportTempFiles::path('R2-10');
        try {
            $archive = new PharData($outputPath, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', $this->contentTypes());
            $archive->addFromString('_rels/.rels', $this->packageRelationships());
            $archive->addFromString('word/document.xml', $this->document($title, $members, $history));
            unset($archive);
        } catch (Throwable $exception) {
            unset($archive);
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
            throw $exception;
        }

        return $outputPath;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function document(string $title, array $members, bool $history): string
    {
        $columns = $history
            ? [
                ['Name', 3000],
                ['Registry', 2000],
                ['Date of delistment', 2400],
                ['Reason', 5000],
                ['Delisted by', 2800],
            ]
            : [
                ['Name', 4200],
                ['Registry', 2400],
                ['Kalagayan', 1400],
                ['Eligibility', 2400],
                ['Report status', 4800],
            ];
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$this->confidentialNotice()
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="30"/></w:rPr><w:t>'
            .$this->escape($title)
            .'</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>'.$this->escape($history ? 'Delistment history' : 'R2-10 eligibility monitoring').'</w:t></w:r></w:p>'
            .'<w:tbl><w:tblPr><w:tblW w:w="15200" w:type="dxa"/><w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="808080"/>'
            .'<w:left w:val="single" w:sz="4" w:color="808080"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="808080"/>'
            .'<w:right w:val="single" w:sz="4" w:color="808080"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="B7B7B7"/>'
            .'<w:insideV w:val="single" w:sz="4" w:color="B7B7B7"/>'
            .'</w:tblBorders></w:tblPr><w:tblGrid>';
        foreach ($columns as [, $width]) {
            $xml .= '<w:gridCol w:w="'.$width.'"/>';
        }
        $xml .= '</w:tblGrid><w:tr>';
        foreach ($columns as [$label, $width]) {
            $xml .= $this->cell($label, $width, true);
        }
        $xml .= '</w:tr>';

        foreach ($members as $member) {
            if ($history) {
                $values = [
                    $member['name'],
                    $member['registry'] !== '' ? $member['registry'] : '—',
                    $member['delisted_at'],
                    $member['reason'],
                    $member['delisted_by'],
                ];
            } else {
                $reportStatus = $member['reported']
                    ? 'Created & reported'
                    : (! $member['eligible'] && $member['endorsed']
                        ? 'Endorsed · Not eligible — review required'
                        : 'Not yet done');
                $values = [
                    $member['name'],
                    $member['registry'] !== '' ? $member['registry'] : '—',
                    $member['kalagayan'],
                    $member['eligible'] ? 'Eligible' : 'Not eligible',
                    $reportStatus,
                ];
            }
            $xml .= '<w:tr>';
            foreach ($columns as $index => [, $width]) {
                $xml .= $this->cell($values[$index], $width);
            }
            $xml .= '</w:tr>';
        }

        return $xml
            .'</w:tbl>'
            .'<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            .'<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/>'
            .'</w:sectPr></w:body></w:document>';
    }

    private function confidentialNotice(): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="C00000"/><w:sz w:val="28"/></w:rPr><w:t>CONFIDENTIAL</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Authorized and Confidential Information Ahead</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>This document contains confidential personal information.</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Access and use are limited to authorized purposes and personnel.</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>If you are not authorized to access this document, stop viewing it and securely delete this copy. Unauthorized access, retention, copying, or disclosure may result in liability under applicable data privacy laws.</w:t></w:r></w:p>';
    }

    private function cell(string $value, int $width, bool $header = false): string
    {
        $shading = $header ? '<w:shd w:fill="D9EAF7"/>' : '';
        $bold = $header ? '<w:b/>' : '';

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$shading.'</w:tcPr>'
            .'<w:p><w:pPr><w:spacing w:after="40"/></w:pPr><w:r><w:rPr>'.$bold
            .'<w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">'.$this->escape($value)
            .'</w:t></w:r></w:p></w:tc>';
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
