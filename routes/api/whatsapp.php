<?php

use App\Http\Controllers\Api\Whatsapp\AutoresponderController;
use App\Http\Controllers\Api\Whatsapp\CallResponderController;
use App\Http\Controllers\Api\Whatsapp\CampaignController;
use App\Http\Controllers\Api\Whatsapp\ChannelController;
use App\Http\Controllers\Api\Whatsapp\ChatbotRuleController;
use App\Http\Controllers\Api\Whatsapp\ContactController;
use App\Http\Controllers\Api\Whatsapp\ContactGroupController;
use App\Http\Controllers\Api\Whatsapp\DashboardController;
use App\Http\Controllers\Api\Whatsapp\FormController;
use App\Http\Controllers\Api\Whatsapp\FormPublicController;
use App\Http\Controllers\Api\Whatsapp\GroupController;
use App\Http\Controllers\Api\Whatsapp\MessageController;
use App\Http\Controllers\Api\Whatsapp\MessageHistoryController;
use App\Http\Controllers\Api\Whatsapp\PublicApi\GroupApiController;
use App\Http\Controllers\Api\Whatsapp\PublicApi\InstanceApiController;
use App\Http\Controllers\Api\Whatsapp\PublicApi\MessageApiController;
use App\Http\Controllers\Api\Whatsapp\ShortLinkController;
use App\Http\Controllers\Api\Whatsapp\TemplateController;
use App\Http\Controllers\Api\Whatsapp\WebhookController;
use App\Http\Controllers\Api\Whatsapp\WhatsappApiSettingsController;
use Illuminate\Support\Facades\Route;

// Loaded via Route::prefix('whatsapp')->group(...) in routes/api.php — every
// path here is already under /api/whatsapp/*.

Route::middleware(['auth:sanctum', 'trial.active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/channels', [ChannelController::class, 'index']);
    Route::post('/channels', [ChannelController::class, 'store']);
    Route::get('/channels/{channel}', [ChannelController::class, 'show']);
    Route::patch('/channels/{channel}', [ChannelController::class, 'update']);
    Route::post('/channels/{channel}/connect', [ChannelController::class, 'connect']);
    Route::get('/channels/{channel}/qr', [ChannelController::class, 'qr']);
    Route::post('/channels/{channel}/pairing-code', [ChannelController::class, 'pairingCode']);
    Route::get('/channels/{channel}/status', [ChannelController::class, 'status']);
    Route::post('/channels/{channel}/disconnect', [ChannelController::class, 'disconnect']);
    Route::delete('/channels/{channel}', [ChannelController::class, 'destroy']);

    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/messages/history', [MessageHistoryController::class, 'index']);

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);

    Route::get('/autoresponders', [AutoresponderController::class, 'index']);
    Route::post('/autoresponders', [AutoresponderController::class, 'store']);
    Route::patch('/autoresponders/{autoresponder}', [AutoresponderController::class, 'update']);
    Route::delete('/autoresponders/{autoresponder}', [AutoresponderController::class, 'destroy']);

    Route::get('/chatbot-rules', [ChatbotRuleController::class, 'index']);
    Route::post('/chatbot-rules', [ChatbotRuleController::class, 'store']);
    Route::patch('/chatbot-rules/{rule}', [ChatbotRuleController::class, 'update']);
    Route::delete('/chatbot-rules/{rule}', [ChatbotRuleController::class, 'destroy']);

    Route::get('/contact-groups', [ContactGroupController::class, 'index']);
    Route::post('/contact-groups', [ContactGroupController::class, 'store']);
    Route::patch('/contact-groups/{group}', [ContactGroupController::class, 'update']);
    Route::delete('/contact-groups/{group}', [ContactGroupController::class, 'destroy']);

    Route::get('/contact-groups/{group}/contacts', [ContactController::class, 'index']);
    Route::post('/contact-groups/{group}/contacts', [ContactController::class, 'store']);
    Route::post('/contact-groups/{group}/contacts/import', [ContactController::class, 'import']);
    Route::post('/contact-groups/{group}/contacts/import-whatsapp', [ContactController::class, 'importFromWhatsapp']);
    Route::get('/contact-groups/{group}/contacts/export', [ContactController::class, 'export']);
    Route::post('/contact-groups/{group}/contacts/validate', [ContactController::class, 'validateNumbers']);
    Route::delete('/contact-groups/{group}/contacts/invalid', [ContactController::class, 'deleteInvalid']);
    Route::delete('/contact-groups/{group}/contacts/bulk', [ContactController::class, 'bulkDestroy']);
    Route::delete('/contact-groups/{group}/contacts/{contact}', [ContactController::class, 'destroy']);

    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::patch('/templates/{template}', [TemplateController::class, 'update']);
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);

    Route::get('/short-links', [ShortLinkController::class, 'index']);
    Route::post('/short-links', [ShortLinkController::class, 'store']);
    Route::delete('/short-links/{link}', [ShortLinkController::class, 'destroy']);

    Route::get('/groups', [GroupController::class, 'index']);
    Route::get('/groups/{group}/export', [GroupController::class, 'export']);

    Route::get('/call-responder/settings', [CallResponderController::class, 'settings']);
    Route::put('/call-responder/settings', [CallResponderController::class, 'updateSettings']);
    Route::get('/call-responder/history', [CallResponderController::class, 'history']);

    Route::get('/forms', [FormController::class, 'index']);
    Route::get('/forms/dashboard', [FormController::class, 'dashboard']);
    Route::post('/forms', [FormController::class, 'store']);
    Route::patch('/forms/{form}', [FormController::class, 'update']);
    Route::delete('/forms/{form}', [FormController::class, 'destroy']);
    Route::get('/forms/{form}/submissions', [FormController::class, 'submissions']);
    Route::get('/forms/{form}/submissions/export', [FormController::class, 'exportSubmissions']);

    Route::get('/api-settings', [WhatsappApiSettingsController::class, 'show']);
    Route::put('/api-settings', [WhatsappApiSettingsController::class, 'update']);
});

// Server-to-server only (Node bridge → Laravel), authenticated via HMAC
// signature in WebhookController — deliberately outside the auth:sanctum group.
Route::post('/webhook', WebhookController::class);

// Public, unauthenticated — a visitor filling out a published form has no
// account/session. See FormPublicController's own docblock for how every
// tenant-scoped model touched here is scoped explicitly instead.
Route::get('/forms/{slug}/public', [FormPublicController::class, 'show']);
Route::post('/forms/{slug}/submit', [FormPublicController::class, 'submit']);

// Public REST API (screenshots 38-50) — external automation tools (Zapier,
// n8n, etc.), never a browser session. Auth is instance_id + access_token
// (see ResolvesPublicApiChannel), deliberately outside auth:sanctum — same
// reasoning as the bridge webhook and the public form endpoints above.
Route::post('/create_instance', [InstanceApiController::class, 'createInstance']);
Route::get('/get_qrcode', [InstanceApiController::class, 'getQrCode']);
Route::post('/set_webhook', [InstanceApiController::class, 'setWebhook']);
Route::post('/reboot', [InstanceApiController::class, 'reboot']);
Route::post('/reset_instance', [InstanceApiController::class, 'resetInstance']);
Route::post('/reconnect', [InstanceApiController::class, 'reconnect']);

Route::post('/send', [MessageApiController::class, 'send']);
Route::post('/send_template', [MessageApiController::class, 'sendTemplate']);
Route::post('/send_by_template', [MessageApiController::class, 'sendByTemplate']);

Route::get('/get_groups', [GroupApiController::class, 'getGroups']);
Route::post('/send_group', [GroupApiController::class, 'sendGroup']);
