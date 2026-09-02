import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/network/api_client.dart';
import '../core/network/auth_interceptor.dart';
import '../database/app_database.dart';
import '../database/tree_cache_dao.dart';
import '../repositories/auth_repository.dart';
import '../repositories/sync_queue_repository.dart';
import '../services/secure_storage_service.dart';

/// Wiring. Deliberately explicit rather than generated: the graph is small, and
/// being able to read it top to bottom is worth more than the boilerplate saved.
final secureStorageProvider = Provider<SecureStorageService>((ref) => SecureStorageService());

final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final database = AppDatabase();
  ref.onDispose(database.close);
  return database;
});

/// Raised when the server rejects the token, so the router can send the person
/// back to sign-in exactly once rather than every failing request doing it.
///
/// A counter rather than a boolean: listeners react to it *increasing*, so a
/// second rejection after a failed recovery is not swallowed as "already true".
class UnauthenticatedSignal extends Notifier<int> {
  @override
  int build() => 0;

  void raise() => state = state + 1;
}

final unauthenticatedSignalProvider =
    NotifierProvider<UnauthenticatedSignal, int>(UnauthenticatedSignal.new);

final authInterceptorProvider = Provider<AuthInterceptor>((ref) {
  return AuthInterceptor(
    ref.watch(secureStorageProvider),
    onUnauthenticated: () => ref.read(unauthenticatedSignalProvider.notifier).raise(),
  );
});

final dioProvider = Provider<Dio>((ref) {
  final dio = ApiClient.buildDio();
  dio.interceptors.add(ref.watch(authInterceptorProvider));
  return dio;
});

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    dio: ref.watch(dioProvider),
    authInterceptor: ref.watch(authInterceptorProvider),
  );
});

final treeCacheProvider = Provider<TreeCacheDao>((ref) {
  return TreeCacheDao(ref.watch(appDatabaseProvider));
});

final syncQueueProvider = Provider<SyncQueueRepository>((ref) {
  return SyncQueueRepository(
    ref.watch(appDatabaseProvider),
    ref.watch(apiClientProvider),
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    api: ref.watch(apiClientProvider),
    storage: ref.watch(secureStorageProvider),
    database: ref.watch(appDatabaseProvider),
  );
});
