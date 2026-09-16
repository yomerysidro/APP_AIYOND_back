<?php

namespace App\Http\Controllers\Disease;

use App\Http\Controllers\Controller;
use App\Http\Data\Disease\DiseaseData;
use App\Traits\ApiResponse;

class DiseaseController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $response = DiseaseData::getDiseases();

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function show(int $id)
    {
        $response = DiseaseData::getDiseaseById($id);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }
}
