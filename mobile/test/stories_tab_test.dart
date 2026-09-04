import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/widgets/stories_tab.dart';
import 'package:my_generation/models/story.dart';

Story _story({
  String ulid = '01STORY',
  String title = 'The weather diaries',
  String? summary = 'Forty years of one man\'s weather notes.',
  String visibility = 'family',
  String? body,
  int? eraStart,
  int? eraEnd,
}) => Story(
  ulid: ulid,
  title: title,
  storyType: 'memory',
  visibility: visibility,
  summary: summary,
  body: body,
  authorName: 'Daniel Whitfield',
  eraStartYear: eraStart,
  eraEndYear: eraEnd,
);

Future<void> _pump(WidgetTester tester, List<Story> stories, {void Function(Story)? onOpen}) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: StoriesTab(stories: stories, onOpen: onOpen ?? (_) {}, onWrite: () {}),
      ),
    ),
  );
}

void main() {
  group('StoriesTab', () {
    testWidgets('an empty archive invites the first story', (tester) async {
      await _pump(tester, const []);

      expect(find.text('No stories yet'), findsOneWidget);
      expect(find.text('Write one'), findsOneWidget);
    });

    testWidgets('a card shows the summary, never the body', (tester) async {
      await _pump(tester, [
        _story(
          summary: 'Forty years of weather notes.',
          body: 'THE FULL TEXT THAT A LIST HAS NO BUSINESS CARRYING',
        ),
      ]);

      expect(find.text('Forty years of weather notes.'), findsOneWidget);
      expect(
        find.textContaining('NO BUSINESS CARRYING'),
        findsNothing,
        reason: 'the body belongs to the reading screen, not the list',
      );
    });

    testWidgets('who may read it is shown, so nobody publishes by accident', (tester) async {
      await _pump(tester, [_story(visibility: 'family')]);

      expect(find.text('Family'), findsOneWidget);
    });

    testWidgets('a public story carries no audience badge', (tester) async {
      await _pump(tester, [_story(visibility: 'public')]);

      // A badge on every card would be noise; the one that matters is the one
      // saying this is *not* public.
      expect(find.text('Family'), findsNothing);
      expect(find.text('Tribe'), findsNothing);
    });

    testWidgets('an era is shown when the story has one', (tester) async {
      await _pump(tester, [_story(eraStart: 1926, eraEnd: 1998)]);

      expect(find.text('1926–1998'), findsOneWidget);
    });

    testWidgets('tapping a card opens that story', (tester) async {
      Story? opened;
      await _pump(tester, [_story(ulid: '01OPENED')], onOpen: (story) => opened = story);

      await tester.tap(find.text('The weather diaries'));
      await tester.pumpAndSettle();

      expect(opened?.ulid, '01OPENED');
    });
  });
}
