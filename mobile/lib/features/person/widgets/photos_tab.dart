import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../models/media_item.dart';

/// A person's photographs.
///
/// Three states, like the timeline: photographs, none recorded, and withheld.
/// A withheld album must not invite an upload — the viewer is not permitted to
/// see this person's face, and asking them to add one misdescribes why the
/// screen is empty.
class PhotosTab extends StatelessWidget {
  const PhotosTab({
    super.key,
    required this.album,
    required this.onOpen,
    required this.onAdd,
  });

  final MediaAlbum album;
  final void Function(MediaItem item) onOpen;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (album.withheld) {
      return _Empty(
        icon: Icons.lock_outline,
        title: 'These photographs are private',
        message: 'You do not have permission to see pictures of this person.',
      );
    }

    if (album.isEmpty) {
      return _Empty(
        icon: Icons.photo_library_outlined,
        title: 'No photographs yet',
        message:
            'A face is the part of a record nobody has to be taught to read.',
        action: FilledButton.icon(
          onPressed: onAdd,
          icon: const Icon(Icons.add_a_photo_outlined),
          label: const Text('Add a photograph'),
        ),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
      ),
      itemCount: album.items.length,
      itemBuilder: (context, index) {
        final item = album.items[index];

        return InkWell(
          onTap: () => onOpen(item),
          borderRadius: BorderRadius.circular(12),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Stack(
              fit: StackFit.expand,
              children: [
                CachedNetworkImage(
                  imageUrl: item.url,
                  fit: BoxFit.cover,
                  placeholder: (context, _) =>
                      ColoredBox(color: theme.colorScheme.surfaceContainerHighest),
                  // A signed URL expires. When one does, the honest thing is a
                  // broken-image placeholder rather than a spinner that never
                  // resolves.
                  errorWidget: (context, _, _) => ColoredBox(
                    color: theme.colorScheme.surfaceContainerHighest,
                    child: Icon(
                      Icons.image_not_supported_outlined,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
                if (item.caption != null)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      color: Colors.black.withValues(alpha: 0.55),
                      child: Text(
                        item.caption!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelSmall?.copyWith(color: Colors.white),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;

  /// Never set on the withheld state: offering to add a photograph of somebody
  /// the viewer is not allowed to see would misdescribe why the screen is
  /// empty.
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 44, color: theme.colorScheme.onSurfaceVariant),
            const SizedBox(height: 14),
            Text(title, style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            if (action != null) ...[const SizedBox(height: 20), action!],
          ],
        ),
      ),
    );
  }
}
