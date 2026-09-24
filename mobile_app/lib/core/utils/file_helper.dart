import 'dart:io';
import 'package:dio/dio.dart';
import 'package:open_file/open_file.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import '../api/api_client.dart';

class FileHelper {
  /// Downloads receipt PDF to application directory and returns the saved file.
  static Future<File> downloadReceiptPdf({
    required String downloadUrl,
    required String receiptNumber,
    void Function(int received, int total)? onProgress,
  }) async {
    final dir = await getApplicationDocumentsDirectory();
    final sanitizedReceipt = receiptNumber.replaceAll(RegExp(r'[^\w\-]'), '_');
    final filePath = '${dir.path}/Receipt_$sanitizedReceipt.pdf';

    final apiClient = ApiClient();
    await apiClient.download(
      downloadUrl,
      filePath,
      options: Options(
        responseType: ResponseType.bytes,
        followRedirects: true,
      ),
      onReceiveProgress: onProgress,
    );

    return File(filePath);
  }

  /// Opens the file using device default PDF viewer.
  static Future<OpenResult> openPdfFile(String filePath) async {
    return await OpenFile.open(filePath);
  }

  /// Shares the PDF via WhatsApp, Email, etc. using share_plus.
  static Future<ShareResult> sharePdfFile({
    required String filePath,
    required String title,
    String? subject,
  }) async {
    final xFile = XFile(filePath, mimeType: 'application/pdf');
    return await Share.shareXFiles(
      [xFile],
      text: title,
      subject: subject,
    );
  }
}
