<?php

namespace App\Http\Controllers;

use App\Models\Lms\BatchMember;
use App\Models\Lms\LmsUser;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Student::search($request->input('search'));

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $students = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $statuses = Student::select('status')->distinct()->whereNotNull('status')->pluck('status');

        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'active')->count();

        return view('students.index', compact('students', 'statuses', 'totalStudents', 'activeStudents'));
    }

    public function show(Student $student): View
    {
        // Match this CRM record to an LMS account by email/phone so the profile
        // can show batch enrolments. Best-effort: if the LMS DB is down, the
        // profile still renders without the LMS section.
        $lmsUser = null;
        $lmsMemberships = collect();

        try {
            $lmsUser = LmsUser::query()
                ->where(function ($q) use ($student) {
                    $q->when($student->email, fn ($u) => $u->orWhere('email', $student->email))
                        ->when($student->phone_number, fn ($u) => $u->orWhere('phone', $student->phone_number));
                })
                ->when(! $student->email && ! $student->phone_number, fn ($q) => $q->whereRaw('1 = 0'))
                ->first();

            if ($lmsUser) {
                $lmsMemberships = BatchMember::with('batch.course')
                    ->where('user_id', $lmsUser->id)
                    ->get();
            }
        } catch (QueryException|\PDOException $e) {
            report($e);
        }

        return view('students.show', compact('student', 'lmsUser', 'lmsMemberships'));
    }
}
