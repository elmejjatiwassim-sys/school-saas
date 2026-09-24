<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقات حسابات الدخول - {{ $school->name }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            padding: 20px;
        }

        .no-print-bar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #f59e0b;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #d97706;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
        }
        .btn-secondary:hover {
            background-color: #d1d5db;
        }

        .cards-container {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .card {
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            position: relative;
            break-inside: avoid;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .school-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .card-badge {
            background-color: #fef3c7;
            color: #92400e;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
        }

        .user-name {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .user-meta {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .credentials-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .cred-row:last-child {
            margin-bottom: 0;
        }

        .cred-label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .cred-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            background: #ffffff;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            direction: ltr;
        }

        .card-footer {
            border-top: 1px dashed #e2e8f0;
            padding-top: 8px;
            font-size: 10px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .no-print-bar {
                display: none;
            }

            .cards-container {
                max-width: 100%;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .card {
                border: 1px dashed #94a3b8;
                padding: 12px;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <h2 style="font-size: 18px; font-weight: 700;">بطاقات حسابات الدخول - {{ $school->name }}</h2>
            <p style="font-size: 13px; color: #6b7280;">
                إجمالي الحسابات: {{ $users->count() }}
                @if($classroom)
                    | القسم: {{ $classroom->name }}
                @endif
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ طباعة البطاقات (Print)
            </button>
            <button onclick="window.history.back()" class="btn btn-secondary">
                ← رجوع
            </button>
        </div>
    </div>

    @if($users->isEmpty())
        <div style="text-align: center; padding: 60px; background: white; max-width: 600px; margin: 0 auto; border-radius: 12px;">
            <p style="font-size: 16px; color: #64748b;">لا توجد حسابات مطابقة لمعايير البحث الحالية.</p>
        </div>
    @else
        <div class="cards-container">
            @foreach($users as $user)
                @php
                    $roleLabel = match($user->role) {
                        'student' => 'تلميذ / Student',
                        'teacher' => 'أستاذ / Teacher',
                        'guardian' => 'ولي أمر / Guardian',
                        'admin' => 'إدارة / Admin',
                        default => ucfirst($user->role ?? 'User'),
                    };
                    $classInfo = $user->student?->classroom?->name ?? $user->classroom?->name ?? null;
                    $gradeInfo = $user->student?->classroom?->gradeLevel?->name ?? null;
                @endphp
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="school-name">{{ $school->name }}</div>
                            <div style="font-size: 11px; color: #64748b;">كود المؤسسة: {{ $school->code }}</div>
                        </div>
                        <span class="card-badge">{{ $roleLabel }}</span>
                    </div>

                    <div>
                        <div class="user-name">{{ $user->name }}</div>
                        <div class="user-meta">
                            @if($classInfo)
                                القسم: <strong>{{ $classInfo }}</strong>
                                @if($gradeInfo) - ({{ $gradeInfo }}) @endif
                            @else
                                حساب مستخدم مؤسساتي
                            @endif
                        </div>

                        <div class="credentials-box">
                            <div class="cred-row">
                                <span class="cred-label">اسم المستخدم (Username):</span>
                                <span class="cred-value">{{ $user->username }}</span>
                            </div>
                            <div class="cred-row">
                                <span class="cred-label">كلمة المرور المؤقتة:</span>
                                <span class="cred-value">{{ $user->temporary_password ?? '••••••••' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span>رابط المنصة: {{ rtrim(config('app.url'), '/') }}/admin</span>
                        <span>يرجى الحفاظ على سرية البيانات</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</body>
</html>
