<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Communication\CommunicationAudienceRuleType;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationKind;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AttemptCommunicationDeliveryRequest;
use App\Http\Requests\Api\V1\Admin\CreateCommunicationAudienceRequest;
use App\Http\Requests\Api\V1\Admin\CreateCommunicationTemplateRequest;
use App\Http\Requests\Api\V1\Admin\CreateInAppNotificationRequest;
use App\Http\Requests\Api\V1\Admin\PrepareCommunicationBroadcastRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationNotification;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use App\Support\Communication\AttemptCommunicationDeliveryAction;
use App\Support\Communication\CommunicationPurpose;
use App\Support\Communication\CreateCommunicationAudienceAction;
use App\Support\Communication\CreateCommunicationTemplateAction;
use App\Support\Communication\CreateInAppNotificationAction;
use App\Support\Communication\PrepareCommunicationBroadcastAction;
use App\Support\Communication\ResolveCommunicationAudienceAction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function storeTemplate(CreateCommunicationTemplateRequest $request, CreateCommunicationTemplateAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $template = $this->execute(fn (): CommunicationTemplate => $action->handle(
            (string) $request->validated('code'),
            CommunicationChannel::from((string) $request->validated('channel')),
            (string) $request->validated('locale'),
            (string) $request->validated('subject'),
            (string) $request->validated('body'),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($template))->resolve($request), status: 201);
    }

    public function storeAudience(CreateCommunicationAudienceRequest $request, CreateCommunicationAudienceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $rules = [];
        foreach ((array) $request->validated('rules') as $rule) {
            $scope = null;
            if (($rule['scope_type'] ?? null) !== null && ($rule['scope_key'] ?? null) !== null) {
                $scope = new ScopeReference((string) $rule['scope_type'], (string) $rule['scope_key']);
            }
            $rules[] = [
                'type' => CommunicationAudienceRuleType::from((string) $rule['type']),
                'selector_key' => $rule['selector_key'] ?? null,
                'scope' => $scope,
            ];
        }
        $audience = $this->execute(fn (): CommunicationAudience => $action->handle(
            (string) $request->validated('code'),
            (string) $request->validated('name'),
            $rules,
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($audience))->resolve($request), status: 201);
    }

    public function prepareBroadcast(PrepareCommunicationBroadcastRequest $request, PrepareCommunicationBroadcastAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $template = CommunicationTemplate::query()->where('public_id', $request->validated('template_id'))->firstOrFail();
        $audience = CommunicationAudience::query()->where('public_id', $request->validated('audience_id'))->firstOrFail();
        $broadcast = $this->execute(fn (): CommunicationBroadcast => $action->handle(
            $template,
            $audience,
            CommunicationKind::from((string) $request->validated('kind')),
            CommunicationChannel::from((string) $request->validated('channel')),
            new CommunicationPurpose((string) $request->validated('purpose')),
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
            $request->validated('scheduled_at') === null ? null : CarbonImmutable::parse((string) $request->validated('scheduled_at')),
        ));
        $broadcast->load(['template:id,public_id,code,subject', 'audience:id,public_id,code,name']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($broadcast))->resolve($request), status: 201);
    }

    public function resolveBroadcast(Request $request, string $broadcast, ResolveCommunicationAudienceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = CommunicationBroadcast::query()->where('public_id', $broadcast)->firstOrFail();
        $updated = $this->execute(fn (): CommunicationBroadcast => $action->handle($target, $context->actor($request)));
        $updated->load(['template:id,public_id,code,subject', 'audience:id,public_id,code,name']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function attemptDelivery(AttemptCommunicationDeliveryRequest $request, string $recipient, AttemptCommunicationDeliveryAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = CommunicationRecipient::query()->where('public_id', $recipient)->firstOrFail();
        $attempt = $this->execute(fn (): CommunicationDeliveryAttempt => $action->handle(
            $target,
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));
        $attempt->load(['recipient:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($attempt))->resolve($request), status: 201);
    }

    public function createNotification(CreateInAppNotificationRequest $request, string $recipient, CreateInAppNotificationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = CommunicationRecipient::query()->where('public_id', $recipient)->firstOrFail();
        $notification = $this->execute(fn (): CommunicationNotification => $action->handle($target, $context->actor($request)));
        $notification->load(['person:id,public_id', 'user:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($notification))->resolve($request), status: 201);
    }
}
