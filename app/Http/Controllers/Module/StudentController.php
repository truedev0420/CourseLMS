<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Model\Course;
use App\Model\Enrollment;
use App\Model\Student;
use App\TeacherCoupon;
use Alert;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Model\CoursePurchaseHistory;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }


    /*All students with search option */
    public function index(Request $request)
    {
        if (Auth::user()->user_type == "Admin") {
            /*if Authenticated  user is admin , admin can show all students */
            if ($request->get('search')) {

                // Search by Teacher Coupon used
                $student_id = TeacherCoupon::where('code', $request->get('search'))
                    ->where('is_used', true)    
                    ->first();
                
                if($student_id){
                    $student_id = $student_id->student_id;
                    $students = Student::where('user_id', $student_id)
                        ->orderBydesc('id')->paginate(10);
                }
                else {
                    // Search by ID
                    $user = User::where('id', $request->search)->first();
                    if($user)
                        $students = Student::where('user_id', $user->id)->orderBydesc('id')->paginate(10);
                    else {
                        $students = Student::where('name', 'like', '%' . $request->get('search') . '%')
                            ->orWhere('email', 'like', '%' . $request->get('search') . '%')
                            ->orderBydesc('id')->paginate(10);
                    }
                }
            } else {
                $students = Student::orderBydesc('id')->paginate(10);
            }
        } else {
            /*There are the Instructor show only his/her register Students list*/
            $courses = Course::where('user_id', Auth::id())->get();
            $course_id_array = array();
            foreach ($courses as $i) {
                array_push($course_id_array, $i->id);
            }
            $enroll_student_id = array();
            $enroll = Enrollment::whereIn('course_id', $course_id_array)->get();
            foreach ($enroll as $i) {
                array_push($enroll_student_id, $i->user_id);
            }

            if ($request->get('search')) {
                $students = Student::where('name', 'like', '%' . $request->get('search') . '%')
                    ->orWhere('email', 'like', '%' . $request->get('search') . '%')
                    ->whereIn('user_id', $enroll_student_id)->orderBydesc('id')->paginate(10);
            } else {
                $students = Student::whereIn('user_id', $enroll_student_id)->orderBydesc('id')->paginate(10);
            }
        }
        return view('module.students.index', compact('students'));
    }

    /*This function show all instructor related history
    like Package, Course , Enrolment Student list Get Payment History*/
    public function show($id)
    {
        $each_student = Student::where('user_id', $id)->first();
        $enrolls = Enrollment::where('user_id', $id)
            ->with('course')
            ->with('history')
            ->get();

        return view('module.students.show', compact('each_student', 'enrolls'));
    }

    public function resetPassword(Request $request){

        $user = User::findOrFail($request->student_id);
        $user->password = Hash::make($request->password);
        $user->save();

        notify()->success(translate('Password reset successfully'));
        return back();
    }


    public function deleteCourse($enrollment_id){
        $enrollment = Enrollment::findOrFail($enrollment_id);

        $course_purchase_hisgory = CoursePurchaseHistory::where('enrollment_id', $enrollment->id)->first();
        $course_purchase_hisgory->delete();

        $enrollment->delete();
        return redirect()->back();
    }

    public function destroy($id){
        $student = Student::findOrFail($id);
        $student->delete();

        /* sending instructor notification */
        notify()->success(translate('Deleted successfully'));
        return back();
    }

    //END
}
