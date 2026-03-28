<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload: ' . $e->getMessage());
            return response()->json(['error' => 'invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook invalid signature: ' . $e->getMessage());
            return response()->json(['error' => 'invalid signature'], 400);
        }

        try {
            switch ($event->type) {

                case 'customer.subscription.updated':
                case 'customer.subscription.deleted': {
                    $sub = $event->data->object;
                    $subscriptionId = $sub->id ?? null;
                    $status = $sub->status ?? null;

                    if (!$subscriptionId) break;

                    $facility = Facility::where('stripe_subscription_id', $subscriptionId)->first();
                    if (!$facility) break;
                    if ($facility->isFree()) break;

                    if ($event->type === 'customer.subscription.deleted') {
                        $status = 'canceled';
                    }

                    $isActive = in_array($status, ['active', 'trialing'], true);

                    $facility->subscription_status = $status ?? $facility->subscription_status;
                    $facility->is_active = $isActive;
                    $facility->subscription_ended_at = $isActive ? null : now();
                    $facility->save();
                    break;
                }

                case 'invoice.payment_failed': {
                    $invoice = $event->data->object;
                    $subscriptionId = $invoice->subscription ?? null;
                    if (!$subscriptionId) break;

                    $facility = Facility::where('stripe_subscription_id', $subscriptionId)->first();
                    if (!$facility) break;
                    if ($facility->isFree()) break;

                    $facility->subscription_status = 'past_due';
                    $facility->is_active = false;
                    $facility->save();
                    break;
                }

                case 'invoice.payment_succeeded': {
                    $invoice = $event->data->object;
                    $subscriptionId = $invoice->subscription ?? null;
                    if (!$subscriptionId) break;

                    $facility = Facility::where('stripe_subscription_id', $subscriptionId)->first();
                    if (!$facility) break;
                    if ($facility->isFree()) break;

                    $facility->subscription_status = 'active';
                    $facility->is_active = true;
                    $facility->subscription_ended_at = null;
                    $facility->save();
                    break;
                }

                default:
                    break;
            }

            return response()->json(['status' => 'ok'], 200);

        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler error: ' . $e->getMessage(), [
                'type' => $event->type ?? null,
            ]);
            return response()->json(['status' => 'ok'], 200);
        }
    }
}
