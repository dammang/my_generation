import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:share_plus/share_plus.dart';

import '../../../core/constants/countries.dart';
import '../../../models/membership.dart';
import '../../tree/export/pdf_text.dart';
import 'xlsx.dart';

/// The members of a family, as something you can send somebody.
///
/// Two formats because they answer different questions. The document is for
/// reading and keeping; the spreadsheet is for counting, sorting and pasting
/// into whatever a committee already uses.
class MemberRoll {
  const MemberRoll({required this.members, required this.title});

  final List<Membership> members;

  /// Which family, and filtered how — printed on it, because a roll with no
  /// caption is a list nobody can file.
  final String title;

  static const columns = [
    'Name',
    'Email',
    'Father',
    'Mother',
    'Grandfather',
    'Grandmother',
    'Country',
    'Contact',
    'Joined',
  ];

  List<String> _row(Membership member) => [
    member.name,
    member.email ?? '',
    member.fatherName ?? '',
    member.motherName ?? '',
    member.grandfatherName ?? '',
    member.grandmotherName ?? '',
    Countries.nameOf(member.country) ?? member.country ?? '',
    member.contact ?? '',
    day(member.joinedAt),
  ];

  /// Every row, header first.
  List<List<String>> get rows => [columns, ...members.map(_row)];

  /// A real workbook, not comma-separated text wearing the name.
  ///
  /// A committee opens the roll, sorts it, adds a column and saves it back;
  /// CSV can be opened but not saved back as a workbook.
  Uint8List get workbook => Xlsx.sheet(name: title, rows: rows);

  Future<Uint8List> documentBytes() async {
    final document = pw.Document(title: plainForPdf(title));

    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(28),
        header: (context) => context.pageNumber == 1
            ? pw.SizedBox()
            : pw.Padding(
                padding: const pw.EdgeInsets.only(bottom: 8),
                child: pw.Text(
                  plainForPdf(title),
                  style: const pw.TextStyle(
                    fontSize: 9,
                    color: PdfColors.grey700,
                  ),
                ),
              ),
        build: (context) => [
          pw.Text(
            plainForPdf(title),
            style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
          ),
          pw.SizedBox(height: 2),
          pw.Text(
            members.length == 1 ? '1 member' : '${members.length} members',
            style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
          ),
          pw.SizedBox(height: 12),
          pw.TableHelper.fromTextArray(
            headers: columns,
            data: members
                .map((m) => _row(m).map(plainForPdf).toList())
                .toList(),
            headerStyle: pw.TextStyle(
              fontSize: 8,
              fontWeight: pw.FontWeight.bold,
            ),
            cellStyle: const pw.TextStyle(fontSize: 8),
            headerDecoration: const pw.BoxDecoration(color: PdfColors.grey200),
            cellPadding: const pw.EdgeInsets.symmetric(
              horizontal: 4,
              vertical: 3,
            ),
            border: pw.TableBorder.all(color: PdfColors.grey400, width: 0.4),
          ),
        ],
      ),
    );

    return document.save();
  }

  Future<void> shareDocument() async =>
      _share(await _write(await documentBytes(), 'pdf'), 'application/pdf');

  Future<void> shareSpreadsheet() async => _share(
    await _write(workbook, 'xlsx'),
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  );

  Future<String> _write(Uint8List bytes, String extension) async {
    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/${_fileName(extension)}');

    await file.writeAsBytes(bytes, flush: true);

    return file.path;
  }

  Future<void> _share(String path, String mime) => SharePlus.instance.share(
    ShareParams(files: [XFile(path, mimeType: mime)]),
  );

  String _fileName(String extension) {
    final stem = title
        .replaceAll(RegExp(r'[^A-Za-z0-9 ]'), '')
        .trim()
        .replaceAll(RegExp(r'\s+'), '-')
        .toLowerCase();

    return '${stem.isEmpty ? 'members' : stem}.$extension';
  }

  static String day(DateTime? when) => when == null
      ? ''
      : '${when.year}-${when.month.toString().padLeft(2, '0')}-'
            '${when.day.toString().padLeft(2, '0')}';
}
