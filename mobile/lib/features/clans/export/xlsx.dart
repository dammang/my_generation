import 'package:archive/archive.dart';
import 'dart:convert';

import 'package:flutter/foundation.dart';

/// A workbook, written by hand.
///
/// An .xlsx is a zip of XML parts, and the packages that would assemble one
/// all pin an older `xml` than `pdf` does — and the chart exports need `pdf`.
/// So the parts are written here. It is a small, strict subset: one sheet,
/// text cells, no styling, which is what a roll of members is.
///
/// A real workbook rather than comma-separated text: a committee opens it,
/// sorts it, adds a column and saves it back, and CSV cannot be saved back as
/// a workbook.
class Xlsx {
  const Xlsx._();

  /// One sheet of rows, every cell a string.
  static Uint8List sheet({
    required String name,
    required List<List<String>> rows,
  }) {
    final archive = Archive();

    void add(String path, String contents) {
      final bytes = utf8Bytes(contents);

      archive.add(ArchiveFile.bytes(path, bytes));
    }

    add('[Content_Types].xml', _contentTypes);
    add('_rels/.rels', _rootRels);
    add('xl/workbook.xml', _workbook(name));
    add('xl/_rels/workbook.xml.rels', _workbookRels);
    add('xl/styles.xml', _styles);
    add('xl/worksheets/sheet1.xml', _sheet(rows));

    // Deflate, which is what every reader expects of an .xlsx.
    return Uint8List.fromList(ZipEncoder().encode(archive));
  }

  @visibleForTesting
  static Uint8List utf8Bytes(String value) =>
      Uint8List.fromList(const Utf8Encoder().convert(value));

  /// Excel is unforgiving here: an unescaped ampersand in a surname makes the
  /// whole workbook unreadable rather than that one cell wrong.
  @visibleForTesting
  static String escape(String value) => value
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&apos;')
      // Control characters are not permitted in XML at all, and a stray one
      // pasted into a contact field would take the file with it.
      .replaceAll(RegExp(r'[\x00-\x08\x0B\x0C\x0E-\x1F]'), '');

  /// "A", "B" … "Z", "AA". Only the first nine are needed today; getting this
  /// wrong at column 27 is the kind of thing found a year later.
  @visibleForTesting
  static String columnName(int index) {
    var name = '';
    var remaining = index;

    while (remaining >= 0) {
      name = String.fromCharCode(65 + remaining % 26) + name;
      remaining = remaining ~/ 26 - 1;
    }

    return name;
  }

  static String _sheet(List<List<String>> rows) {
    final buffer = StringBuffer()
      ..write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
      ..write(
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">',
      )
      ..write('<sheetData>');

    for (var r = 0; r < rows.length; r++) {
      buffer.write('<row r="${r + 1}">');

      for (var c = 0; c < rows[r].length; c++) {
        final reference = '${columnName(c)}${r + 1}';

        // Inline strings: no shared-string table to keep in step, and every
        // value in a roll of members is text anyway.
        buffer.write(
          '<c r="$reference" t="inlineStr"><is><t xml:space="preserve">'
          '${escape(rows[r][c])}</t></is></c>',
        );
      }

      buffer.write('</row>');
    }

    buffer.write('</sheetData></worksheet>');

    return buffer.toString();
  }

  static const _contentTypes =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      '<Default Extension="xml" ContentType="application/xml"/>'
      '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
      '</Types>';

  static const _rootRels =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
      '</Relationships>';

  static const _workbookRels =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
      '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
      '</Relationships>';

  static const _styles =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
      '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
      '<borders count="1"><border/></borders>'
      '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
      '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
      '</styleSheet>';

  /// The sheet's name is what a reader sees on the tab. Excel rejects a few
  /// characters in it and truncates at 31, so it is cleaned rather than
  /// passed through.
  static String _workbook(String name) {
    final tab = escape(name.replaceAll(RegExp(r'[\[\]\*/\\\?:]'), ' ').trim());

    final safe = tab.isEmpty
        ? 'Members'
        : (tab.length > 31 ? tab.substring(0, 31) : tab);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        '<sheets><sheet name="$safe" sheetId="1" r:id="rId1"/></sheets>'
        '</workbook>';
  }
}
