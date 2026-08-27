<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Http\Resources\StudentResource;
use Illuminate\Support\Facades\DB;

class StudentController extends ApiController
{
    protected string $model = User::class;
    protected string $resource = StudentResource::class;
    protected array $searchable = ['name', 'email'];
    protected array $filterable = ['status'];
    protected array $sortable = ['name', 'created_at', 'updated_at'];

    public function index(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->merge(['account_type' => 'etudiant']);
        $this->filterable = ['account_type', 'status'];
        if ($request->filled('inee')) {
            $request->merge(['inee' => (string) $request->input('inee')]);
        }
        $query = User::query()->where('account_type', 'etudiant')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')->select('users.*')->when($request->input('inee'), fn ($builder, string $inee) => $builder->where('student_profiles.inee', $inee));
        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $query->where(fn ($builder) => $builder->where('users.name', 'like', "%{$term}%")->orWhere('users.email', 'like', "%{$term}%")->orWhere('student_profiles.inee', 'like', "%{$term}%"));
        }
        $paginator = $query->paginate((int) $request->input('per_page', 20))->withQueryString();

        return response()->json(['data' => StudentResource::collection($paginator->items()), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()]]);
    }

    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $student = User::where('account_type', 'etudiant')->findOrFail($id);
        return response()->json(['data' => new StudentResource($student)]);
    }
}
