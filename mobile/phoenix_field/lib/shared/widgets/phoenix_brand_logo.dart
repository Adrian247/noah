import 'package:flutter/material.dart';
import 'package:phoenix_field/shared/brand/brand_assets.dart';

enum PhoenixBrandLogoSize { sm, md, lg }

class PhoenixBrandLogo extends StatefulWidget {
  const PhoenixBrandLogo({
    super.key,
    this.size = PhoenixBrandLogoSize.md,
    this.animated = false,
  });

  final PhoenixBrandLogoSize size;
  final bool animated;

  @override
  State<PhoenixBrandLogo> createState() => _PhoenixBrandLogoState();
}

class _PhoenixBrandLogoState extends State<PhoenixBrandLogo>
    with SingleTickerProviderStateMixin {
  AnimationController? _idleController;

  double get _dimension => switch (widget.size) {
        PhoenixBrandLogoSize.sm => 40,
        PhoenixBrandLogoSize.md => 44,
        PhoenixBrandLogoSize.lg => 52,
      };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncAnimation();
  }

  @override
  void didUpdateWidget(covariant PhoenixBrandLogo oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.animated != widget.animated) {
      _syncAnimation();
    }
  }

  void _syncAnimation() {
    final reduceMotion = MediaQuery.of(context).disableAnimations;
    if (widget.animated && !reduceMotion) {
      _idleController ??= AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 3500),
      )..repeat(reverse: true);
    } else {
      _idleController?.dispose();
      _idleController = null;
    }
  }

  @override
  void dispose() {
    _idleController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final logo = Image.asset(
      BrandAssets.phoenixLogo,
      width: _dimension,
      height: _dimension,
      fit: BoxFit.contain,
      semanticLabel: 'Phoenix',
    );

    if (_idleController == null) {
      return _withGlow(logo, 0.55);
    }

    return AnimatedBuilder(
      animation: _idleController!,
      builder: (context, child) {
        final glow = 0.35 + (_idleController!.value * 0.2);
        return _withGlow(child!, glow);
      },
      child: logo,
    );
  }

  Widget _withGlow(Widget child, double intensity) {
    return DecoratedBox(
      decoration: BoxDecoration(
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFFB923C).withValues(alpha: intensity),
            blurRadius: 14,
            spreadRadius: 1,
          ),
        ],
      ),
      child: child,
    );
  }
}

class PhoenixBrandWordmark extends StatelessWidget {
  const PhoenixBrandWordmark({
    super.key,
    this.title = 'Phoenix Campo',
    this.subtitle = 'Pyro Systems',
    this.compact = false,
  });

  final String title;
  final String subtitle;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          title,
          style: (compact ? theme.textTheme.titleLarge : theme.textTheme.headlineMedium)
              ?.copyWith(
            fontWeight: FontWeight.w800,
            letterSpacing: 0.04,
            color: const Color(0xFFFFF7ED),
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 4),
        Text(
          subtitle.toUpperCase(),
          style: theme.textTheme.labelSmall?.copyWith(
            fontWeight: FontWeight.w600,
            letterSpacing: 2.8,
            color: const Color(0xFFFDBA74).withValues(alpha: 0.9),
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}
