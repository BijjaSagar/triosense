import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:triosense/features/locations/data/locations_repository.dart';
import 'package:triosense/features/locations/presentation/bloc/locations_event.dart';
import 'package:triosense/features/locations/presentation/bloc/locations_state.dart';

class LocationsBloc extends Bloc<LocationsEvent, LocationsState> {
  LocationsBloc(this._repository) : super(const LocationsInitial()) {
    on<LoadLocationsRequested>(_onLoad);
  }

  final LocationsRepository _repository;

  Future<void> _onLoad(
    LoadLocationsRequested event,
    Emitter<LocationsState> emit,
  ) async {
    emit(const LocationsLoading());
    try {
      final locations = await _repository.fetchLocations();
      emit(LocationsLoaded(locations));
    } catch (_) {
      emit(const LocationsError('Could not load locations'));
    }
  }
}
