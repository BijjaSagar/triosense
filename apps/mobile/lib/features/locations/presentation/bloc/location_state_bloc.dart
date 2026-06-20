import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:logger/logger.dart';
import 'package:triosense/features/locations/data/location_state_repository.dart';
import 'package:triosense/features/locations/presentation/bloc/location_state_event.dart';
import 'package:triosense/features/locations/presentation/bloc/location_state_state.dart';

class LocationStateBloc extends Bloc<LocationStateEvent, LocationStateScreenState> {
  LocationStateBloc(this._repository) : super(LocationStateInitial()) {
    on<LoadLocationStateRequested>(_onLoad);
    on<ApplyOverrideRequested>(_onOverride);
  }

  final LocationStateRepository _repository;
  final Logger _log = Logger();
  int? _locationId;

  Future<void> _onLoad(
    LoadLocationStateRequested event,
    Emitter<LocationStateScreenState> emit,
  ) async {
    _locationId = event.locationId;
    emit(LocationStateLoading());

    final cached = _repository.readCached(event.locationId);
    if (cached != null) {
      emit(LocationStateLoaded(cached, isStale: cached.isStale()));
    }

    try {
      final state = await _repository.fetchState(event.locationId);
      emit(LocationStateLoaded(state, isStale: state.isStale()));
    } catch (e) {
      _log.e('LocationStateBloc.load_failed', error: e);
      if (cached == null) {
        emit(LocationStateError(e.toString()));
      }
    }
  }

  Future<void> _onOverride(
    ApplyOverrideRequested event,
    Emitter<LocationStateScreenState> emit,
  ) async {
    final locationId = _locationId;
    if (locationId == null) return;

    try {
      await _repository.applyOverride(
        locationId: locationId,
        action: event.action,
        reason: event.reason,
        cutoffPosition: event.cutoffPosition,
      );
      final state = await _repository.fetchState(locationId);
      emit(OverrideApplied(state));
      emit(LocationStateLoaded(state));
    } catch (e) {
      _log.e('LocationStateBloc.override_failed', error: e);
      emit(LocationStateError(e.toString()));
    }
  }
}
