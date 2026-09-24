class ApiConstants {
  // Base API URL - adjustable for Android Emulator (10.0.2.2), iOS Simulator (localhost), or Production
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  // Authentication endpoints
  static const String login = '/auth/login';
  static const String logout = '/auth/logout';
  static const String me = '/auth/me';

  // Teacher endpoints
  static const String teacherActiveSession = '/teacher/active-session';
  static const String teacherBulkAttendance = '/teacher/attendances/bulk-record';

  // Guardian endpoints
  static const String guardianChildren = '/guardian/children';
  static String guardianStudentAttendances(int studentId) =>
      '/guardian/students/$studentId/attendances';
  static String guardianJustifyAttendance(int attendanceId) =>
      '/guardian/attendances/$attendanceId/justify';
  static String guardianStudentInvoices(int studentId) =>
      '/guardian/students/$studentId/invoices';
  static String guardianDownloadReceipt(int paymentId) =>
      '/guardian/receipts/$paymentId/download';
}
