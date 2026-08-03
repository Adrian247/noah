import 'dart:io';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/data/session/session_store.dart';

@pragma('vm:entry-point')
Future<void> phoenixFirebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

class PushNotificationService {
  PushNotificationService({
    required Dio dio,
    required SessionStore session,
  })  : _dio = dio,
        _session = session;

  final Dio _dio;
  final SessionStore _session;
  final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();

  bool _ready = false;
  bool get isReady => _ready;

  Future<void> initialize() async {
    if (kIsWeb) {
      return;
    }

    try {
      await Firebase.initializeApp();
    } catch (e, st) {
      debugPrint('Push: Firebase.initializeApp falló (¿google-services real?): $e\n$st');
      return;
    }

    FirebaseMessaging.onBackgroundMessage(phoenixFirebaseMessagingBackgroundHandler);

    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings();
    await _local.initialize(
      const InitializationSettings(android: androidInit, iOS: iosInit),
    );

    const channel = AndroidNotificationChannel(
      'phoenix_routines',
      'Rutinas Phoenix',
      description: 'Avisos de asignación y flujo de rutinas',
      importance: Importance.high,
    );
    await _local
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(alert: true, badge: true, sound: true);
    await messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );

    FirebaseMessaging.onMessage.listen(_showForegroundNotification);
    _ready = true;
  }

  Future<void> registerIfAuthenticated() async {
    if (!_ready || !_session.isAuthenticated) {
      return;
    }

    final deviceId = _session.deviceId;
    if (deviceId == null || deviceId.isEmpty) {
      return;
    }

    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) {
        debugPrint('Push: sin token FCM (proyecto Firebase placeholder o sin red).');
        return;
      }

      String? appVersion;
      try {
        final info = await PackageInfo.fromPlatform();
        appVersion = info.version;
      } catch (_) {}

      await _dio.post<Map<String, dynamic>>(
        '/mobile/device-tokens',
        data: {
          'device_id': deviceId,
          'token': token,
          'platform': Platform.isIOS ? 'ios' : 'android',
          if (appVersion != null) 'app_version': appVersion,
        },
      );

      FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
        if (!_session.isAuthenticated) {
          return;
        }
        try {
          await _dio.post<Map<String, dynamic>>(
            '/mobile/device-tokens',
            data: {
              'device_id': deviceId,
              'token': newToken,
              'platform': Platform.isIOS ? 'ios' : 'android',
            },
          );
        } catch (e) {
          debugPrint('Push: refresh token falló: $e');
        }
      });
    } catch (e) {
      debugPrint('Push: registro de token falló: $e');
    }
  }

  Future<void> unregister() async {
    if (!_session.isAuthenticated) {
      return;
    }
    final deviceId = _session.deviceId;
    if (deviceId == null) {
      return;
    }
    try {
      await _dio.delete<void>(
        '/mobile/device-tokens',
        data: {'device_id': deviceId},
      );
    } catch (_) {}
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    final title = notification?.title ?? message.data['title']?.toString() ?? 'Phoenix';
    final body = notification?.body ?? message.data['body']?.toString() ?? '';

    await _local.show(
      message.hashCode,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'phoenix_routines',
          'Rutinas Phoenix',
          channelDescription: 'Avisos de asignación y flujo de rutinas',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(),
      ),
      payload: message.data['routine_id']?.toString(),
    );
  }
}

final pushNotificationServiceProvider = Provider<PushNotificationService>((ref) {
  return PushNotificationService(
    dio: ref.watch(dioProvider),
    session: ref.watch(sessionStoreProvider),
  );
});
