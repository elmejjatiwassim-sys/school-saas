<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $payment->receipt_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            color: #1f2937;
        }
        .receipt-container {
            max-width: 750px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .school-title {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 5px 0;
        }
        .school-info {
            font-size: 13px;
            color: #6b7280;
        }
        .receipt-badge {
            text-align: right;
        }
        .badge-title {
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 5px 0;
        }
        .receipt-number {
            font-size: 14px;
            font-family: monospace;
            font-weight: 600;
            color: #374151;
        }
        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
        }
        .card-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: #4b5563;
            margin-top: 0;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .info-label {
            color: #6b7280;
        }
        .info-val {
            font-weight: 500;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 12px 10px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }
        .amount-col {
            text-align: right;
        }
        .total-box {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        .total-table {
            width: 320px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }
        .grand-total {
            font-size: 18px;
            font-weight: 700;
            color: #16a34a;
            border-top: 2px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 5px;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #d1d5db;
        }
        .signature-box {
            width: 200px;
            height: 70px;
            border-bottom: 1px solid #9ca3af;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            padding-top: 50px;
        }
        .btn-print {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .btn-print:hover {
            background-color: #1d4ed8;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div style="max-width: 750px; margin: 0 auto 15px auto; display: flex; gap: 10px; flex-wrap: wrap;" class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print Receipt / طباعة الوصل</button>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn-print" style="background-color: #059669; text-decoration: none; display: inline-flex; align-items: center;" download>📥 Download PDF / تحميل PDF</a>
        <button type="button" class="btn-print" style="background-color: #0d9488;" onclick="shareReceipt()">📤 Share / مشاركة</button>
    </div>

    <div class="receipt-container">
        <div class="header">
            <div>
                <h1 class="school-title">{{ $school->name }}</h1>
                <div class="school-info">
                    @if($school->phone)<span>Tel: {{ $school->phone }}</span>@if($school->email) • <span>Email: {{ $school->email }}</span>@endif
                    @elseif($school->email)<span>Email: {{ $school->email }}</span>@endif
                </div>
            </div>
            <div class="receipt-badge">
                <div class="badge-title">Payment Receipt / وصل أداء</div>
                <div class="receipt-number">{{ $payment->receipt_number }}</div>
                <div class="school-info" style="margin-top: 4px;">Date: {{ $payment->payment_date->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="section-grid">
            <div class="card">
                <div class="card-title">Student Information / بيانات التلميذ</div>
                <div class="info-row">
                    <span class="info-label">Full Name:</span>
                    <span class="info-val">{{ $student->first_name }} {{ $student->last_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Registration No:</span>
                    <span class="info-val">{{ $student->registration_number }}</span>
                </div>
                @if($student->massar_code)
                <div class="info-row">
                    <span class="info-label">Massar Code:</span>
                    <span class="info-val">{{ $student->massar_code }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Classroom:</span>
                    <span class="info-val">{{ $student->classroom?->name }} ({{ $student->classroom?->gradeLevel?->name }})</span>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Guardian & Payment / ولي الأمر والأداء</div>
                <div class="info-row">
                    <span class="info-label">Guardian:</span>
                    <span class="info-val">{{ $student->guardian?->first_name }} {{ $student->guardian?->last_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-val">{{ $student->guardian?->phone }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-val">{{ $payment->payment_method->getLabel() }}</span>
                </div>
                @if($payment->cheque_number)
                <div class="info-row">
                    <span class="info-label">Cheque No / رقم الشيك:</span>
                    <span class="info-val font-mono">{{ $payment->cheque_number }}</span>
                </div>
                @endif
                @if($payment->bank_name)
                <div class="info-row">
                    <span class="info-label">Bank / البنك:</span>
                    <span class="info-val">{{ $payment->bank_name }}</span>
                </div>
                @endif
                @if($payment->notes)
                <div class="info-row">
                    <span class="info-label">Notes:</span>
                    <span class="info-val">{{ $payment->notes }}</span>
                </div>
                @endif
            </div>
        </div>

        @if($invoice->items->isNotEmpty())
        <div style="margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #374151; text-transform: uppercase;">
            Invoice Items / تفاصيل وبنود الفاتورة:
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Item Description / البيان</th>
                    <th style="text-align: center; width: 80px;">Qty / الكمية</th>
                    <th class="amount-col" style="width: 120px;">Unit Price / السعر</th>
                    <th class="amount-col" style="width: 140px;">Subtotal / المجموع</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $idx => $item)
                    <tr>
                        <td style="color: #6b7280; font-size: 12px;">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $item->description }}</strong>
                            @if($item->feeType)
                                <span style="display: block; font-size: 11px; color: #6b7280;">{{ $item->feeType->name }}</span>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td class="amount-col">{{ number_format($item->amount, 2) }} MAD</td>
                        <td class="amount-col" style="font-weight: 600;">{{ number_format($item->subtotal, 2) }} MAD</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <table>
            <thead>
                <tr>
                    <th>Description / البيان</th>
                    <th>Academic Year</th>
                    <th>Invoice No</th>
                    <th class="amount-col">Amount Paid / المبلغ المؤدى</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $invoice->title }}</strong>
                        @if($invoice->billing_month)
                            <br><small style="color: #6b7280;">Cycle Month: {{ $invoice->billing_month }}/{{ $invoice->billing_year }}</small>
                        @endif
                    </td>
                    <td>{{ $invoice->academicYear?->name }}</td>
                    <td><code>{{ $invoice->invoice_number }}</code></td>
                    <td class="amount-col" style="font-weight: 700; font-size: 15px;">{{ number_format($payment->amount, 2) }} MAD</td>
                </tr>
            </tbody>
        </table>
        @endif

        <div class="total-box">
            <div class="total-table">
                <div class="info-row total-row">
                    <span class="info-label">Invoice Total / إجمالي الفاتورة:</span>
                    <span class="info-val">{{ number_format($invoice->total_amount, 2) }} MAD</span>
                </div>
                <div class="info-row total-row">
                    <span class="info-label">Total Paid So Far / مجموع المؤدى:</span>
                    <span class="info-val">{{ number_format($invoice->paid_amount, 2) }} MAD</span>
                </div>
                <div class="info-row total-row">
                    <span class="info-label">Remaining Balance / الباقي:</span>
                    <span class="info-val" style="color: {{ $invoice->remaining_amount > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ number_format($invoice->remaining_amount, 2) }} MAD
                    </span>
                </div>
                <div class="info-row total-row grand-total">
                    <span>This Payment:</span>
                    <span>{{ number_format($payment->amount, 2) }} MAD</span>
                </div>
            </div>
        </div>

        @php
            $stampSrc = null;
            if ($school->stamp_signature_path) {
                $realPath = public_path('storage/' . $school->stamp_signature_path);
                if (file_exists($realPath)) {
                    $mime = mime_content_type($realPath) ?: 'image/png';
                    $stampSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($realPath));
                } else {
                    $stampSrc = asset('storage/' . $school->stamp_signature_path);
                }
            }
        @endphp

        <div class="footer" style="display: flex; justify-content: flex-end; margin-top: 40px; padding-top: 20px; border-top: 1px dashed #d1d5db;">
            <div style="text-align: center; min-width: 220px;">
                <div style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px;">
                    إدارة المؤسسة / La Direction
                </div>
                @if($school->stamp_signature_path)
                    <div style="display: flex; justify-content: center; align-items: center; min-height: 80px;">
                        <img src="{{ $stampSrc ?? asset('storage/' . $school->stamp_signature_path) }}" alt="Stamp & Signature" style="max-height: 85px; max-width: 200px; object-fit: contain;">
                    </div>
                @else
                    <div class="signature-box" style="margin: 0 auto; width: 180px; height: 60px; border-bottom: 1px solid #9ca3af;"></div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function shareReceipt() {
            const shareData = {
                title: 'Receipt {{ $payment->receipt_number }} - {{ $school->name }}',
                text: 'Payment Receipt {{ $payment->receipt_number }} ({{ number_format($payment->amount, 2) }} MAD) for {{ $student->first_name }} {{ $student->last_name }}',
                url: window.location.href,
            };

            if (navigator.share) {
                navigator.share(shareData).catch(() => {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Receipt link copied to clipboard! (تم نسخ رابط الوصل بنجاح)');
                });
            } else {
                window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(shareData.text + ' ' + shareData.url), '_blank');
            }
        }
    </script>
</body>
</html>
