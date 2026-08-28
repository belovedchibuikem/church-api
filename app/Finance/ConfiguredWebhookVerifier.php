<?php

namespace App\Finance;

use App\Finance\Contracts\WebhookVerifier;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\Data\VerifiedPaymentWebhook;
use DateTimeImmutable;

class ConfiguredWebhookVerifier implements WebhookVerifier
{
    public function __construct(private readonly ResolvesActivePaymentConfiguration $configurations) {}

    public function verify(PaymentWebhookEnvelope $envelope): ?VerifiedPaymentWebhook
    {
        $configuration = $this->configurations->active();

        if ($configuration === null) {
            return null;
        }

        $expectedProvider = $configuration->active_provider->value;
        if ($envelope->providerCode !== $expectedProvider) {
            return null;
        }

        return match ($configuration->active_provider) {
            PaymentProvider::Paystack => $this->verifyPaystack($envelope, (string) $configuration->paystack_webhook_secret),
            PaymentProvider::Flutterwave => $this->verifyFlutterwave($envelope, (string) $configuration->flutterwave_webhook_secret),
            PaymentProvider::Stripe => $this->verifyStripe($envelope, (string) $configuration->stripe_webhook_secret),
        };
    }

    private function verifyPaystack(PaymentWebhookEnvelope $envelope, string $secret): ?VerifiedPaymentWebhook
    {
        $raw = $this->rawBody($envelope);
        if ($envelope->signature === null || $secret === '' || $raw === '') {
            return null;
        }

        $expected = hash_hmac('sha512', $raw, $secret);
        if (! hash_equals($expected, $envelope->signature)) {
            return null;
        }

        $event = (string) ($envelope->payload['event'] ?? '');
        $data = is_array($envelope->payload['data'] ?? null) ? $envelope->payload['data'] : [];
        $status = strtolower((string) ($data['status'] ?? ''));
        $type = $event === 'charge.success' || $status === 'success' ? 'payment_succeeded' : null;

        if ($type === null) {
            return null;
        }

        $intentId = (string) (data_get($data, 'metadata.payment_intent_id') ?: ($data['reference'] ?? ''));
        $amount = (int) ($data['amount'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        if ($intentId === '' || $amount < 1 || $currency === '') {
            return null;
        }

        return new VerifiedPaymentWebhook(
            type: $type,
            providerCode: PaymentProvider::Paystack->value,
            eventId: (string) ($envelope->eventId ?: ($data['id'] ?? $intentId)),
            paymentIntentPublicId: $intentId,
            providerReference: (string) ($data['reference'] ?? $intentId),
            amountMinor: $amount,
            currency: $currency,
            occurredAt: $this->occurredAt($data['paid_at'] ?? $data['transaction_date'] ?? null, $envelope->receivedAt),
        );
    }

    private function verifyFlutterwave(PaymentWebhookEnvelope $envelope, string $secret): ?VerifiedPaymentWebhook
    {
        if ($envelope->signature === null || $secret === '' || ! hash_equals($secret, $envelope->signature)) {
            return null;
        }

        $event = strtolower((string) ($envelope->payload['event'] ?? ''));
        $data = is_array($envelope->payload['data'] ?? null) ? $envelope->payload['data'] : [];
        $status = strtolower((string) ($data['status'] ?? ''));

        if ($event !== 'charge.completed' && $status !== 'successful') {
            return null;
        }

        $intentId = (string) (data_get($data, 'meta.payment_intent_id') ?: ($data['tx_ref'] ?? ''));
        $amountMajor = (float) ($data['amount'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        if ($intentId === '' || $amountMajor <= 0 || $currency === '') {
            return null;
        }

        return new VerifiedPaymentWebhook(
            type: 'payment_succeeded',
            providerCode: PaymentProvider::Flutterwave->value,
            eventId: (string) ($envelope->eventId ?: ($data['id'] ?? $intentId)),
            paymentIntentPublicId: $intentId,
            providerReference: (string) ($data['flw_ref'] ?? $data['id'] ?? $intentId),
            amountMinor: (int) round($amountMajor * 100),
            currency: $currency,
            occurredAt: $this->occurredAt($data['created_at'] ?? null, $envelope->receivedAt),
        );
    }

    private function verifyStripe(PaymentWebhookEnvelope $envelope, string $secret): ?VerifiedPaymentWebhook
    {
        $raw = $this->rawBody($envelope);
        if ($envelope->signature === null || $secret === '' || $raw === '') {
            return null;
        }

        if (! $this->validStripeSignature($envelope->signature, $raw, $secret)) {
            return null;
        }

        $type = (string) ($envelope->payload['type'] ?? '');
        $object = is_array(data_get($envelope->payload, 'data.object')) ? data_get($envelope->payload, 'data.object') : [];

        if ($type !== 'checkout.session.completed' && $type !== 'payment_intent.succeeded') {
            return null;
        }

        $intentId = (string) (data_get($object, 'metadata.payment_intent_id')
            ?: ($object['client_reference_id'] ?? ''));
        $amount = (int) ($object['amount_total'] ?? $object['amount'] ?? 0);
        $currency = strtoupper((string) ($object['currency'] ?? ''));

        if ($intentId === '' || $amount < 1 || $currency === '') {
            return null;
        }

        return new VerifiedPaymentWebhook(
            type: 'payment_succeeded',
            providerCode: PaymentProvider::Stripe->value,
            eventId: (string) ($envelope->payload['id'] ?? $envelope->eventId),
            paymentIntentPublicId: $intentId,
            providerReference: (string) ($object['id'] ?? $intentId),
            amountMinor: $amount,
            currency: $currency,
            occurredAt: $this->occurredAt(
                isset($object['created']) ? '@'.$object['created'] : null,
                $envelope->receivedAt,
            ),
        );
    }

    private function validStripeSignature(string $header, string $payload, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function rawBody(PaymentWebhookEnvelope $envelope): string
    {
        $raw = $envelope->payload['_raw'] ?? null;

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        $encoded = json_encode($envelope->payload, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    private function occurredAt(mixed $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if (is_string($value) && $value !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception) {
                return $fallback;
            }
        }

        return $fallback;
    }
}
