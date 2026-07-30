import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Duración mínima alineada con la web (`LOGIN_ENTER_MIN_MS`).
const kSystemEnterMinDuration = Duration(milliseconds: 2600);

class SystemEnterState {
  const SystemEnterState({
    this.active = false,
    this.message = 'Entrando al sistema…',
  });

  final bool active;
  final String message;

  SystemEnterState copyWith({bool? active, String? message}) {
    return SystemEnterState(
      active: active ?? this.active,
      message: message ?? this.message,
    );
  }
}

class SystemEnterController extends StateNotifier<SystemEnterState> {
  SystemEnterController() : super(const SystemEnterState());

  DateTime? _shownAt;

  void show(String message) {
    _shownAt ??= DateTime.now();
    state = SystemEnterState(active: true, message: message);
  }

  void updateMessage(String message) {
    if (!state.active) {
      show(message);
      return;
    }
    state = state.copyWith(message: message);
  }

  Future<void> waitForMinimumDuration() async {
    if (_shownAt == null) {
      return;
    }
    final elapsed = DateTime.now().difference(_shownAt!);
    final remaining = kSystemEnterMinDuration - elapsed;
    if (remaining > Duration.zero) {
      await Future<void>.delayed(remaining);
    }
  }

  void hide() {
    _shownAt = null;
    state = const SystemEnterState();
  }
}

final systemEnterProvider =
    StateNotifierProvider<SystemEnterController, SystemEnterState>(
  (ref) => SystemEnterController(),
);
