<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Models\SocialComment;
use Modules\Social\Services\SocialEngagementInboxService;
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
            'inboxEnabled' => app(SocialEngagementInboxService::class)->shouldOpenInbox($company),
        ]);
    }

    public function createContact(
        Request $request,
        SocialComment $comment,
        SocialEngagerContactService $engagers,
        SocialEngagementInboxService $inbox,
    ): RedirectResponse {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $comment->company_id !== (int) $company->id) {
            abort(404);
        }

        $contact = $engagers->createFromComment($comment);
        $opened = $inbox->openFromComment($comment->fresh(), createContactIfMissing: false);

        if ($opened['opened'] ?? false) {
            return redirect()
                ->back()
                ->with('success', __('Contact created and comment opened in inbox for :name.', [
                    'name' => $contact->name,
                ]));
        }

        return redirect()
            ->back()
            ->with('success', __('Contact created for :name.', ['name' => $contact->name]));
    }

    public function openInbox(
        Request $request,
        SocialComment $comment,
        SocialEngagementInboxService $inbox,
    ): RedirectResponse {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $comment->company_id !== (int) $company->id) {
            abort(404);
        }

        $result = $inbox->openFromComment($comment, createContactIfMissing: true);

        if (! ($result['opened'] ?? false)) {
            $message = match ($result['skipped'] ?? '') {
                'missing_channel_connection' => __('Connect Instagram or Messenger inbox for this company before opening Social comments in Chat.'),
                'offering_or_config' => __('Opening comments in inbox requires full offering mode.'),
                'unsupported_provider' => __('Only Facebook and Instagram comments can open in inbox.'),
                default => __('Could not open this comment in inbox.'),
            };

            return redirect()->back()->withError($message);
        }

        return redirect()
            ->route('chat.index')
            ->with('success', __('Comment opened in inbox.'));
    }
}
