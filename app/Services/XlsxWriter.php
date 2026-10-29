<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Tạo file Excel .xlsx thật (1 sheet) không cần thư viện ngoài, chỉ dùng ZipArchive.
 * Mỗi ô: chuỗi/số, hoặc ['v' => giá trị, 's' => tên kiểu] với kiểu trong STYLES.
 */
class XlsxWriter
{
    // Thứ tự = chỉ số cellXfs trong styles.xml
    private const STYLES = ['default', 'title', 'subtitle', 'label', 'header', 'text', 'money', 'int', 'total_label', 'total_money', 'total_int', 'note', 'percent', 'total_percent'];

    private array $rows = [];
    private array $merges = [];
    private array $widths = [];
    private array $heights = [];

    public function __construct(private string $sheetName = 'Sheet1')
    {
    }

    public function addRow(array $cells, ?float $height = null): int
    {
        $this->rows[] = $cells;
        $index = count($this->rows);
        if ($height !== null) {
            $this->heights[$index] = $height;
        }

        return $index;
    }

    public function merge(string $range): void
    {
        $this->merges[] = $range;
    }

    public function setColumnWidths(array $widths): void
    {
        $this->widths = $widths;
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file Excel.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xml(mb_substr($this->sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
        $zip->close();
    }

    private function sheetXml(): string
    {
        $cols = '';
        foreach ($this->widths as $i => $width) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $width . '" customWidth="1"/>';
        }

        $rowsXml = '';
        foreach ($this->rows as $r => $cells) {
            $rowNumber = $r + 1;
            $attrs = isset($this->heights[$rowNumber]) ? ' ht="' . $this->heights[$rowNumber] . '" customHeight="1"' : '';
            $rowsXml .= '<row r="' . $rowNumber . '"' . $attrs . '>';
            foreach (array_values($cells) as $c => $cell) {
                $ref = $this->columnLetter($c) . $rowNumber;
                $value = is_array($cell) ? ($cell['v'] ?? null) : $cell;
                $style = is_array($cell) ? array_search($cell['s'] ?? 'default', self::STYLES, true) : 0;
                $s = $style ? ' s="' . $style . '"' : '';
                if ($value === null || $value === '') {
                    $rowsXml .= '<c r="' . $ref . '"' . $s . '/>';
                } elseif (is_int($value) || is_float($value)) {
                    $rowsXml .= '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . $this->xml((string) $value) . '</t></is></c>';
                }
            }
            $rowsXml .= '</row>';
        }

        $merges = $this->merges
            ? '<mergeCells count="' . count($this->merges) . '">' . implode('', array_map(fn ($m) => '<mergeCell ref="' . $m . '"/>', $this->merges)) . '</mergeCells>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . ($cols ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . $merges
            . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
    }

    private function stylesXml(): string
    {
        // fonts: 0 thường, 1 tiêu đề, 2 đậm, 3 nghiêng xám, 4 đậm trắng (tiêu đề cột)
        $fonts = '<fonts count="5">'
            . '<font><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF1E3A8A"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><name val="Arial"/></font>'
            . '<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '</fonts>';
        // fills: 0, 1 bắt buộc; 2 xanh đậm (tiêu đề cột); 3 xanh nhạt (tổng cộng)
        $fills = '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>';
        $thin = '<left style="thin"><color rgb="FF9CA3AF"/></left><right style="thin"><color rgb="FF9CA3AF"/></right>'
            . '<top style="thin"><color rgb="FF9CA3AF"/></top><bottom style="thin"><color rgb="FF9CA3AF"/></bottom><diagonal/>';
        $borders = '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $thin . '</border></borders>';
        $numFmts = '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts>';

        $xf = fn ($font, $fill, $border, $numFmt = 0, $align = '') => '<xf numFmtId="' . $numFmt . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="' . $border . '"'
            . ($numFmt ? ' applyNumberFormat="1"' : '') . ' applyFont="1" applyFill="1" applyBorder="1"' . ($align ? ' applyAlignment="1">' . $align . '</xf>' : '/>');
        $center = '<alignment horizontal="center" vertical="center" wrapText="1"/>';
        $left = '<alignment horizontal="left" vertical="center" wrapText="1"/>';
        $right = '<alignment horizontal="right" vertical="center"/>';

        // Cùng thứ tự với STYLES
        $cellXfs = [
            $xf(0, 0, 0),                     // default
            $xf(1, 0, 0, 0, $center),         // title
            $xf(0, 0, 0, 0, $center),         // subtitle
            $xf(2, 0, 0, 0, $left),           // label
            $xf(4, 2, 1, 0, $center),         // header
            $xf(0, 0, 1, 0, $left),           // text
            $xf(0, 0, 1, 164, $right),        // money
            $xf(0, 0, 1, 0, $center),         // int
            $xf(2, 3, 1, 0, $center),         // total_label
            $xf(2, 3, 1, 164, $right),        // total_money
            $xf(2, 3, 1, 0, $center),         // total_int
            $xf(3, 0, 0, 0, $left),           // note
            $xf(0, 0, 1, 165, $right),        // percent
            $xf(2, 3, 1, 165, $right),        // total_percent
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $numFmts . $fonts . $fills . $borders
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($cellXfs) . '">' . implode('', $cellXfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function columnLetter(int $index): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26) . $letters;
        }

        return $letters;
    }

    private function xml(string $value): string
    {
        // Bỏ ký tự điều khiển không hợp lệ trong XML
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
