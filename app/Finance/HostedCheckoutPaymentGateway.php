<?php

namespace App\Finance;

use App\Exceptions\PaymentGatewayException;
use App\Finance\Contracts\PaymentGateway;
use App\Models\PaymentIntent;
use App\Models\PaymentProviderConfiguration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HostedCheckoutPaymentGateway implements PaymentGateway
{
    public function __construct(private readonly PaymentProviderConfiguration $configuration) {}

    public function providerCode(): string
    {
        return $this->configuration->active_provider->value;
    }

    public function initiate(PaymentIntent $paymentIntent): array
    {
        $paymentIntent->loadMissing('payer.user');

        return match ($this->configuration->active_provider) {
            PaymentProvider::Paystack => $this->initiatePaystack($paymentIntent),
            PaymentProvider::Flutterwave => $this->initiateFlutterwave($paymentIntent),
            PaymentProvider::Stripe => $this->initiateStripe($paymentIntent),
        };
    }

    /** @return array{provider_reference: string, client_payload: array<string, mixed>} */
    private function initiatePaystack(PaymentIntent $intent): array
    {
        $email = $this->payerEmail($intent);
        $response = Http::withToken((string) $this->configuration->paystack_secret_key)
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $intent->amount_minor,
                'currency' => $intent->currency,
                'reference' => $intent->public_id,
                'callback_url' => $this->webCallbackUrl($intent),
                'metadata' => [
                    'payment_intent_id' => $intent->public_id,
                    'purpose_code' => $intent->purpose_code,
                    'cancel_action' => $this->mobileCallbackUrl($intent),
                ],
            ]);

        $this->ensureSuccessful($response, 'PAYSTACK_INITIALIZE_FAILED');
        $data = (array) $response->json('data', []);
        $checkoutUrl = (string) ($data['authorization_url'] ?? '');
        $reference = (string) ($data['reference'] ?? $intent->public_id);

        if ($checkoutUrl === '') {
            throw new PaymentGatewayException('PAYSTACK_CHECKOUT_URL_MISSING', 'Paystack did not return a checkout URL.');
        }

        return $this->payload(
            $intent,
            $reference,
            $checkoutUrl,
            (string) $this->configuration->paystack_public_key,
            [
                'access_code' => $data['access_code'] ?? null,
                'reference' => $reference,
            ],
        );
    }

    /** @return array{provider_reference: string, client_payload: array<string, mixed>} */
    private function initiateFlutterwave(PaymentIntent $intent): array
    {
        $email = $this->payerEmail($intent);
        $response = Http::withToken((string) $this->configuration->flutterwave_secret_key)
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $intent->public_id,
                'amount' => round($intent->amount_minor / 100, 2),
                'currency' => $intent->currency,
                'redirect_url' => $this->webCallbackUrl($intent),
                'customer' => [
                    'email' => $email,
                    'name' => $intent->payer?->user?->name ?? 'Family House member',
                ],
                'customizations' => [
                    'title' => 'Family House Connect',
                    'description' => $intent->purpose_code,
                ],
                'meta' => [
                    'payment_intent_id' => $intent->public_id,
                    'purpose_code' => $intent->purpose_code,
                ],
            ]);

        $this->ensureSuccessful($response, 'FLUTTERWAVE_INITIALIZE_FAILED');
        $link = (string) $response->json('data.link', '');

        if ($link === '') {
            throw new PaymentGatewayException('FLUTTERWAVE_CHECKOUT_URL_MISSING', 'Flutterwave did not return a checkout URL.');
        }

        return $this->payload(
            $intent,
            $intent->public_id,
            $link,
            (string) $this->configuration->flutterwave_public_key,
        );
    }

    /** @return array{provider_reference: string, client_payload: array<string, mixed>} */
    private function initiateStripe(PaymentIntent $intent): array
    {
        $email = $this->payerEmail($intent);
        $response = Http::withToken((string) $this->configuration->stripe_secret_key)
            ->asForm()
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $this->webCallbackUrl($intent).'&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->webCallbackUrl($intent).'&cancelled=1',
                'client_reference_id' => $intent->public_id,
                'customer_email' => $email,
                'metadata[payment_intent_id]' => $intent->public_id,
                'metadata[purpose_code]' => $intent->purpose_code,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($intent->currency),
                'line_items[0][price_data][unit_amount]' => $intent->amount_minor,
                'line_items[0][price_data][product_data][name]' => 'Family House '.$intent->purpose_code,
            ]);

        $this->ensureSuccessful($response, 'STRIPE_INITIALIZE_FAILED');
        $checkoutUrl = (string) $response->json('url', '');
        $sessionId = (string) $response->json('id', $intent->public_id);
        $clientSecret = $response->json('client_secret');

        if ($checkoutUrl === '') {
            throw new PaymentGatewayException('STRIPE_CHECKOUT_URL_MISSING', 'Stripe did not return a checkout URL.');
        }

        return $this->payload(
            $intent,
            $sessionId,
            $checkoutUrl,
            (string) $this->configuration->stripe_publishable_key,
            [
                'session_id' => $sessionId,
                'client_secret' => is_string($clientSecret) ? $clientSecret : null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array{provider_reference: string, client_payload: array<string, mixed>}
     */
    private function payload(
        PaymentIntent $intent,
        string $reference,
        string $checkoutUrl,
        string $publicKey,
        array $extras = [],
    ): array {
        return [
            'provider_reference' => $reference,
            'client_payload' => array_filter([
                'provider' => $this->providerCode(),
                'checkout_mode' => 'redirect',
                'checkout_url' => $checkoutUrl,
                'public_key' => $publicKey,
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency,
                'intent_id' => $intent->public_id,
                'success_url' => $this->checkoutCallbackUrl($intent),
                'mobile_return_url' => $this->mobileCallbackUrl($intent),
                ...$extras,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    private function payerEmail(PaymentIntent $intent): string
    {
        $email = $intent->payer?->user?->email;

        if (! is_string($email) || $email === '') {
            throw new PaymentGatewayException(
                'PAYER_EMAIL_MISSING',
                'A verified account email is required to start checkout.',
            );
        }

        return $email;
    }

    private function checkoutCallbackUrl(PaymentIntent $intent): string
    {
        return $this->prefersMobileReturn()
            ? $this->mobileCallbackUrl($intent)
            : $this->webCallbackUrl($intent);
    }

    private function prefersMobileReturn(): bool
    {
        $channel = strtolower((string) (
            request()?->header('X-Client-Channel')
            ?: request()?->input('checkout_return')
            ?: 'web'
        ));

        return $channel === 'mobile';
    }

    private function webCallbackUrl(PaymentIntent $intent): string
    {
        $base = rtrim((string) config('finance.callbacks.web'), '/');

        return $base.(str_contains($base, '?') ? '&' : '?').'intent='.$intent->public_id;
    }

    private function mobileCallbackUrl(PaymentIntent $intent): string
    {
        $base = rtrim((string) config('finance.callbacks.mobile'), '/');

        return $base.(str_contains($base, '?') ? '&' : '?').'id='.$intent->public_id;
    }

    private function ensureSuccessful(Response $response, string $failureCode): void
    {
        if ($response->successful()) {
            $status = $response->json('status');
            if ($status === false || $status === 'error') {
                throw new PaymentGatewayException(
                    $failureCode,
                    (string) ($response->json('message') ?: 'The payment provider rejected checkout initialization.'),
                );
            }

            return;
        }

        throw new PaymentGatewayException(
            $failureCode,
            'The payment provider rejected checkout initialization.',
        );
    }
}
