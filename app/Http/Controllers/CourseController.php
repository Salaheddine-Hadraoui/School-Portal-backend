<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Services\SupabaseStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    protected $supabaseStorage;
    public function __construct()
    {
        $this->supabaseStorage =  new SupabaseStorage(env('SUPABASE_BUCKET_COURSES'));
    }
    public function modules()
    {
        $modules = Module::all();
        return response()->json([
            'modules' => $modules
        ], 200);
    }
    public function index()
    {
        $courses = Course::with('module')->get();
        return response()->json([
            'courses' => $courses
        ], 200);
    }


    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|min:8',
                'course_pdf' => 'required|file|mimes:pdf|max:10240',
                'module_id' => 'required|exists:modules,id'
            ]);
        } catch (ValidationException $err) {
            return response()->json([
                'errors' => $err->errors(),
                'succes' => false
            ], 200);
        }

        if ($request->hasFile('course_pdf')) {

            $filepath = $this->supabaseStorage->upload($request->file('course_pdf'));
            $data['course_pdf'] = $filepath;
        }
        $course = Course::create($data);
        //jib l module li relationnell m3a had cours jdid
        $course->load('module');

        return response()->json([
            'course' => $course,
            'succes' => true
        ], 201);
    }





    public function destroy($id)
    {

        $cours = Course::find($id);


        if (!$cours) {
            return response()->json([
                'success' => false,
                'message' => 'Cours introuvable.'
            ], 422);
        }



        if ($this->supabaseStorage->exists(trim($cours['course_pdf'], '/'))) {
            $this->supabaseStorage->delete($cours['course_pdf']);
        }

        $cours->delete();

        return response()->json([
            'success' => true,
            'cours' => $cours,
            'message' => 'Cours supprimé avec succès.',
        ], 200);
    }
}
