import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/dispute.dart';
import '../models/revision.dart';
import '../repositories/review_repository.dart';
import 'app_providers.dart';

final reviewRepositoryProvider = Provider<ReviewRepository>((ref) {
  return ReviewRepository(ref.watch(apiClientProvider));
});

/// Which half of the queue is on screen: what I proposed, or what I may decide.
class QueueFilter extends Notifier<String> {
  @override
  String build() => 'mine';

  void show(String filter) => state = filter;
}

final queueFilterProvider = NotifierProvider<QueueFilter, String>(QueueFilter.new);

final reviewQueueProvider = FutureProvider.family<ReviewQueue, String>((ref, filter) {
  return ref.watch(reviewRepositoryProvider).changeRequests(filter: filter);
});

final historyProvider = FutureProvider.family<History, String>((ref, ulid) {
  return ref.watch(reviewRepositoryProvider).history(ulid);
});

final disputesProvider = FutureProvider.family<List<Dispute>, String>((ref, ulid) {
  return ref.watch(reviewRepositoryProvider).disputes(ulid);
});

/// Whether to offer the review queue at all.
///
/// Answered by the server rather than inferred from a role name: authority is
/// scoped, and "clan-admin" does not say which clan.
final canReviewProvider = FutureProvider<bool>((ref) async {
  final queue = await ref.watch(reviewRepositoryProvider).changeRequests(filter: 'review');

  return queue.canReview;
});
