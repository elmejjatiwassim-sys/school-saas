<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">إجمالي الحسابات / Total Users</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $this->stats['total_users'] }}</div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-users class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">حسابات التلاميذ / Student Accounts</div>
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">{{ $this->stats['students'] }}</div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-success-50 dark:bg-success-950/50 flex items-center justify-center text-success-600 dark:text-success-400">
                    <x-heroicon-o-academic-cap class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">الأساتذة والموظفون / Teachers & Staff</div>
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ $this->stats['teachers'] }}</div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-950/50 flex items-center justify-center text-primary-600 dark:text-primary-400">
                    <x-heroicon-o-briefcase class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">تلاميذ بدون حساب / Pending Accounts</div>
                    <div class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-1">{{ $this->stats['unassigned'] }}</div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-danger-50 dark:bg-danger-950/50 flex items-center justify-center text-danger-600 dark:text-danger-400">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- Filter & Actions Bar --}}
        <div class="p-5 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    {{-- Search Input --}}
                    <div class="w-full md:w-64">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="بحث بالاسم أو اسم المستخدم..."
                            class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                        />
                    </div>

                    {{-- Role / Category Filter --}}
                    <div class="w-full md:w-48">
                        <select wire:model.live="roleFilter" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="all">كل الحسابات (All Accounts)</option>
                            <option value="student">تلاميذ (Students)</option>
                            <option value="teacher">أساتذة (Teachers)</option>
                            <option value="guardian">أولياء أمور (Guardians)</option>
                            <option value="admin">إدارة (Admins)</option>
                            <option value="unassigned_students">⚠️ تلاميذ بدون حسابات ({{ $this->stats['unassigned'] }})</option>
                        </select>
                    </div>

                    {{-- Classroom Filter --}}
                    <div class="w-full md:w-48">
                        <select wire:model.live="classroomId" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="">كل الفصول (All Classrooms)</option>
                            @foreach($this->classrooms as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="openBulkModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-semibold shadow-sm transition"
                    >
                        <x-heroicon-o-sparkles class="w-4 h-4" />
                        توليد جماعي للقسم (Bulk Generate)
                    </button>

                    <a
                        href="{{ route('filament.admin.credentials.print-cards', [
                            'tenant' => $this->school?->slug,
                            'classroom_id' => $classroomId,
                            'role' => $roleFilter !== 'all' && $roleFilter !== 'unassigned_students' ? $roleFilter : null
                        ]) }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm font-semibold transition"
                    >
                        <x-heroicon-o-printer class="w-4 h-4" />
                        طباعة البطاقات (Print Cards)
                    </a>

                    <button
                        type="button"
                        wire:click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg text-sm font-semibold shadow-sm transition"
                    >
                        <x-heroicon-o-plus class="w-4 h-4" />
                        حساب يدوي (New User)
                    </button>
                </div>
            </div>
        </div>

        {{-- TABLE: UNASSIGNED STUDENTS --}}
        @if($roleFilter === 'unassigned_students')
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">قائمة التلاميذ غير المسجلين بحسابات ({{ $this->unassignedStudents->count() }})</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">يمكنك إنشاء حساب فردي أو التوليد الجماعي للقسم بأكمله بنقرة واحدة</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 font-semibold border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-4">رقم التسجيل</th>
                                <th class="py-3 px-4">اسم التلميذ</th>
                                <th class="py-3 px-4">القسم / المستوى</th>
                                <th class="py-3 px-4">رمز مسار</th>
                                <th class="py-3 px-4">الحالة</th>
                                <th class="py-3 px-4 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse($this->unassignedStudents as $student)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="py-3 px-4 font-mono text-xs">{{ $student->registration_number }}</td>
                                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-white">{{ $student->first_name }} {{ $student->last_name }}</td>
                                    <td class="py-3 px-4">{{ $student->classroom?->name ?? 'غير محدد' }}</td>
                                    <td class="py-3 px-4 font-mono text-xs">{{ $student->massar_code ?? '-' }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400">
                                            بدون حساب
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button
                                            type="button"
                                            wire:click="generateAccountForStudent({{ $student->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                                        >
                                            <x-heroicon-o-user-plus class="w-3.5 h-3.5" />
                                            إنشاء حساب (Generate Account)
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                        كل التلاميذ المسجلين في هذا القسم لديهم حسابات مفعلة! 🎉
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- TABLE: USERS / CREDENTIALS --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 font-semibold border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-3.5 px-4">المستخدم (User Name & Role)</th>
                                <th class="py-3.5 px-4">القسم / المستوى</th>
                                <th class="py-3.5 px-4">اسم المستخدم (Username)</th>
                                <th class="py-3.5 px-4">كلمة المرور المؤقتة</th>
                                <th class="py-3.5 px-4 text-center">الحالة</th>
                                <th class="py-3.5 px-4 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse($this->users as $user)
                                @php
                                    $roleBadge = match($user->role) {
                                        'student' => ['bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400', 'label' => 'تلميذ'],
                                        'teacher' => ['bg' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400', 'label' => 'أستاذ'],
                                        'guardian' => ['bg' => 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-400', 'label' => 'ولي أمر'],
                                        'admin' => ['bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400', 'label' => 'إدارة'],
                                        default => ['bg' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300', 'label' => ucfirst($user->role ?? 'User')],
                                    };
                                    $classroomName = $user->student?->classroom?->name ?? $user->classroom?->name ?? '-';
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ $user->name }}</div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $roleBadge['bg'] }}">
                                                {{ $roleBadge['label'] }}
                                            </span>
                                            @if($user->email)
                                                <span class="text-xs text-gray-400">{{ $user->email }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-gray-700 dark:text-gray-300">
                                        {{ $classroomName }}
                                    </td>
                                    <td class="py-3.5 px-4" x-data="{ copied: false }">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 text-amber-600 dark:text-amber-400">
                                                {{ $user->username }}
                                            </span>
                                            <button
                                                type="button"
                                                @click="navigator.clipboard.writeText('{{ $user->username }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition"
                                                title="نسخ اسم المستخدم"
                                            >
                                                <template x-if="!copied">
                                                    <x-heroicon-o-clipboard-document class="w-4 h-4" />
                                                </template>
                                                <template x-if="copied">
                                                    <x-heroicon-o-check class="w-4 h-4 text-success-600" />
                                                </template>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        @if($user->temporary_password)
                                            <span class="bg-gray-50 dark:bg-gray-800/80 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700">
                                                {{ $user->temporary_password }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">••••••••</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if($user->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-success-50 text-success-700 dark:bg-success-950 dark:text-success-400">
                                                نشط (Active)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                معطل (Inactive)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button
                                                type="button"
                                                wire:click="resetPassword({{ $user->id }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400 dark:hover:bg-amber-900 rounded-md text-xs font-semibold transition"
                                                title="إعادة تعيين كلمة المرور"
                                            >
                                                <x-heroicon-o-key class="w-3.5 h-3.5" />
                                                إعادة تعيين (Reset)
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="toggleUserStatus({{ $user->id }})"
                                                class="inline-flex items-center p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                                                title="{{ $user->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}"
                                            >
                                                @if($user->is_active)
                                                    <x-heroicon-o-no-symbol class="w-4 h-4 text-danger-500" />
                                                @else
                                                    <x-heroicon-o-check-circle class="w-4 h-4 text-success-500" />
                                                @endif
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                        لا توجد حسابات مطابقة لمعايير البحث.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- MODAL: PASSWORD REVEAL & COPY --}}
        @if($showPasswordModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-800" x-data="{ credCopied: false }">
                    <div class="text-center mb-5">
                        <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center mb-3">
                            <x-heroicon-o-key class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">بيانات الدخول الجديدة</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">تم تجهيز بيانات الاعتماد الخاصة بـ: <strong>{{ $modalName }}</strong></p>
                    </div>

                    <div class="space-y-3 bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400">اسم المستخدم (Username):</span>
                            <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-base">{{ $modalUsername }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400">كلمة المرور المؤقتة (Password):</span>
                            <span class="font-mono font-bold text-gray-900 dark:text-white text-base">{{ $modalPassword }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs pt-2 border-t border-gray-200 dark:border-gray-700 text-gray-400">
                            <span>رابط الدخول:</span>
                            <span class="font-mono">{{ rtrim(config('app.url'), '/') }}/admin</span>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col gap-2">
                        <button
                            type="button"
                            @click="navigator.clipboard.writeText('المستخدم: {{ $modalName }}\nاسم الدخول: {{ $modalUsername }}\nكلمة المرور: {{ $modalPassword }}\nالرابط: {{ rtrim(config('app.url'), '/') }}/admin'); credCopied = true; setTimeout(() => credCopied = false, 2500)"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition shadow-sm"
                        >
                            <template x-if="!credCopied">
                                <span class="flex items-center gap-1.5"><x-heroicon-o-clipboard-document class="w-4 h-4" /> نسخ كامل البيانات (Copy All)</span>
                            </template>
                            <template x-if="credCopied">
                                <span class="flex items-center gap-1.5"><x-heroicon-o-check class="w-4 h-4" /> تم النسخ إلى الحافظة! ✓</span>
                            </template>
                        </button>

                        <button
                            type="button"
                            wire:click="closePasswordModal"
                            class="w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl text-sm font-medium transition"
                        >
                            إغلاق (Close)
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL: BULK GENERATE CLASS CREDENTIALS --}}
        @if($showBulkModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-800">
                    <div class="text-center mb-5">
                        <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center mb-3">
                            <x-heroicon-o-sparkles class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">توليد جماعي لحسابات القسم</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">سيتم توليد اسم مستخدم وكلمة مرور مؤقتة لجميع تلاميذ القسم الذين ليس لديهم حساب بعد.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                اختر الفصل الدراسي (Classroom) <span class="text-danger-600">*</span>
                            </label>
                            <select wire:model="bulkClassroomId" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                                <option value="">-- اختر القسم --</option>
                                @foreach($this->classrooms as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            wire:click="executeBulkGenerate"
                            class="flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition shadow-sm"
                        >
                            توليد الحسابات الآن
                        </button>
                        <button
                            type="button"
                            wire:click="closeBulkModal"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl text-sm font-medium transition"
                        >
                            إلغاء
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL: CREATE MANUAL USER --}}
        @if($showCreateModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-800">
                    <div class="text-center mb-5">
                        <div class="w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-950 text-primary-600 dark:text-primary-400 mx-auto flex items-center justify-center mb-3">
                            <x-heroicon-o-user-plus class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">إضافة مستخدم جديد</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">إنشاء حساب للأستاذ، ولي الأمر، أو الإدارة وتوليد اسم مستخدم فريد تلقائياً</p>
                    </div>

                    <div class="space-y-4 text-right">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الاسم الكامل <span class="text-danger-600">*</span></label>
                            <input type="text" wire:model="createName" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" placeholder="مثال: ذ. محمد السعدي" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الصفة / الدور <span class="text-danger-600">*</span></label>
                            <select wire:model="createRole" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                                <option value="teacher">أستاذ (Teacher)</option>
                                <option value="admin">إدارة مدرسة (Admin)</option>
                                <option value="guardian">ولي أمر (Guardian)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">البريد الإلكتروني (اختياري)</label>
                            <input type="email" wire:model="createEmail" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" placeholder="teacher@school.ma" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">القسم المرتبط (اختياري)</label>
                            <select wire:model="createClassroomId" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                                <option value="">-- بدون فصل --</option>
                                @foreach($this->classrooms as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            wire:click="executeCreateUser"
                            class="flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition shadow-sm"
                        >
                            إنشاء الحساب الآن
                        </button>
                        <button
                            type="button"
                            wire:click="closeCreateModal"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl text-sm font-medium transition"
                        >
                            إلغاء
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
