<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\AccountController;
use Modules\Social\Http\Controllers\CalendarController;
use Modules\Social\Http\Controllers\CommentController;
use Modules\Social\Http\Controllers\FacebookConnectController;
use Modules\Social\Http\Controllers\GbpConnectController;
use Modules\Social\Http\Controllers\HashtagGroupController;
use Modules\Social\Http\Controllers\HomeController;
use Modules\Social\Http\Controllers\InsightsController;
use Modules\Social\Http\Controllers\InstagramConnectController;
use Modules\Social\Http\Controllers\LabelController;
use Modules\Social\Http\Controllers\LinkedInConnectController;
use Modules\Social\Http\Controllers\MediaController;
use Modules\Social\Http\Controllers\OfferRedirectController;
use Modules\Social\Http\Controllers\PinterestConnectController;
use Modules\Social\Http\Controllers\PostController;
use Modules\Social\Http\Controllers\QueueSlotController;
use Modules\Social\Http\Controllers\TemplateController;
use Modules\Social\Http\Controllers\ThreadsConnectController;
use Modules\Social\Http\Controllers\TikTokConnectController;
use Modules\Social\Http\Controllers\XConnectController;
use Modules\Social\Http\Controllers\YouTubeConnectController;

Route::middleware(['web'])
    ->get('/o/{token}', OfferRedirectController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('social.offer.redirect');

Route::middleware(['web', 'impersonate', 'verified', 'auth', 'isOwnerOnPro', 'plan.plugin:social'])
    ->prefix('social')
    ->group(function () {
        Route::get('/', [HomeController::class, '__invoke'])->name('social.home');
        Route::get('/accounts', [AccountController::class, 'index'])->name('social.accounts.index');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('social.accounts.disconnect');

        Route::get('/media', [MediaController::class, 'index'])->name('social.media.index');
        Route::post('/media', [MediaController::class, 'store'])->name('social.media.store');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('social.media.destroy');

        Route::get('/posts', [PostController::class, 'index'])->name('social.posts.index');
        Route::get('/posts/create', [PostController::class, 'create'])->name('social.posts.create');
        Route::get('/posts/{post}', [PostController::class, 'show'])->name('social.posts.show');
        Route::post('/posts/{post}/submit-approval', [PostController::class, 'submitForApproval'])
            ->name('social.posts.submit-approval');
        Route::post('/posts/{post}/approve', [PostController::class, 'approve'])
            ->name('social.posts.approve');
        Route::post('/posts/{post}/reject', [PostController::class, 'reject'])
            ->name('social.posts.reject');

        Route::get('/comments', [CommentController::class, 'index'])->name('social.comments.index');
        Route::post('/comments/{comment}/create-contact', [CommentController::class, 'createContact'])
            ->name('social.comments.create-contact');
        Route::post('/comments/{comment}/open-inbox', [CommentController::class, 'openInbox'])
            ->name('social.comments.open-inbox');

        Route::get('/templates', [TemplateController::class, 'index'])->name('social.templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->name('social.templates.store');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('social.templates.update');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('social.templates.destroy');

        Route::get('/hashtags', [HashtagGroupController::class, 'index'])->name('social.hashtags.index');
        Route::post('/hashtags', [HashtagGroupController::class, 'store'])->name('social.hashtags.store');
        Route::delete('/hashtags/{hashtag}', [HashtagGroupController::class, 'destroy'])->name('social.hashtags.destroy');

        Route::get('/labels', [LabelController::class, 'index'])->name('social.labels.index');
        Route::post('/labels', [LabelController::class, 'store'])->name('social.labels.store');
        Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('social.labels.destroy');

        Route::get('/queue', [QueueSlotController::class, 'index'])->name('social.queue.index');
        Route::post('/queue', [QueueSlotController::class, 'store'])->name('social.queue.store');
        Route::post('/queue/seed', [QueueSlotController::class, 'seedRecommended'])->name('social.queue.seed');
        Route::delete('/queue/{slot}', [QueueSlotController::class, 'destroy'])->name('social.queue.destroy');

        Route::get('/insights', [InsightsController::class, 'index'])
            ->middleware('plan.capability:social_analytics')
            ->name('social.insights');

        Route::get('/calendar', CalendarController::class)->name('social.calendar');

        Route::get('/accounts/connect/facebook', [FacebookConnectController::class, 'redirect'])
            ->name('social.accounts.connect.facebook');
        Route::get('/accounts/connect/facebook/callback', [FacebookConnectController::class, 'callback'])
            ->name('social.accounts.connect.facebook.callback');

        Route::get('/accounts/connect/instagram', [InstagramConnectController::class, 'redirect'])
            ->name('social.accounts.connect.instagram');
        Route::get('/accounts/connect/instagram/callback', [InstagramConnectController::class, 'callback'])
            ->name('social.accounts.connect.instagram.callback');

        Route::get('/accounts/connect/linkedin', [LinkedInConnectController::class, 'redirect'])
            ->name('social.accounts.connect.linkedin');
        Route::get('/accounts/connect/linkedin/callback', [LinkedInConnectController::class, 'callback'])
            ->name('social.accounts.connect.linkedin.callback');

        Route::get('/accounts/connect/tiktok', [TikTokConnectController::class, 'redirect'])
            ->name('social.accounts.connect.tiktok');
        Route::get('/accounts/connect/tiktok/callback', [TikTokConnectController::class, 'callback'])
            ->name('social.accounts.connect.tiktok.callback');

        Route::get('/accounts/connect/youtube', [YouTubeConnectController::class, 'redirect'])
            ->name('social.accounts.connect.youtube');
        Route::get('/accounts/connect/youtube/callback', [YouTubeConnectController::class, 'callback'])
            ->name('social.accounts.connect.youtube.callback');

        Route::get('/accounts/connect/threads', [ThreadsConnectController::class, 'redirect'])
            ->name('social.accounts.connect.threads');
        Route::get('/accounts/connect/threads/callback', [ThreadsConnectController::class, 'callback'])
            ->name('social.accounts.connect.threads.callback');

        Route::get('/accounts/connect/pinterest', [PinterestConnectController::class, 'redirect'])
            ->name('social.accounts.connect.pinterest');
        Route::get('/accounts/connect/pinterest/callback', [PinterestConnectController::class, 'callback'])
            ->name('social.accounts.connect.pinterest.callback');

        Route::get('/accounts/connect/gbp', [GbpConnectController::class, 'redirect'])
            ->name('social.accounts.connect.gbp');
        Route::get('/accounts/connect/gbp/callback', [GbpConnectController::class, 'callback'])
            ->name('social.accounts.connect.gbp.callback');

        Route::get('/accounts/connect/x', [XConnectController::class, 'redirect'])
            ->name('social.accounts.connect.x');
        Route::get('/accounts/connect/x/callback', [XConnectController::class, 'callback'])
            ->name('social.accounts.connect.x.callback');
    });
