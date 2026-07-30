import 'dart:convert';

import 'package:phoenix_field/core/config/app_config.dart';
import 'package:phoenix_field/core/security/mobile_security_policy.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SessionStore {
  static const _tokenKey = 'phoenix_token';
  static const _companyIdKey = 'phoenix_company_id';
  static const _userJsonKey = 'phoenix_user_json';
  static const _companiesJsonKey = 'phoenix_companies_json';
  static const _deviceIdKey = 'phoenix_device_id';
  static const _apiBaseUrlKey = 'phoenix_api_base_url';

  String? token;
  int? companyId;
  Map<String, dynamic>? user;
  List<Map<String, dynamic>> companies = [];
  String? deviceId;
  String? apiBaseUrl;

  bool get isAuthenticated => token != null && token!.isNotEmpty && companyId != null;

  Map<String, dynamic>? get currentCompany {
    final id = companyId;
    if (id == null) {
      return null;
    }
    for (final company in companies) {
      if (company['id'] == id) {
        return company;
      }
    }
    return null;
  }

  MobileSecurityPolicy get mobilePolicyForCurrentCompany {
    final policy = currentCompany?['mobile_policy'];
    if (policy is Map) {
      return MobileSecurityPolicy.fromMap(Map<String, dynamic>.from(policy));
    }
    return MobileSecurityPolicy.defaults;
  }

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    token = prefs.getString(_tokenKey);
    companyId = prefs.getInt(_companyIdKey);
    deviceId = prefs.getString(_deviceIdKey);
    final savedApi = prefs.getString(_apiBaseUrlKey);
    if (savedApi != null && AppConfig.shouldDiscardPersistedUrl(savedApi)) {
      apiBaseUrl = null;
      await prefs.remove(_apiBaseUrlKey);
    } else {
      apiBaseUrl = savedApi;
    }

    final userJson = prefs.getString(_userJsonKey);
    if (userJson != null) {
      user = jsonDecode(userJson) as Map<String, dynamic>;
    }

    final companiesJson = prefs.getString(_companiesJsonKey);
    if (companiesJson != null) {
      final decoded = jsonDecode(companiesJson) as List<dynamic>;
      companies = decoded.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    }
  }

  Future<void> saveLogin({
    required String token,
    required Map<String, dynamic> user,
    required List<Map<String, dynamic>> companies,
    required int companyId,
    required String deviceId,
  }) async {
    this.token = token;
    this.user = user;
    this.companies = companies;
    this.companyId = companyId;
    this.deviceId = deviceId;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
    await prefs.setInt(_companyIdKey, companyId);
    await prefs.setString(_userJsonKey, jsonEncode(user));
    await prefs.setString(_companiesJsonKey, jsonEncode(companies));
    await prefs.setString(_deviceIdKey, deviceId);
  }

  Future<void> setCompanyId(int id) async {
    companyId = id;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_companyIdKey, id);
  }

  Future<void> setApiBaseUrl(String url) async {
    apiBaseUrl = url;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_apiBaseUrlKey, url);
  }

  Future<void> updateUser(Map<String, dynamic> user) async {
    this.user = user;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_userJsonKey, jsonEncode(user));
  }

  Future<void> updateCompanies(List<Map<String, dynamic>> companies) async {
    this.companies = companies;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_companiesJsonKey, jsonEncode(companies));
  }

  Future<void> updateCurrentCompanyMobilePolicy(Map<String, dynamic> policy) async {
    final id = companyId;
    if (id == null) {
      return;
    }

    companies = companies.map((company) {
      if (company['id'] == id) {
        return {
          ...company,
          'mobile_policy': policy,
        };
      }
      return company;
    }).toList();

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_companiesJsonKey, jsonEncode(companies));
  }

  Future<void> clear() async {
    token = null;
    companyId = null;
    user = null;
    companies = [];

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_companyIdKey);
    await prefs.remove(_userJsonKey);
    await prefs.remove(_companiesJsonKey);
  }
}
