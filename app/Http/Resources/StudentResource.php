<?php

namespace App\Http\Resources;

class StudentResource extends ApiResource
{
	protected array $fields = ['id', 'name', 'email', 'account_type', 'status', 'created_at', 'updated_at'];

	public function toArray(\Illuminate\Http\Request $request): array
	{
		$data = parent::toArray($request);
		$data['inee'] = (string) \Illuminate\Support\Facades\DB::table('student_profiles')->where('user_id', $this->id)->value('inee');

		return $data;
	}
}
