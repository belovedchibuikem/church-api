<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\CreateMessageConversationRequest;
use App\Http\Requests\Api\V1\User\StoreMessageRequest;
use App\Http\Resources\Api\V1\User\MessageConversationResource;
use App\Http\Resources\Api\V1\User\MessageResource;
use App\Models\Message;
use App\Models\MessageConversation;
use App\Models\Person;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function conversations(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $conversations = MessageConversation::query()
            ->whereHas('participants', fn ($query) => $query->where('people.id', $person->getKey()))
            ->with('participants:id,public_id')
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            MessageConversationResource::collection($conversations)->resolve($request),
        );
    }

    public function messages(Request $request, string $conversation): JsonResponse
    {
        $person = $this->person($request);
        $owned = $this->ownedConversation($person, $conversation);
        $messages = $owned->messages()
            ->with(['sender:id,public_id', 'conversation:id,public_id'])
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            $request,
            MessageResource::collection($messages)->resolve($request),
        );
    }

    public function storeMessage(StoreMessageRequest $request, string $conversation): JsonResponse
    {
        $person = $this->person($request);
        $owned = $this->ownedConversation($person, $conversation);
        $validated = $request->validated();

        $message = new Message;
        $message->forceFill([
            'conversation_id' => $owned->getKey(),
            'sender_person_id' => $person->getKey(),
            'body' => $validated['body'],
        ])->save();
        $owned->touch();
        $message->load(['sender:id,public_id', 'conversation:id,public_id']);

        return ApiResponse::success(
            $request,
            MessageResource::make($message)->resolve($request),
            status: 201,
        );
    }

    public function storeConversation(CreateMessageConversationRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $validated = $request->validated();
        $otherIds = collect($validated['participant_person_ids'])
            ->unique()
            ->values();

        $others = Person::query()
            ->whereIn('public_id', $otherIds)
            ->get();

        abort_unless($others->count() === $otherIds->count(), 422, 'One or more participants were not found.');
        abort_if(
            $others->contains(fn (Person $other): bool => (int) $other->getKey() === (int) $person->getKey()),
            422,
            'participant_person_ids must include at least one other person.',
        );

        $conversation = DB::transaction(function () use ($person, $others, $validated): MessageConversation {
            $conversation = new MessageConversation;
            $conversation->forceFill([
                'subject' => $validated['subject'] ?? null,
            ])->save();

            $participantKeys = $others->pluck('id')->push($person->getKey())->unique()->all();
            $conversation->participants()->sync($participantKeys);

            $message = new Message;
            $message->forceFill([
                'conversation_id' => $conversation->getKey(),
                'sender_person_id' => $person->getKey(),
                'body' => $validated['first_message'],
            ])->save();

            return $conversation->load('participants:id,public_id');
        });

        return ApiResponse::success(
            $request,
            MessageConversationResource::make($conversation)->resolve($request),
            status: 201,
        );
    }

    private function ownedConversation(Person $person, string $conversationPublicId): MessageConversation
    {
        return MessageConversation::query()
            ->where('public_id', $conversationPublicId)
            ->whereHas('participants', fn ($query) => $query->where('people.id', $person->getKey()))
            ->firstOrFail();
    }
}
