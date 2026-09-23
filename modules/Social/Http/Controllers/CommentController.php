<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Models\SocialComment;
use Modules\Social\Services\SocialEngagerContactService;

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

    public function createContact(
        Request $request,
        SocialComment $comment,
        SocialEngagerContactService $engagers,
    ): RedirectResponse {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $comment->company_id !== (int) $company->id) {
            abort(404);
        }

        $contact = $engagers->createFromComment($comment);

        return redirect()
            ->back()
            ->with('success', __('Contact created for :name.', ['name' => $contact->name]));
    }
}
