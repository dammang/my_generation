import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/widgets/photos_tab.dart';
import 'package:my_generation/models/media_item.dart';

MediaItem _item({String ulid = '01PHOTO', String? caption}) => MediaItem(
  ulid: ulid,
  url: 'https://media.khanggui.com/x.jpg',
  isPrivate: true,
  caption: caption,
);

Future<void> _pump(WidgetTester tester, MediaAlbum album) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: PhotosTab(album: album, onOpen: (_) {}, onAdd: () {}),
      ),
    ),
  );
}

void main() {
  group('PhotosTab', () {
    testWidgets('an empty album says so and does not blame permissions', (tester) async {
      await _pump(tester, const MediaAlbum(items: [], withheld: false));

      expect(find.text('No photographs yet'), findsOneWidget);
      expect(find.text('Add a photograph'), findsOneWidget);
      expect(find.textContaining('private'), findsNothing);
    });

    testWidgets('a withheld album says private and never invites an upload', (tester) async {
      await _pump(tester, const MediaAlbum.withheldFrom());

      expect(find.text('These photographs are private'), findsOneWidget);
      // Asking somebody to add a photograph of a person they are not allowed
      // to see would misdescribe why the screen is empty.
      expect(find.text('Add a photograph'), findsNothing);
    });

    testWidgets('a caption is shown over its photograph', (tester) async {
      await _pump(
        tester,
        MediaAlbum(items: [_item(caption: 'Edward at the farm, 1961')], withheld: false),
      );

      expect(find.text('Edward at the farm, 1961'), findsOneWidget);
    });
  });
}
