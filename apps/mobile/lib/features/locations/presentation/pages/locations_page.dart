import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:triosense/core/di.dart';
import 'package:triosense/features/locations/domain/entities/location_summary.dart';
import 'package:triosense/features/locations/presentation/bloc/locations_bloc.dart';
import 'package:triosense/features/locations/presentation/bloc/locations_event.dart';
import 'package:triosense/features/locations/presentation/bloc/locations_state.dart';

class LocationsPage extends StatelessWidget {
  const LocationsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => sl<LocationsBloc>()..add(const LoadLocationsRequested()),
      child: Scaffold(
        appBar: AppBar(title: const Text('TTD Counters')),
        body: BlocBuilder<LocationsBloc, LocationsState>(
          builder: (context, state) {
            return switch (state) {
              LocationsInitial() || LocationsLoading() => const Center(
                  child: CircularProgressIndicator(),
                ),
              LocationsError(:final message) => Center(child: Text(message)),
              LocationsLoaded(:final locations) => _LocationsGrid(
                  locations: locations,
                ),
            };
          },
        ),
      ),
    );
  }
}

class _LocationsGrid extends StatelessWidget {
  const _LocationsGrid({required this.locations});

  final List<LocationSummary> locations;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: locations.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final location = locations[index];
        return Card(
          child: ListTile(
            title: Text(
              location.name,
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: Text('${location.shortCode} · ${location.mode}'),
            trailing: Chip(
              label: Text(location.status),
            ),
            onTap: () => context.push('/locations/${location.locationId}'),
          ),
        );
      },
    );
  }
}
