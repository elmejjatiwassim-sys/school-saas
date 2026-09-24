import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../storage/secure_storage_service.dart';
import 'api_constants.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;

  late final Dio dio;
  final SecureStorageService _storage = SecureStorageService();

  // Global navigation key for 401 unauthorized redirect
  static final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  // Optional unauthorized stream/callback
  final StreamController<void> _unauthorizedController = StreamController<void>.broadcast();
  Stream<void> get onUnauthorized => _unauthorizedController.stream;

  ApiClient._internal() {
    dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _setupInterceptors();
  }

  void _setupInterceptors() {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException error, handler) async {
          if (error.response?.statusCode == 401) {
            // Global 401 handling: clear storage and notify listeners
            await _storage.clear();
            _unauthorizedController.add(null);

            // Redirect to login screen if navigatorKey is mounted
            if (navigatorKey.currentState != null) {
              navigatorKey.currentState?.pushNamedAndRemoveUntil('/login', (route) => false);
            }
          }
          return handler.next(error);
        },
      ),
    );
  }

  // GET request
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await dio.get<T>(
      path,
      queryParameters: queryParameters,
      options: options,
    );
  }

  // POST request
  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await dio.post<T>(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
    );
  }

  // Download file request
  Future<Response> download(
    String urlPath,
    dynamic savePath, {
    ProgressCallback? onReceiveProgress,
    Options? options,
  }) async {
    return await dio.download(
      urlPath,
      savePath,
      onReceiveProgress: onReceiveProgress,
      options: options,
    );
  }

  // Helper to extract user-friendly error message
  static String formatError(dynamic error) {
    if (error is DioException) {
      if (error.response?.data is Map<String, dynamic>) {
        final data = error.response!.data as Map<String, dynamic>;
        if (data.containsKey('message')) {
          return data['message'].toString();
        }
      }
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout) {
        return 'انتهت مهلة الاتصال بالخادم، يرجى المحاولة لاحقاً (Connection timed out).';
      }
      if (error.type == DioExceptionType.connectionError) {
        return 'تعذر الاتصال بالخادم، يرجى التحقق من اتصال الإنترنت (Network connection error).';
      }
      return error.message ?? 'حدث خطأ غير متوقع أثناء الاتصال بالخادم.';
    }
    return error.toString();
  }
}
