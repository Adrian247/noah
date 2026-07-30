import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/data/repositories/auth_repository.dart';
import 'package:phoenix_field/features/auth/login_page.dart';
import 'package:phoenix_field/features/profile/profile_page.dart';
import 'package:phoenix_field/features/routines/routine_detail_page.dart';
import 'package:phoenix_field/features/routines/routines_page.dart';
import 'package:phoenix_field/features/shell/app_shell.dart';
import 'package:phoenix_field/features/sync/sync_queue_page.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  ref.watch(authNavigationVersionProvider);
  ref.watch(sessionBootstrapProvider);
  ref.watch(appLockBootstrapProvider);
  final session = ref.read(sessionStoreProvider);

  return GoRouter(
    initialLocation: '/login',
    redirect: (context, state) {
      if (ref.read(sessionBootstrapProvider).isLoading ||
          ref.read(appLockBootstrapProvider).isLoading) {
        return null;
      }

      final loggingIn = state.matchedLocation == '/login';
      final authed = session.isAuthenticated;

      if (!authed && !loggingIn) {
        return '/login';
      }
      if (authed && loggingIn) {
        return '/routines';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginPage(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/routines',
                builder: (context, state) => const RoutinesPage(),
                routes: [
                  GoRoute(
                    path: ':id',
                    builder: (context, state) {
                      final id = int.parse(state.pathParameters['id']!);
                      return RoutineDetailPage(routineId: id);
                    },
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/sync',
                builder: (context, state) => const SyncQueuePage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/profile',
                builder: (context, state) => const ProfilePage(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
});
