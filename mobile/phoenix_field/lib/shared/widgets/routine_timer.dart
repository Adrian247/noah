import 'dart:async';

import 'package:flutter/material.dart';

class RoutineTimer extends StatefulWidget {
  const RoutineTimer({
    super.key,
    required this.onMinutesChanged,
    this.initialMinutes,
  });

  final void Function(int minutes) onMinutesChanged;
  final int? initialMinutes;

  @override
  State<RoutineTimer> createState() => _RoutineTimerState();
}

class _RoutineTimerState extends State<RoutineTimer> {
  Timer? _timer;
  Duration _elapsed = Duration.zero;
  bool _running = false;

  @override
  void initState() {
    super.initState();
    if (widget.initialMinutes != null && widget.initialMinutes! > 0) {
      _elapsed = Duration(minutes: widget.initialMinutes!);
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _toggle() {
    if (_running) {
      _timer?.cancel();
      setState(() => _running = false);
      return;
    }

    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      setState(() => _elapsed += const Duration(seconds: 1));
      widget.onMinutesChanged(_elapsed.inMinutes);
    });
    setState(() => _running = true);
  }

  void _reset() {
    _timer?.cancel();
    setState(() {
      _running = false;
      _elapsed = Duration.zero;
    });
    widget.onMinutesChanged(0);
  }

  String _format(Duration d) {
    final h = d.inHours;
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    if (h > 0) {
      return '$h:$m:$s';
    }
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Tiempo en sitio', style: Theme.of(context).textTheme.titleSmall),
                  const SizedBox(height: 4),
                  Text(
                    _format(_elapsed),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          color: Theme.of(context).colorScheme.primary,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                  ),
                ],
              ),
            ),
            IconButton(
              onPressed: _reset,
              icon: const Icon(Icons.replay),
              tooltip: 'Reiniciar',
            ),
            FilledButton.icon(
              onPressed: _toggle,
              icon: Icon(_running ? Icons.pause : Icons.play_arrow),
              label: Text(_running ? 'Pausar' : 'Iniciar'),
            ),
          ],
        ),
      ),
    );
  }
}
