import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:phoenix_field/shared/brand/brand_assets.dart';

/// Overlay de entrada al sistema (equivalente a `PhoenixSystemEnter.vue` en web).
class PhoenixSystemEnterOverlay extends StatefulWidget {
  const PhoenixSystemEnterOverlay({
    super.key,
    required this.message,
  });

  final String message;

  @override
  State<PhoenixSystemEnterOverlay> createState() => _PhoenixSystemEnterOverlayState();
}

class _PhoenixSystemEnterOverlayState extends State<PhoenixSystemEnterOverlay>
    with TickerProviderStateMixin {
  static const _enterDuration = Duration(milliseconds: 1150);
  static const _glowDuration = Duration(milliseconds: 2200);
  static const _waveDuration = Duration(milliseconds: 5500);
  static const _beamDuration = Duration(milliseconds: 1150);

  late final AnimationController _logoController;
  late final AnimationController _glowController;
  late final AnimationController _waveController;
  late final AnimationController _beamController;
  late final AnimationController _textController;

  late final Animation<double> _logoScale;
  late final Animation<double> _logoOpacity;

  @override
  void initState() {
    super.initState();
    _logoController = AnimationController(vsync: this, duration: _enterDuration);
    _glowController = AnimationController(vsync: this, duration: _glowDuration)
      ..repeat(reverse: true);
    _waveController = AnimationController(vsync: this, duration: _waveDuration)
      ..repeat();
    _beamController = AnimationController(vsync: this, duration: _beamDuration)
      ..repeat();
    _textController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 720),
    );

    final logoCurve = CurvedAnimation(
      parent: _logoController,
      curve: const Cubic(0.22, 1, 0.36, 1),
    );
    _logoScale = Tween<double>(begin: 0.9, end: 1).animate(logoCurve);
    _logoOpacity = Tween<double>(begin: 0, end: 1).animate(logoCurve);

    _logoController.forward();
    _textController.forward();
  }

  @override
  void dispose() {
    _logoController.dispose();
    _glowController.dispose();
    _waveController.dispose();
    _beamController.dispose();
    _textController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MediaQuery.of(context).disableAnimations;

    return Material(
      color: const Color(0xEB07080C),
      child: Stack(
        alignment: Alignment.center,
        children: [
          if (!reduceMotion) ...[
            Positioned.fill(child: _GlowPulse(animation: _glowController)),
            Positioned.fill(child: _BeamSweep(animation: _beamController)),
            Positioned.fill(child: _WaveField(animation: _waveController)),
          ],
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (reduceMotion)
                Image.asset(
                  BrandAssets.phoenixLogo,
                  width: 120,
                  height: 120,
                  fit: BoxFit.contain,
                  semanticLabel: 'Phoenix',
                )
              else
                AnimatedBuilder(
                  animation: _logoController,
                  builder: (context, child) {
                    return Opacity(
                      opacity: _logoOpacity.value,
                      child: Transform.scale(
                        scale: _logoScale.value,
                        child: child,
                      ),
                    );
                  },
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFFFB923C).withValues(alpha: 0.75),
                          blurRadius: 24,
                          spreadRadius: 2,
                        ),
                      ],
                    ),
                    child: Image.asset(
                      BrandAssets.phoenixLogo,
                      width: 120,
                      height: 120,
                      fit: BoxFit.contain,
                      semanticLabel: 'Phoenix',
                    ),
                  ),
                ),
              const SizedBox(height: 14),
              _FadeSlideIn(
                animation: _textController,
                delay: 0.12,
                child: const Text(
                  'Phoenix',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 2.2,
                    color: Color(0xFFFFF7ED),
                  ),
                ),
              ),
              const SizedBox(height: 4),
              _FadeSlideIn(
                animation: _textController,
                delay: 0.22,
                child: Text(
                  'PYRO SYSTEMS',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 3.2,
                    color: const Color(0xFFFDBA74).withValues(alpha: 0.9),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              _FadeSlideIn(
                animation: _textController,
                delay: 0.32,
                child: Text(
                  widget.message,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    letterSpacing: 0.5,
                    color: const Color(0xFFE2E8F0).withValues(alpha: 0.92),
                  ),
                  textAlign: TextAlign.center,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FadeSlideIn extends StatelessWidget {
  const _FadeSlideIn({
    required this.animation,
    required this.delay,
    required this.child,
  });

  final Animation<double> animation;
  final double delay;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, child) {
        final t = ((animation.value - delay) / (1 - delay)).clamp(0.0, 1.0);
        return Opacity(
          opacity: t,
          child: Transform.translate(
            offset: Offset(0, 12 * (1 - t)),
            child: child,
          ),
        );
      },
      child: child,
    );
  }
}

class _GlowPulse extends StatelessWidget {
  const _GlowPulse({required this.animation});

  final Animation<double> animation;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, _) {
        final opacity = 0.42 + (animation.value * 0.46);
        return Center(
          child: Container(
            width: 176,
            height: 176,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(
                colors: [
                  const Color(0xFFF59E0B).withValues(alpha: opacity * 0.45),
                  const Color(0xFFEF4444).withValues(alpha: opacity * 0.12),
                  Colors.transparent,
                ],
                stops: const [0, 0.55, 1],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _BeamSweep extends StatelessWidget {
  const _BeamSweep({required this.animation});

  final Animation<double> animation;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return AnimatedBuilder(
          animation: animation,
          builder: (context, _) {
            final top = constraints.maxHeight * (-0.35 + (animation.value * 1.5));
            return Stack(
              children: [
                Positioned(
                  left: constraints.maxWidth / 2 - 2,
                  top: top,
                  child: Container(
                    width: 4,
                    height: constraints.maxHeight * 0.38,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(999),
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.transparent,
                          const Color(0xFFFBBF24).withValues(alpha: 0.15),
                          const Color(0xFFF59E0B).withValues(alpha: 0.85),
                          const Color(0xFFEF4444).withValues(alpha: 0.35),
                          Colors.transparent,
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        );
      },
    );
  }
}

class _WaveField extends StatelessWidget {
  const _WaveField({required this.animation});

  final Animation<double> animation;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, _) {
        return Stack(
          alignment: Alignment.center,
          children: [
            _EnterWave(progress: animation.value),
            _EnterWave(progress: (animation.value + 0.5) % 1),
          ],
        );
      },
    );
  }
}

class _EnterWave extends StatelessWidget {
  const _EnterWave({required this.progress});

  final double progress;

  @override
  Widget build(BuildContext context) {
    final scale = 0.7 + (progress * 1.9);
    final opacity = progress < 0.75
        ? 0.28 - (progress * 0.2)
        : 0.08 * (1 - ((progress - 0.75) / 0.25));

    return Transform.scale(
      scale: scale,
      child: Opacity(
        opacity: math.max(0, opacity),
        child: Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: const Color(0xFFF59E0B).withValues(alpha: 0.35),
              width: 1.5,
            ),
          ),
        ),
      ),
    );
  }
}
