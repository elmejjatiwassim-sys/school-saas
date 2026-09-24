<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CredentialCardPrintController extends Controller
{
    public function show(Request $request, School $tenant): View
    {
        $currentUser = $request->user();
        abort_unless($currentUser && (int) $currentUser->school_id === (int) $tenant->id, 403);

        $classroomId = $request->query('classroom_id');
        $role = $request->query('role');

        $query = User::where('school_id', $tenant->id)
            ->whereNotNull('username')
            ->with(['student.classroom.gradeLevel', 'classroom.gradeLevel']);

        if ($classroomId) {
            $query->where(function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId)
                    ->orWhereHas('student', fn ($sq) => $sq->where('classroom_id', $classroomId));
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('name')->get();
        $classroom = $classroomId ? Classroom::where('school_id', $tenant->id)->find($classroomId) : null;

        return view('credentials.cards', [
            'school' => $tenant,
            'users' => $users,
            'classroom' => $classroom,
            'role' => $role,
        ]);
    }
}
