<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الأداء الإلكتروني - {{ $invoice->invoice_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col justify-between">
    {{-- Header / Top Navigation --}}
    <header class="bg-white border-b border-slate-200 shadow-sm py-4">
        <div class="max-w-3xl mx-auto px-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    {{ mb_substr($school->name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 leading-tight">{{ $school->name }}</h1>
                    <p class="text-xs text-slate-500">بوابة تدبير الرسوم والخدمات المدرسية</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                🔒 بوابة مؤمنة
            </span>
        </div>
    </header>

    {{-- Main Container --}}
    <main class="max-w-3xl mx-auto px-4 py-8 flex-1 w-full">
        {{-- Teaser / Coming Soon Banner --}}
        <div class="mb-8 rounded-2xl bg-gradient-to-br from-blue-700 via-indigo-700 to-purple-800 text-white p-6 shadow-xl relative overflow-hidden">
            <div class="absolute -right-8 -top-8 w-44 h-44 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col sm:flex-row items-center gap-5 text-center sm:text-right">
                <div class="w-16 h-16 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center text-3xl shrink-0 shadow-inner">
                    💳
                </div>
                <div class="space-y-1.5 flex-1">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-amber-400 text-slate-900">
                        ⚡ قريباً / Coming Soon
                    </div>
                    <h2 class="text-xl font-bold">بوابة الأداء الإلكتروني المباشر (Online Card Payment)</h2>
                    <p class="text-sm text-blue-100 leading-relaxed">
                        بوابة الأداء الإلكتروني قيد التفعيل حالياً. يرجى التواصل مع إدارة المؤسسة لأداء الواجبات.
                    </p>
                </div>
            </div>
        </div>

        {{-- Invoice Summary Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
            <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">تفاصيل الفاتورة / Invoice Details</span>
                    <h3 class="text-lg font-bold text-slate-900 mt-0.5">{{ $invoice->title }}</h3>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $invoice->invoice_number }}</p>
                </div>
                <div class="text-left" dir="ltr">
                    <span class="text-xs text-slate-400 block font-sans">Total Amount</span>
                    <span class="text-2xl font-black text-slate-900">{{ number_format($invoice->total_amount, 2) }} <span class="text-sm font-semibold text-slate-500">MAD</span></span>
                </div>
            </div>

            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-slate-50/50">
                <div class="bg-white p-4 rounded-xl border border-slate-200/80">
                    <span class="text-xs text-slate-400 block mb-1">بيانات التلميذ / Student</span>
                    <p class="font-bold text-slate-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                    <p class="text-xs text-slate-500 mt-1">الرقم التعريفي: <code>{{ $student->registration_number }}</code></p>
                    @if($student->classroom)
                        <p class="text-xs text-slate-500 mt-0.5">الفصل: {{ $student->classroom->name }}</p>
                    @endif
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200/80">
                    <span class="text-xs text-slate-400 block mb-1">حالة الأداء / Payment Status</span>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $invoice->status->value === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $invoice->status->getLabel() }}
                        </span>
                        @if($invoice->due_date)
                            <span class="text-xs text-slate-500">تاريخ الاستحقاق: {{ $invoice->due_date->format('d/m/Y') }}</span>
                        @endif
                    </div>
                    @if($invoice->remaining_amount > 0)
                        <p class="text-xs text-rose-600 font-semibold mt-2">المبلغ المتبقي: {{ number_format($invoice->remaining_amount, 2) }} MAD</p>
                    @endif
                </div>
            </div>

            {{-- Line items breakdown if available --}}
            @if($invoice->items->isNotEmpty())
                <div class="p-6 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-3">بنود وتفاصيل الفاتورة</h4>
                    <div class="space-y-2">
                        @foreach($invoice->items as $item)
                            <div class="flex items-center justify-between text-xs py-2 border-b border-slate-100 last:border-0">
                                <div>
                                    <span class="font-bold text-slate-800">{{ $item->description }}</span>
                                    <span class="text-slate-400 mr-2">({{ $item->quantity }} × {{ number_format($item->amount, 2) }} MAD)</span>
                                </div>
                                <span class="font-bold text-slate-900" dir="ltr">{{ number_format($item->subtotal, 2) }} MAD</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Disabled payment button with teaser badge --}}
            <div class="p-6 bg-slate-50 border-t border-slate-200/80 flex flex-col items-center justify-center text-center gap-3">
                <button
                    type="button"
                    disabled
                    class="w-full sm:w-auto px-8 py-3 rounded-xl bg-slate-300 text-slate-500 font-bold text-sm cursor-not-allowed flex items-center justify-center gap-2 shadow-inner"
                >
                    <span>💳 أداء بالبطاقة البنكية (قريباً)</span>
                    <span class="text-[10px] bg-slate-400 text-white px-2 py-0.5 rounded-full">Inactive</span>
                </button>
                <p class="text-xs text-slate-500">
                    لأداء الواجبات، يرجى التوجه إلى إدارة المؤسسة أو استخدام التحويل البنكي لحساب المؤسسة.
                </p>
            </div>
        </div>

        {{-- Contact Box --}}
        @if($school->phone || $school->email)
            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-600 bg-white">
                <span class="font-bold block mb-1">معلومات التواصل مع إدارة المؤسسة:</span>
                @if($school->phone)<span class="ml-3">📞 {{ $school->phone }}</span>@endif
                @if($school->email)<span>✉️ {{ $school->email }}</span>@endif
            </div>
        @endif
    </main>

    {{-- Footer --}}
    <footer class="py-6 border-t border-slate-200 bg-white text-center text-xs text-slate-400">
        <p>&copy; {{ date('Y') }} {{ $school->name }} &bull; جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>
