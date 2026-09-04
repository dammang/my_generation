/// A photograph held on the media store.
///
/// [url] is what the server decided this viewer may load: the public domain
/// for public media, a signed link that expires for everything else. The
/// client never sees the stored path and cannot construct a URL of its own.
class MediaItem {
  const MediaItem({
    required this.ulid,
    required this.url,
    required this.isPrivate,
    this.caption,
    this.width,
    this.height,
    this.takenAt,
    this.uploadedBy,
  });

  final String ulid;
  final String url;
  final bool isPrivate;
  final String? caption;
  final int? width;
  final int? height;
  final String? takenAt;
  final String? uploadedBy;

  /// Used to lay a grid out without the images jumping as they load.
  double? get aspectRatio =>
      (width != null && height != null && height! > 0) ? width! / height! : null;

  factory MediaItem.fromJson(Map<String, dynamic> json) => MediaItem(
    ulid: json['ulid'] as String,
    url: json['url'] as String? ?? '',
    isPrivate: json['is_private'] as bool? ?? true,
    caption: json['caption'] as String?,
    width: json['width'] as int?,
    height: json['height'] as int?,
    takenAt: json['taken_at'] as String?,
    uploadedBy: json['uploaded_by'] as String?,
  );
}

/// Photographs, plus why there are none.
///
/// Withheld and empty are different facts. An empty album invites a
/// contribution; a withheld one must not, because the viewer is not permitted
/// to see this person's face and saying "add a photo" would misdescribe that.
class MediaAlbum {
  const MediaAlbum({required this.items, required this.withheld});

  const MediaAlbum.withheldFrom() : items = const [], withheld = true;

  final List<MediaItem> items;
  final bool withheld;

  bool get isEmpty => items.isEmpty && !withheld;
}
