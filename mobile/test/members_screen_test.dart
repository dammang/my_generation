import 'dart:convert';

import 'package:archive/archive.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/constants/countries.dart';
import 'package:my_generation/features/clans/export/member_roll.dart';
import 'package:my_generation/features/clans/export/xlsx.dart';
import 'package:my_generation/features/clans/view/members_screen.dart';
import 'package:my_generation/models/membership.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _jk = '01JKJKJKJKJKJKJKJKJKJKJKJK';
const _other = '01OTHEROTHEROTHEROTHEROTH';

Map<String, dynamic> _scope(String ulid, String name) => {
  'scope_type': 'clan',
  'scope_ulid': ulid,
  'name': name,
  'assignable_roles': <String>[],
};

Map<String, dynamic> _member({
  String name = 'Cing Za Man',
  String country = 'MM',
}) => {
  'ulid': '01MEMBER${name.hashCode.abs()}',
  'status': 'active',
  'approved_at': '2026-09-01T10:00:00+00:00',
  'scope': {'type': 'clan', 'ulid': _jk, 'name': 'JK'},
  'user': {'ulid': '01USERUSERUSERUSERUSERUSER', 'name': 'ngaih kim'},
  'applicant': {
    'name': name,
    'email': 'ngaih@example.com',
    'father': 'Thawng Dam',
    'mother': 'Niang Za Dim',
    'grandfather': 'Hau Neng',
    'grandmother': 'Dim Zel',
    'country': country,
    'contact': '+95 9 123 456',
  },
};

