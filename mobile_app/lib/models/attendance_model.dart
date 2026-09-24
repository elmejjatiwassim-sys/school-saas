class AttendanceRecordModel {
  final int id;
  final String date;
  final String status; // 'present', 'absent', 'late', 'excused'
  final String? lateArrivalTime;
  final String? justificationStatus; // 'pending', 'approved', 'rejected'
  final String? guardianJustificationNote;
  final String? guardianJustificationAttachment;
  final String? remarks;
  final int? timetableId;
  final String? subjectName;
  final String? teacherName;
  final String? classroomName;
  final String? sessionTime;

  AttendanceRecordModel({
    required this.id,
    required this.date,
    required this.status,
    this.lateArrivalTime,
    this.justificationStatus,
    this.guardianJustificationNote,
    this.guardianJustificationAttachment,
    this.remarks,
    this.timetableId,
    this.subjectName,
    this.teacherName,
    this.classroomName,
    this.sessionTime,
  });

  bool get isAbsent => status == 'absent';
  bool get isLate => status == 'late';
  bool get isPresent => status == 'present';
  bool get isExcused => status == 'excused';

  bool get hasJustification =>
      guardianJustificationNote != null && guardianJustificationNote!.isNotEmpty;
  bool get isJustificationPending => justificationStatus == 'pending';
  bool get isJustificationApproved => justificationStatus == 'approved';
  bool get isJustificationRejected => justificationStatus == 'rejected';

  // Can guardian submit justification? Absent and not approved yet
  bool get canSubmitJustification =>
      isAbsent && (justificationStatus == null || justificationStatus == 'pending' || justificationStatus == 'rejected');

  factory AttendanceRecordModel.fromJson(Map<String, dynamic> json) {
    String? subject;
    if (json['subject'] != null && json['subject'] is Map<String, dynamic>) {
      subject = json['subject']['name'] as String?;
    }

    String? teacher;
    if (json['teacher'] != null && json['teacher'] is Map<String, dynamic>) {
      teacher = json['teacher']['name'] as String?;
    }

    return AttendanceRecordModel(
      id: json['id'] as int? ?? 0,
      date: json['date'] as String? ?? '',
      status: json['status'] as String? ?? 'present',
      lateArrivalTime: json['late_arrival_time'] as String?,
      justificationStatus: json['justification_status'] as String?,
      guardianJustificationNote: json['guardian_justification_note'] as String?,
      guardianJustificationAttachment: json['guardian_justification_attachment'] as String?,
      remarks: json['remarks'] as String?,
      timetableId: json['timetable_id'] as int?,
      subjectName: subject,
      teacherName: teacher,
      classroomName: json['classroom'] as String?,
      sessionTime: json['session_time'] as String?,
    );
  }
}

class AttendancePaginationModel {
  final List<AttendanceRecordModel> records;
  final int currentPage;
  final int lastPage;
  final int total;
  final int perPage;

  AttendancePaginationModel({
    required this.records,
    this.currentPage = 1,
    this.lastPage = 1,
    this.total = 0,
    this.perPage = 15,
  });

  bool get hasMore => currentPage < lastPage;

  factory AttendancePaginationModel.fromJson(Map<String, dynamic> json) {
    final rawList = json['data'] as List<dynamic>? ?? [];
    final records = rawList
        .whereType<Map<String, dynamic>>()
        .map((item) => AttendanceRecordModel.fromJson(item))
        .toList();

    final meta = json['meta'] as Map<String, dynamic>? ?? {};

    return AttendancePaginationModel(
      records: records,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      total: meta['total'] as int? ?? records.length,
      perPage: meta['per_page'] as int? ?? 15,
    );
  }
}
