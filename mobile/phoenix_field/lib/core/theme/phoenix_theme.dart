import 'package:flutter/material.dart';

abstract final class PhoenixTheme {
  static const _amber = Color(0xFFF59E0B);
  static const _surface = Color(0xFF0F172A);
  static const _card = Color(0xFF1E293B);

  static ThemeData get dark {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: const ColorScheme.dark(
        primary: _amber,
        secondary: Color(0xFFFBBF24),
        surface: _surface,
        onSurface: Color(0xFFF1F5F9),
        error: Color(0xFFF87171),
      ),
      scaffoldBackgroundColor: _surface,
      cardColor: _card,
      appBarTheme: const AppBarTheme(
        backgroundColor: _surface,
        foregroundColor: Color(0xFFF1F5F9),
        elevation: 0,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFF334155),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: _amber, width: 1.5),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: _amber,
          foregroundColor: const Color(0xFF0F172A),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: _card,
        indicatorColor: Color(0x33F59E0B),
        labelTextStyle: WidgetStatePropertyAll(TextStyle(fontSize: 12)),
      ),
    );

    return base;
  }
}