Future<FakeAdapter> _pump(
  WidgetTester tester, {
  List<Map<String, dynamic>>? scopes,
  List<Map<String, dynamic>>? members,
}) async {
  await tester.binding.setSurfaceSize(const Size(1200, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/scope-roles/administered': [
      FakeReply(200, {
        'success': true,
        'data': scopes ?? [_scope(_jk, 'JK')],
      }),
    ],
    'GET /api/v1/scope-members': [
      FakeReply(200, {
        'success': true,
        'data': members ?? [_member()],
      }),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(home: MembersScreen()),
    ),
  );

  await tester.pumpAndSettle();
  await tester.pump(const Duration(milliseconds: 50));
  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('a member is a row, read across', (tester) async {
    final adapter = await _pump(tester);

    expect(find.byType(DataTable), findsOneWidget);

    for (final column in [
      'Photo',
      'Name',
      'Parents',
      'Grandparents',
      'Contact',
      'Joined',
    ]) {
      expect(find.text(column), findsWidgets, reason: '$column column missing');
    }

    // Name over email, father over mother, grandfather over grandmother.
    expect(find.text('Cing Za Man'), findsOneWidget);
    expect(find.text('ngaih@example.com'), findsOneWidget);
    expect(find.text('Thawng Dam'), findsOneWidget);
    expect(find.text('Niang Za Dim'), findsOneWidget);
    expect(find.text('Hau Neng'), findsOneWidget);
    expect(find.text('Dim Zel'), findsOneWidget);

    // The country was stored as a code; a reader wants the country.
    expect(find.text('Myanmar (Burma)'), findsWidgets);
    expect(find.text('MM'), findsNothing);

    // Members, not applicants: the queue of requests is its own page.
    final asked = adapter.received.singleWhere(
      (r) => r.path == '/api/v1/scope-members',
    );

    expect(asked.queryParameters['status'], 'active');
    expect(asked.queryParameters['scope_ulid'], _jk);
  });

  testWidgets('the father is above the mother', (tester) async {
    await _pump(tester);

    expect(
      tester.getTopLeft(find.text('Thawng Dam')).dy,
      lessThan(tester.getTopLeft(find.text('Niang Za Dim')).dy),
    );
  });

  testWidgets('a clan is chosen from a dropdown', (tester) async {
    await _pump(tester, scopes: [_scope(_jk, 'JK'), _scope(_other, 'Sukte')]);

    expect(find.byType(DropdownButtonFormField<String>), findsOneWidget);

    await tester.tap(find.text('JK').last);
    await tester.pumpAndSettle();

    expect(find.text('Sukte'), findsWidgets);
  });

  testWidgets('a country filter narrows the roll', (tester) async {
    await _pump(
      tester,
      members: [
        _member(name: 'At Home'),
        _member(name: 'Abroad', country: 'MY'),
      ],
    );

    expect(find.text('At Home'), findsOneWidget);
    expect(find.text('Abroad'), findsOneWidget);

    await tester.tap(find.text('Anywhere'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Malaysia').last);
    await tester.pumpAndSettle();

    expect(find.text('Abroad'), findsOneWidget);
    expect(find.text('At Home'), findsNothing);
  });

  testWidgets('somebody who runs nothing is told so', (tester) async {
    await _pump(tester, scopes: []);

    expect(find.text('You do not run a family yet.'), findsOneWidget);
  });

  test('the spreadsheet is a real workbook', () {
    final roll = MemberRoll(
      members: [
        Membership.fromJson(_member(name: 'Cing Za Man')),
        Membership.fromJson(_member(name: 'Abroad', country: 'MY')),
      ],
      title: 'JK',
    );

    final bytes = roll.workbook;

    // PK: a zip, which is what an .xlsx is.
    expect(bytes.take(2).toList(), [0x50, 0x4b]);

    final parts = {
      for (final file in ZipDecoder().decodeBytes(bytes))
        file.name: utf8.decode(file.content as List<int>),
    };

    // Every part a reader looks for. A workbook missing one of these opens as
    // "the file is corrupt", which is indistinguishable from having exported
    // nothing.
    for (final required in [
      '[Content_Types].xml',
      '_rels/.rels',
      'xl/workbook.xml',
      'xl/_rels/workbook.xml.rels',
      'xl/worksheets/sheet1.xml',
      'xl/styles.xml',
    ]) {
      expect(parts.keys, contains(required));
    }

    final sheet = parts['xl/worksheets/sheet1.xml']!;

    expect(sheet, contains('Cing Za Man'));
    expect(sheet, contains('Myanmar (Burma)'));
    expect(sheet, contains('Malaysia'));
    expect(sheet, contains('<c r="A1"'));

    // Header plus two members.
    expect(RegExp('<row ').allMatches(sheet).length, 3);
    expect(parts['xl/workbook.xml'], contains('name="JK"'));
  });

  test('a name that would break the file is escaped', () {
    // An unescaped ampersand in a surname makes the whole workbook unreadable
    // rather than that one cell wrong.
    final bytes = Xlsx.sheet(
      name: 'JK',
      rows: [
        ['Name'],
        ['Tom & Jerry <"x">'],
      ],
    );

    final sheet = ZipDecoder()
        .decodeBytes(bytes)
        .firstWhere((f) => f.name == 'xl/worksheets/sheet1.xml');

    final xml = utf8.decode(sheet.content as List<int>);

    expect(xml, contains('Tom &amp; Jerry &lt;&quot;x&quot;&gt;'));
    expect(xml, isNot(contains('Tom & Jerry')));
  });

  test('a sheet name Excel would refuse is cleaned', () {
    // Excel rejects these outright and truncates past 31 characters, so a tab
    // named after a filter would have produced a file that would not open.
    final bytes = Xlsx.sheet(
      name: 'JK / Myanmar [2026] : a very long tab name indeed',
      rows: [
        ['Name'],
      ],
    );

    final workbook = ZipDecoder()
        .decodeBytes(bytes)
        .firstWhere((f) => f.name == 'xl/workbook.xml');

    final name = RegExp(
      'name="([^"]*)"',
    ).firstMatch(utf8.decode(workbook.content as List<int>))!.group(1)!;

    expect(name, isNot(contains('/')));
    expect(name, isNot(contains('[')));
    expect(name.length, lessThanOrEqualTo(31));
  });

  test('columns keep counting past Z', () {
    // Only the first nine are needed today; getting this wrong at column 27 is
    // the kind of thing found a year later.
    expect(Xlsx.columnName(0), 'A');
    expect(Xlsx.columnName(25), 'Z');
    expect(Xlsx.columnName(26), 'AA');
    expect(Xlsx.columnName(27), 'AB');
    expect(Xlsx.columnName(51), 'AZ');
    expect(Xlsx.columnName(52), 'BA');
  });

  test('every country in the picker has a code and a name', () {
    expect(Countries.byCode.length, greaterThan(200));
    expect(Countries.nameOf('MM'), 'Myanmar (Burma)');
    expect(Countries.nameOf('mm'), 'Myanmar (Burma)');
    expect(Countries.nameOf('ZZ'), isNull);
    expect(Countries.matching('myan').single.key, 'MM');

    // Only what is used, named and sorted.
    expect(Countries.only(['MY', 'MM', null, 'ZZ']).keys.toList(), [
      'MY',
      'MM',
    ]);
  });
}
