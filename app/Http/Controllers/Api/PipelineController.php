<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    /**
     * List pipelines visible to the current user, each with its stages.
     * Tenant scoping on Pipeline (and Lead within stages) already restricts
     * results to the user's own account subtree.
     */
    public function index(Request $request)
    {
        return Pipeline::with(['stages' => function ($query) {
            $query->withCount('leads');
        }])->get();
    }
}
