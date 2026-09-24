import '../../core/api/api_client.dart';
import '../../core/api/api_constants.dart';
import '../../models/active_session_model.dart';

class TeacherService {
  final ApiClient _api = ApiClient();

  /// Fetches current active timetable session slot and students
  Future<ActiveSessionResponse> getActiveSession() async {
    final response = await _api.get(ApiConstants.teacherActiveSession);
    final data = response.data as Map<String, dynamic>;
    return ActiveSessionResponse.fromJson(data);
  }

  /// Sends fast bulk attendance records to API
  Future<Map<String, dynamic>> submitBulkAttendance({
    required int timetableId,
    String? date,
    required List<StudentSessionItem> students,
  }) async {
    final recordsPayload = students.map((s) => s.toRecordPayload()).toList();

    final response = await _api.post(
      ApiConstants.teacherBulkAttendance,
      data: {
        'timetable_id': timetableId,
        if (date != null) 'date': date,
        'records': recordsPayload,
      },
    );

    return response.data as Map<String, dynamic>;
  }
}
