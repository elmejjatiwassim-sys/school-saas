<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Section --}}
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Classroom (الفصل الدراسي) <span class="text-danger-600">*</span>
                    </label>
                    <select wire:model.live="classroom_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                        <option value="">-- Select Classroom / اختر الفصل --</option>
                        @foreach($this->classrooms as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Attendance Date (تاريخ الحضور) <span class="text-danger-600">*</span>
                    </label>
                    <input type="date" wire:model.live="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Session (الحصة الدراسية) <span class="text-danger-600">*</span>
                    </label>
                    <select wire:model.live="session" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                        <option value="morning">Morning / صباح (08:30 - 12:00)</option>
                        <option value="afternoon">Afternoon / مساء (14:30 - 18:00)</option>
                        <option value="full_day">Full Day / يوم كامل</option>
                    </select>
                </div>
            </div>

            {{-- Quick action buttons & Stats --}}
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="markAllPresent" class="inline-flex items-center px-3 py-1.5 bg-success-50 text-success-700 rounded-lg text-xs font-semibold hover:bg-success-100 dark:bg-success-950 dark:text-success-400">
                        ✓ Mark All Present (الكل حاضر)
                    </button>
                    <button type="button" wire:click="markAllAbsent" class="inline-flex items-center px-3 py-1.5 bg-danger-50 text-danger-700 rounded-lg text-xs font-semibold hover:bg-danger-100 dark:bg-danger-950 dark:text-danger-400">
                        ✗ Mark All Absent (الكل غائب)
                    </button>
                </div>

                <div class="flex items-center gap-3 text-xs font-medium">
                    <span class="px-2.5 py-1 bg-gray-100 dark:bg-gray-800 rounded-full text-gray-700 dark:text-gray-300">
                        Total: {{ count($this->studentsAttendance) }}
                    </span>
                    <span class="px-2.5 py-1 bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-300 rounded-full">
                        Present: {{ collect($this->studentsAttendance)->where('status', 'present')->count() }}
                    </span>
                    <span class="px-2.5 py-1 bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-300 rounded-full">
                        Absent: {{ collect($this->studentsAttendance)->where('status', 'absent')->count() }}
                    </span>
                    <span class="px-2.5 py-1 bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-300 rounded-full">
                        Late: {{ collect($this->studentsAttendance)->where('status', 'late')->count() }}
                    </span>
                    <span class="px-2.5 py-1 bg-info-100 dark:bg-info-900 text-info-800 dark:text-info-300 rounded-full">
                        Excused: {{ collect($this->studentsAttendance)->where('status', 'excused')->count() }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Students Attendance Table --}}
        @if($this->students->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden dark:bg-gray-900 dark:border-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-center w-12">#</th>
                                <th class="px-4 py-3 font-semibold">Student Name (اسم التلميذ)</th>
                                <th class="px-4 py-3 font-semibold">Reg. No / Massar</th>
                                <th class="px-4 py-3 font-semibold text-center min-w-[320px]">Attendance Status (حالة الحضور)</th>
                                <th class="px-4 py-3 font-semibold min-w-[200px]">Remarks (ملاحظات)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->students as $index => $student)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                    <td class="px-4 py-3 text-center text-gray-400 text-xs">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $student->first_name }} {{ $student->last_name }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        <code>{{ $student->registration_number }}</code>
                                        @if($student->massar_code)
                                            <span class="block text-[11px] text-gray-400">{{ $student->massar_code }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50 dark:bg-gray-800 dark:border-gray-700 gap-1">
                                            {{-- Present --}}
                                            <label class="cursor-pointer text-xs px-2.5 py-1 rounded-md font-medium transition {{ ($studentsAttendance[$student->id]['status'] ?? '') === 'present' ? 'bg-success-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                <input type="radio" wire:model="studentsAttendance.{{ $student->id }}.status" value="present" class="sr-only" />
                                                ✓ حاضر
                                            </label>

                                            {{-- Absent --}}
                                            <label class="cursor-pointer text-xs px-2.5 py-1 rounded-md font-medium transition {{ ($studentsAttendance[$student->id]['status'] ?? '') === 'absent' ? 'bg-danger-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                <input type="radio" wire:model="studentsAttendance.{{ $student->id }}.status" value="absent" class="sr-only" />
                                                ✗ غائب
                                            </label>

                                            {{-- Late --}}
                                            <label class="cursor-pointer text-xs px-2.5 py-1 rounded-md font-medium transition {{ ($studentsAttendance[$student->id]['status'] ?? '') === 'late' ? 'bg-warning-500 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                <input type="radio" wire:model="studentsAttendance.{{ $student->id }}.status" value="late" class="sr-only" />
                                                ⏰ متأخر
                                            </label>

                                            {{-- Excused --}}
                                            <label class="cursor-pointer text-xs px-2.5 py-1 rounded-md font-medium transition {{ ($studentsAttendance[$student->id]['status'] ?? '') === 'excused' ? 'bg-info-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                <input type="radio" wire:model="studentsAttendance.{{ $student->id }}.status" value="excused" class="sr-only" />
                                                📄 مبرر
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="text"
                                            wire:model="studentsAttendance.{{ $student->id }}.remarks"
                                            placeholder="Optional remarks..."
                                            class="w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Save Bar --}}
                <div class="p-4 bg-gray-50 border-t border-gray-200 dark:bg-gray-800/80 dark:border-gray-700 flex justify-end gap-3">
                    <button
                        type="button"
                        wire:click="save"
                        class="inline-flex items-center px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg shadow-sm text-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        💾 Save Attendance / حفظ تسجيل الغياب
                    </button>
                </div>
            </div>
        @elseif($classroom_id)
            <div class="text-center py-12 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    No active students enrolled in this classroom. / لا يوجد تلاميذ مسجلين في هذا الفصل.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
