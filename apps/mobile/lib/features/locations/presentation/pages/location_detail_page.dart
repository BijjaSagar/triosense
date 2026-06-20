import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:triosense/core/di.dart';
import 'package:triosense/features/locations/domain/entities/location_live_state.dart';
import 'package:triosense/features/locations/presentation/bloc/location_state_bloc.dart';
import 'package:triosense/features/locations/presentation/bloc/location_state_event.dart';
import 'package:triosense/features/locations/presentation/bloc/location_state_state.dart';

class LocationDetailPage extends StatefulWidget {
  const LocationDetailPage({super.key, required this.locationId});

  final int locationId;

  @override
  State<LocationDetailPage> createState() => _LocationDetailPageState();
}

class _LocationDetailPageState extends State<LocationDetailPage> {
  final _reasonController = TextEditingController();

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => sl<LocationStateBloc>()
        ..add(LoadLocationStateRequested(widget.locationId)),
      child: Scaffold(
        appBar: AppBar(title: const Text('Live counter')),
        body: BlocBuilder<LocationStateBloc, LocationStateScreenState>(
          builder: (context, state) {
            return switch (state) {
              LocationStateInitial() || LocationStateLoading() =>
                const Center(child: CircularProgressIndicator()),
              LocationStateError(:final message) => Center(child: Text(message)),
              LocationStateLoaded(:final data, :final isStale) =>
                _LoadedView(
                  data: data,
                  isStale: isStale,
                  reasonController: _reasonController,
                  locationId: widget.locationId,
                ),
              OverrideApplied(:final data) =>
                _LoadedView(
                  data: data,
                  isStale: data.isStale(),
                  reasonController: _reasonController,
                  locationId: widget.locationId,
                ),
            };
          },
        ),
      ),
    );
  }
}

class _LoadedView extends StatelessWidget {
  const _LoadedView({
    required this.data,
    required this.isStale,
    required this.reasonController,
    required this.locationId,
  });

  final LocationLiveState data;
  final bool isStale;
  final TextEditingController reasonController;
  final int locationId;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (isStale)
          Container(
            padding: const EdgeInsets.all(12),
            color: Colors.amber.shade100,
            child: const Text('Stale data — last update > 60s ago'),
          ),
        if (data.mode == 'shadow')
          Container(
            margin: const EdgeInsets.only(top: 8),
            padding: const EdgeInsets.all(12),
            color: Colors.orange.shade100,
            child: const Text('SHADOW MODE — predictions only'),
          ),
        const SizedBox(height: 16),
        Text(
          data.locationName,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        Text('${data.shortCode} · ${data.status}'),
        const SizedBox(height: 24),
        Text(
          '${data.tokensRemaining}',
          style: Theme.of(context).textTheme.displayLarge,
        ),
        const Text('tokens remaining'),
        const SizedBox(height: 8),
        Text('Queue ${data.queueHead} → ${data.queueTail}'),
        if (data.cutoffPosition != null)
          Text('Cutoff #${data.cutoffPosition}'),
        const SizedBox(height: 24),
        TextField(
          controller: reasonController,
          decoration: const InputDecoration(
            labelText: 'Override reason',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          children: [
            OutlinedButton(
              onPressed: () => _override(context, 'force_open'),
              child: const Text('Force open'),
            ),
            OutlinedButton(
              onPressed: () => _override(context, 'force_close'),
              child: const Text('Force close'),
            ),
          ],
        ),
      ],
    );
  }

  void _override(BuildContext context, String action) {
    final reason = reasonController.text.trim();
    if (reason.length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Reason must be at least 5 characters')),
      );
      return;
    }

    context.read<LocationStateBloc>().add(
          ApplyOverrideRequested(action: action, reason: reason),
        );
  }
}
