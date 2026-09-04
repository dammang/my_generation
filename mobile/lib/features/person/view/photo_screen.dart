import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../models/media_item.dart';

/// One photograph, full size.
///
/// Black ground and a pinch-to-zoom viewer: a family photograph is usually
/// looked at closely, and a scanned print of somebody's grandparents is worth
/// being able to enlarge.
class PhotoScreen extends StatelessWidget {
  const PhotoScreen({super.key, required this.item});

  final MediaItem item;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(item.caption ?? 'Photograph'),
      ),
      body: Column(
        children: [
          Expanded(
            child: InteractiveViewer(
              maxScale: 5,
              child: Center(
                child: CachedNetworkImage(
                  imageUrl: item.url,
                  fit: BoxFit.contain,
                  placeholder: (context, _) => const Center(child: CircularProgressIndicator()),
                  errorWidget: (context, _, _) => const Padding(
                    padding: EdgeInsets.all(32),
                    child: Text(
                      'This picture could not be loaded. Its link may have '
                      'expired — go back and open it again.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white70),
                    ),
                  ),
                ),
              ),
            ),
          ),
          if (item.uploadedBy != null || item.takenAt != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
              child: Text(
                [
                  if (item.takenAt != null) item.takenAt!,
                  if (item.uploadedBy != null) 'added by ${item.uploadedBy}',
                ].join(' · '),
                style: const TextStyle(color: Colors.white60, fontSize: 12),
              ),
            ),
        ],
      ),
    );
  }
}
