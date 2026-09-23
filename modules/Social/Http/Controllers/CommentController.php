<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Social\Models\SocialComment;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $comments = SocialComment::query()
            ->with(['post.defaultVersion', 'account'])
            ->when($company, fn ($q) => $q->where('company_id', $company->id))
            ->orderByDesc('commented_at')
            ->orderByDesc('id')
            ->paginate(30);

        return view('social::comments.index', [
            'comments' => $comments,
            'company' => $company,
        ]);
    }
}
