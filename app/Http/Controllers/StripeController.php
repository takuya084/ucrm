<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function createSession(Request $request)
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.st_key'));

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => config('services.stripe.price_id'),
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => url('/success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => url('/cancel'),
        ]);

        return response()->json(['id' => $session->id]);
    }

    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        return redirect()->route('subscribe.create', ['session_id' => $sessionId]);
    }

    public function cancel()
    {
        return redirect('/')->with('message', 'お支払いがキャンセルされました。');
    }
}
