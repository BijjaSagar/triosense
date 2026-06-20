import 'package:go_router/go_router.dart';
import 'package:triosense/core/auth/token_store.dart';
import 'package:triosense/core/di.dart';
import 'package:triosense/features/auth/presentation/pages/login_page.dart';
import 'package:triosense/features/locations/presentation/pages/location_detail_page.dart';
import 'package:triosense/features/locations/presentation/pages/locations_page.dart';

final GoRouter appRouter = GoRouter(
  initialLocation: '/login',
  redirect: (context, state) {
    final tokenStore = sl<TokenStore>();
    final loggedIn = tokenStore.isAuthenticated;
    final onLogin = state.matchedLocation == '/login';

    if (!loggedIn && !onLogin) return '/login';
    if (loggedIn && onLogin) return '/locations';
    return null;
  },
  routes: [
    GoRoute(
      path: '/login',
      builder: (context, state) => const LoginPage(),
    ),
    GoRoute(
      path: '/locations',
      builder: (context, state) => const LocationsPage(),
    ),
    GoRoute(
      path: '/locations/:id',
      builder: (context, state) {
        final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
        return LocationDetailPage(locationId: id);
      },
    ),
  ],
);
