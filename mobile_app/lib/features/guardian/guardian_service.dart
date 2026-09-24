import 'dart:io';
import 'package:dio/dio.dart';
import '../../core/api/api_client.dart';
import '../../core/api/api_constants.dart';
import '../../core/utils/file_helper.dart';
import '../../models/attendance_model.dart';
import '../../models/invoice_model.dart';
import '../../models/student_model.dart';

class GuardianService {
  final ApiClient _api = ApiClient();

  /// Fetch children list linked to authenticated guardian
  Future<List<ChildModel>> getChildren() async {
    final response = await _api.get(ApiConstants.guardianChildren);
    final data = response.data as Map<String, dynamic>;
    final rawList = (data['children'] ?? data['data']) as List<dynamic>? ?? [];

    return rawList
        .whereType<Map<String, dynamic>>()
        .map((c) => ChildModel.fromJson(c))
        .toList();
  }

  /// Fetch paginated session attendances for a child
  Future<AttendancePaginationModel> getStudentAttendances({
    required int studentId,
    int page = 1,
  }) async {
    final response = await _api.get(
      ApiConstants.guardianStudentAttendances(studentId),
      queryParameters: {'page': page},
    );

    final data = response.data as Map<String, dynamic>;
    return AttendancePaginationModel.fromJson(data);
  }

  /// Submit justification for an absence record
  Future<Map<String, dynamic>> submitJustification({
    required int attendanceId,
    required String reason,
    File? attachment,
  }) async {
    dynamic requestData;

    if (attachment != null) {
      final fileName = attachment.path.split('/').last;
      requestData = FormData.fromMap({
        'reason': reason,
        'note': reason,
        'attachment': await MultipartFile.fromFile(
          attachment.path,
          filename: fileName,
        ),
      });
    } else {
      requestData = {
        'reason': reason,
        'note': reason,
      };
    }

    final response = await _api.post(
      ApiConstants.guardianJustifyAttendance(attendanceId),
      data: requestData,
    );

    return response.data as Map<String, dynamic>;
  }

  /// Fetch student invoices and receipts
  Future<GuardianInvoicesResponse> getStudentInvoices(int studentId) async {
    final response = await _api.get(
      ApiConstants.guardianStudentInvoices(studentId),
    );

    final data = response.data as Map<String, dynamic>;
    return GuardianInvoicesResponse.fromJson(data);
  }

  /// Download official receipt PDF
  Future<File> downloadReceipt({
    required String downloadUrl,
    required String receiptNumber,
    void Function(int received, int total)? onProgress,
  }) async {
    return await FileHelper.downloadReceiptPdf(
      downloadUrl: downloadUrl,
      receiptNumber: receiptNumber,
      onProgress: onProgress,
    );
  }

  /// Share receipt PDF via WhatsApp/Apps
  Future<void> shareReceipt({
    required String filePath,
    required String receiptNumber,
    required String studentName,
  }) async {
    await FileHelper.sharePdfFile(
      filePath: filePath,
      title: 'وصل أداء مدرسي رسمي رقم $receiptNumber للتلميذ $studentName',
      subject: 'وصل الأداء المدرسي - $receiptNumber',
    );
  }
}
