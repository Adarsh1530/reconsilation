<?php
/**
 * Simple XLSX Writer for School Reconciliation Management System
 * Generates standards-compliant OpenXML (.xlsx) files using native PHP ZipArchive.
 * Zero external dependencies. Works on Vercel Serverless PHP.
 */

class SimpleXlsxWriter {
    /**
     * Create an .xlsx file at $filePath
     *
     * @param string $filePath Absolute path where .xlsx should be saved
     * @param array $headers List of column header strings [ 'school name', 1, 2, ... ]
     * @param array $rows 2D array of rows [[ 'BVBMVA', 'cell text...', ... ], ...]
     * @param string $sheetTitle Title of the worksheet tab
     * @return bool True on success, false on failure
     */
    public static function create($filePath, array $headers, array $rows, $sheetTitle = 'Reconciliation Report') {
        if (!class_exists('ZipArchive')) {
            error_log('SimpleXlsxWriter error: ZipArchive class not found.');
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            error_log("SimpleXlsxWriter error: Could not open $filePath for writing.");
            return false;
        }

        // Clean sheet title (max 31 chars, no invalid characters)
        $cleanTitle = substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $sheetTitle), 0, 31) ?: 'Report';

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . htmlspecialchars($cleanTitle, ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 5. xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <!-- Font 0: Normal Calibri 11 -->
    <font><sz val="11"/><name val="Calibri"/></font>
    <!-- Font 1: Header Bold White 11 -->
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    <!-- Font 2: Bold Dark 11 -->
    <font><b/><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>
  </fonts>
  <fills count="4">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <!-- Fill 2: Primary Navy Blue header -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
    <!-- Fill 3: Soft gray zebra / school name -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="4">
    <!-- Style 0: Default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <!-- Style 1: Table Header (Navy Blue background, white bold text, centered) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <!-- Style 2: School Name Column (Bold, left aligned, centered vertically) -->
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center" wrapText="1"/>
    </xf>
    <!-- Style 3: Report Data Cell (Wrap text, top aligned, clean border) -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>
  </cellXfs>
</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $colCount = count($headers);
        // Column 1 (School Name) width 24, all subsequent day columns width 22
        $colXml = '<cols><col min="1" max="1" width="24" customWidth="1"/>';
        for ($c = 2; $c <= $colCount; $c++) {
            $colXml .= '<col min="' . $c . '" max="' . $c . '" width="22" customWidth="1"/>';
        }
        $colXml .= '</cols>';

        $sheetData = '<sheetData>';

        // Row 1: Headers
        $sheetData .= '<row r="1" ht="28" customHeight="1">';
        foreach ($headers as $colIdx => $val) {
            $cellRef = self::colName($colIdx + 1) . '1';
            $sheetData .= '<c r="' . $cellRef . '" s="1" t="inlineStr"><is><t>' . htmlspecialchars((string)$val, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }
        $sheetData .= '</row>';

        // Rows 2..N: Data
        $r = 2;
        foreach ($rows as $row) {
            // Default row height 115 to comfortably display multiline content (User, Time, Nil, PS, Recon, Refund, Duplicate)
            $sheetData .= '<row r="' . $r . '" ht="115" customHeight="1">';
            foreach ($row as $colIdx => $val) {
                $cellRef = self::colName($colIdx + 1) . $r;
                $styleId = ($colIdx === 0) ? '2' : '3';
                $valStr = (string)$val;
                if ($valStr !== '') {
                    $sheetData .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars($valStr, ENT_XML1, 'UTF-8') . '</t></is></c>';
                } else {
                    $sheetData .= '<c r="' . $cellRef . '" s="' . $styleId . '"/>';
                }
            }
            $sheetData .= '</row>';
            $r++;
        }
        $sheetData .= '</sheetData>';

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $colXml . $sheetData . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);

        $zip->close();
        return true;
    }

    /**
     * Convert 1-based column index to Excel column string (1 -> A, 2 -> B, 27 -> AA)
     */
    public static function colName($colIndex) {
        $name = '';
        while ($colIndex > 0) {
            $rem = ($colIndex - 1) % 26;
            $name = chr(65 + $rem) . $name;
            $colIndex = intval(($colIndex - $rem) / 26);
        }
        return $name;
    }
}
