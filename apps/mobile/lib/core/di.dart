import 'package:get_it/get_it.dart';
import 'package:triosense/core/api/api_client.dart';
import 'package:triosense/core/auth/token_store.dart';
import 'package:triosense/core/cache/hive_boxes.dart';
import 'package:triosense/features/auth/data/auth_repository.dart';
import 'package:triosense/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:triosense/features/locations/data/locations_repository.dart';
import 'package:triosense/core/notifications/fcm_service.dart';
import 'package:triosense/features/locations/data/location_state_repository.dart';
import 'package:triosense/features/locations/presentation/bloc/location_state_bloc.dart';

final GetIt sl = GetIt.instance;

Future<void> setupDependencies() async {
  await HiveBoxes.init();

  sl
    ..registerLazySingleton<TokenStore>(TokenStore.new)
    ..registerLazySingleton<ApiClient>(() => ApiClient(sl<TokenStore>()))
    ..registerLazySingleton<AuthRepository>(
      () => AuthRepository(sl<ApiClient>(), sl<TokenStore>()),
    )
    ..registerLazySingleton<LocationsRepository>(
      () => LocationsRepository(sl<ApiClient>()),
    )
    ..registerLazySingleton<LocationStateRepository>(
      () => LocationStateRepository(sl<ApiClient>()),
    )
    ..registerLazySingleton<FcmService>(FcmService.new)
    ..registerFactory<AuthBloc>(() => AuthBloc(sl<AuthRepository>()))
    ..registerFactory<LocationsBloc>(() => LocationsBloc(sl<LocationsRepository>()))
    ..registerFactory<LocationStateBloc>(
      () => LocationStateBloc(sl<LocationStateRepository>()),
    );
}
