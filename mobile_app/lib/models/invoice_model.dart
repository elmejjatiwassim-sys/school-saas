class InvoiceItemModel {
  final int id;
  final String? description;
  final String? feeType;
  final double amount;
  final int quantity;
  final double subtotal;

  InvoiceItemModel({
    required this.id,
    this.description,
    this.feeType,
    this.amount = 0.0,
    this.quantity = 1,
    this.subtotal = 0.0,
  });

  factory InvoiceItemModel.fromJson(Map<String, dynamic> json) {
    return InvoiceItemModel(
      id: json['id'] as int? ?? 0,
      description: json['description'] as String?,
      feeType: json['fee_type'] as String?,
      amount: (json['amount'] as num?)?.toDouble() ?? 0.0,
      quantity: json['quantity'] as int? ?? 1,
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class InvoiceModel {
  final int id;
  final String invoiceNumber;
  final String title;
  final String invoiceType;
  final int? billingMonth;
  final int? billingYear;
  final double totalAmount;
  final double paidAmount;
  final double remainingAmount;
  final String? dueDate;
  final String status;
  final bool isPaid;
  final String? receiptNumber;
  final String? receiptDownloadUrl;
  final List<InvoiceItemModel> items;

  InvoiceModel({
    required this.id,
    required this.invoiceNumber,
    required this.title,
    required this.invoiceType,
    this.billingMonth,
    this.billingYear,
    required this.totalAmount,
    required this.paidAmount,
    required this.remainingAmount,
    this.dueDate,
    required this.status,
    required this.isPaid,
    this.receiptNumber,
    this.receiptDownloadUrl,
    this.items = const [],
  });

  String get billingPeriod {
    if (billingMonth != null && billingYear != null) {
      return '$billingMonth / $billingYear';
    }
    return dueDate ?? '';
  }

  factory InvoiceModel.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'] as List<dynamic>? ?? [];
    final itemsList = rawItems
        .whereType<Map<String, dynamic>>()
        .map((i) => InvoiceItemModel.fromJson(i))
        .toList();

    return InvoiceModel(
      id: json['id'] as int? ?? 0,
      invoiceNumber: json['invoice_number'] as String? ?? '',
      title: json['title'] as String? ?? 'فاتورة مدرسية',
      invoiceType: json['invoice_type'] as String? ?? 'monthly_fee',
      billingMonth: json['billing_month'] as int?,
      billingYear: json['billing_year'] as int?,
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0.0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0.0,
      remainingAmount: (json['remaining_amount'] as num?)?.toDouble() ?? 0.0,
      dueDate: json['due_date'] as String?,
      status: json['status'] as String? ?? 'unpaid',
      isPaid: json['is_paid'] as bool? ?? false,
      receiptNumber: json['receipt_number'] as String?,
      receiptDownloadUrl: json['receipt_download_url'] as String?,
      items: itemsList,
    );
  }
}

class InvoicesSummaryModel {
  final int totalInvoices;
  final int paidCount;
  final int pendingCount;
  final double totalAmount;
  final double totalPaid;
  final double totalPending;

  InvoicesSummaryModel({
    this.totalInvoices = 0,
    this.paidCount = 0,
    this.pendingCount = 0,
    this.totalAmount = 0.0,
    this.totalPaid = 0.0,
    this.totalPending = 0.0,
  });

  factory InvoicesSummaryModel.fromJson(Map<String, dynamic> json) {
    return InvoicesSummaryModel(
      totalInvoices: json['total_invoices'] as int? ?? 0,
      paidCount: json['paid_count'] as int? ?? 0,
      pendingCount: json['pending_count'] as int? ?? 0,
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0.0,
      totalPaid: (json['total_paid'] as num?)?.toDouble() ?? 0.0,
      totalPending: (json['total_pending'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class GuardianInvoicesResponse {
  final InvoicesSummaryModel summary;
  final List<InvoiceModel> paidInvoices;
  final List<InvoiceModel> pendingInvoices;
  final List<InvoiceModel> allInvoices;

  GuardianInvoicesResponse({
    required this.summary,
    required this.paidInvoices,
    required this.pendingInvoices,
    required this.allInvoices,
  });

  factory GuardianInvoicesResponse.fromJson(Map<String, dynamic> json) {
    final summaryObj = json['summary'] != null && json['summary'] is Map<String, dynamic>
        ? InvoicesSummaryModel.fromJson(json['summary'] as Map<String, dynamic>)
        : InvoicesSummaryModel();

    final rawPaid = json['paid_invoices'] as List<dynamic>? ?? [];
    final paid = rawPaid
        .whereType<Map<String, dynamic>>()
        .map((i) => InvoiceModel.fromJson(i))
        .toList();

    final rawPending = json['pending_invoices'] as List<dynamic>? ?? [];
    final pending = rawPending
        .whereType<Map<String, dynamic>>()
        .map((i) => InvoiceModel.fromJson(i))
        .toList();

    final rawAll = json['invoices'] as List<dynamic>? ?? [];
    final all = rawAll
        .whereType<Map<String, dynamic>>()
        .map((i) => InvoiceModel.fromJson(i))
        .toList();

    return GuardianInvoicesResponse(
      summary: summaryObj,
      paidInvoices: paid,
      pendingInvoices: pending,
      allInvoices: all,
    );
  }
}
