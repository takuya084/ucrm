<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Stripe\StripeClient;
use App\Models\User;
use App\Models\Facility;
use App\Models\Staff;

class SubscribeController extends Controller
{
    public function create(string $session_id)
    {
        $stripe = new StripeClient(config('services.stripe.st_key'));

        try {
            $session = $stripe->checkout->sessions->retrieve($session_id);

            if (($session->mode ?? null) !== 'subscription' || ($session->payment_status ?? null) !== 'paid') {
                return redirect('/')
                    ->with('message', 'お支払いの完了を確認できませんでした。決済完了後に登録へ進んでください。');
            }

            $admin_email = $this->getNormalizedEmailFromSession($session);
        } catch (\Throwable $e) {
            Log::error('Stripe session retrieve failed (create)', [
                'session_id' => $session_id,
                'message' => $e->getMessage(),
            ]);

            return redirect('/')->with('error', 'Stripeセッション情報の取得に失敗しました。');
        }

        return Inertia::render('Subscribe/Create', [
            'session_id' => $session_id,
            'admin_email' => $admin_email,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'session_id'      => 'required|string',
            'facility_name'   => 'required|string|max:255',
            'admin_name'      => 'required|string|max:255',
            'admin_email'     => 'nullable|email',
            'address'         => 'nullable|string|max:255',
            'tel'             => 'nullable|string|max:50',
            'capacity_per_day' => 'nullable|integer|min:1',
        ]);

        $stripe = new StripeClient(config('services.stripe.st_key'));

        try {
            $session = $stripe->checkout->sessions->retrieve($request->session_id);

            $adminEmail = $this->getNormalizedEmailFromSession($session, $request->input('admin_email'));

            if (!filled($adminEmail)) {
                return back()->with('error', '決済時のメールアドレスが取得できませんでした。');
            }

            $subscriptionId = $this->assertPaidCheckoutSession($stripe, $request->session_id);
            $customerId = $session->customer ?? null;

            if (!$customerId) {
                return back()->with('error', 'Stripe情報の取得に失敗しました（customer）。');
            }

            // 二重登録チェック
            if (Facility::where('stripe_subscription_id', $subscriptionId)->exists()) {
                if (User::where('email', $adminEmail)->exists()) {
                    $status = Password::sendResetLink(['email' => $adminEmail]);
                    if ($status === Password::RESET_LINK_SENT) {
                        return $this->redirectDone('success',
                            'すでに登録済みです。パスワード設定用のメールを送りました。', $adminEmail);
                    }
                    return $this->redirectDone('error',
                        'すでに登録済みですが、メール送信に失敗しました。しばらくしてから再送ボタンをお試しください。', $adminEmail);
                }

                Log::error('Facility exists but user not found', [
                    'email' => $adminEmail, 'subscription_id' => $subscriptionId,
                ]);
                return $this->redirectDone('error',
                    '登録情報はありますが、ユーザーが見つかりません。管理者へ連絡してください。', $adminEmail);
            }

            // メールが既にユーザーとして存在
            if (User::where('email', $adminEmail)->exists()) {
                Password::sendResetLink(['email' => $adminEmail]);
                return $this->redirectDone('success',
                    'このメールアドレスは既に登録済みです。パスワード設定用のメールを送りました。', $adminEmail);
            }

            // 新規作成
            $result = DB::transaction(function () use ($request, $adminEmail, $customerId, $subscriptionId) {
                $facility = Facility::create([
                    'name'                       => $request->facility_name,
                    'address'                    => $request->address,
                    'tel'                        => $request->tel,
                    'capacity_per_day'           => $request->capacity_per_day ?? 10,
                    'billing_type'               => 'stripe',
                    'is_active'                  => true,
                    'subscription_status'        => 'active',
                    'stripe_checkout_session_id' => $request->session_id,
                    'stripe_customer_id'         => $customerId,
                    'stripe_subscription_id'     => $subscriptionId,
                ]);

                $user = User::create([
                    'name'              => $request->admin_name,
                    'email'             => $adminEmail,
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                ]);

                Staff::create([
                    'user_id'     => $user->id,
                    'facility_id' => $facility->id,
                    'name'        => $request->admin_name,
                    'role'        => 'admin',
                    'is_active'   => true,
                    'joined_at'   => now(),
                ]);

                return [$user, $facility];
            });

            [$admin, $facility] = $result;

            // メール送信（失敗しても登録自体は完了しているのでdone画面へ）
            try {
                $status = Password::sendResetLink(['email' => $adminEmail]);
            } catch (\Throwable $mailError) {
                Log::error('Password reset mail exception', [
                    'email' => $adminEmail, 'message' => $mailError->getMessage(),
                ]);
                return $this->redirectDone('error',
                    '登録は完了しましたが、パスワード設定メールの送信に失敗しました。下の「再送」ボタンから再送してください。', $adminEmail);
            }

            if ($status === Password::RESET_LINK_SENT) {
                return $this->redirectDone('success',
                    'メールを送りました。メール内のリンクからパスワードを設定してください。', $adminEmail);
            }

            Log::error('Password reset link send failed', [
                'email' => $adminEmail, 'status' => $status, 'facility_id' => $facility->id ?? null,
            ]);

            return $this->redirectDone('error',
                '登録は完了しましたが、パスワード設定メールの送信に失敗しました。下の「再送」ボタンから再送してください。', $adminEmail);

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Subscribe store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session_id' => $request->session_id ?? null,
            ]);

            return back()->with('error', '登録に失敗しました。');
        }
    }

    public function resendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim((string)$request->input('email')));

        if (!User::where('email', $email)->exists()) {
            return redirect()->route('subscribe.done')
                ->with('error', 'このメールアドレスのユーザーが見つかりませんでした。')
                ->with('login_email', $email);
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            return redirect()->route('subscribe.done')
                ->with('success', 'パスワード設定メールを再送しました。')
                ->with('login_email', $email);
        }

        if ($status === Password::RESET_THROTTLED) {
            return redirect()->route('subscribe.done')
                ->with('error', '短時間に再送が行われたため一時的に制限されています。1〜2分ほど待ってから再度お試しください。')
                ->with('login_email', $email);
        }

        return redirect()->route('subscribe.done')
            ->with('error', '再送に失敗しました。しばらくしてから再度お試しください。')
            ->with('login_email', $email);
    }

    private function assertPaidCheckoutSession(StripeClient $stripe, string $sessionId): string
    {
        $session = $stripe->checkout->sessions->retrieve($sessionId, []);

        if (($session->mode ?? null) !== 'subscription') {
            throw ValidationException::withMessages(['session_id' => '決済情報が不正です（mode）。']);
        }

        if (($session->payment_status ?? null) !== 'paid') {
            throw ValidationException::withMessages(['session_id' => 'お支払いが完了していません。']);
        }

        $lineItems = $stripe->checkout->sessions->allLineItems($sessionId, ['limit' => 10]);
        $expectedPriceId = config('services.stripe.price_id');

        $matched = false;
        foreach ($lineItems->data as $item) {
            if (($item->price->id ?? null) === $expectedPriceId) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            throw ValidationException::withMessages(['session_id' => '購入プランが正しく確認できません。']);
        }

        $subscriptionId = $session->subscription ?? null;

        if (!$subscriptionId) {
            throw ValidationException::withMessages(['session_id' => 'サブスクリプション情報が確認できません。']);
        }

        return (string)$subscriptionId;
    }

    private function getNormalizedEmailFromSession(object $session, ?string $fallback = null): string
    {
        $email = $session->customer_details->email
            ?? $session->customer_email
            ?? $fallback
            ?? null;

        $email = strtolower(trim((string)$email));
        return filled($email) ? $email : '';
    }

    private function redirectDone(?string $type, string $message, ?string $email = null)
    {
        $email = strtolower(trim((string)$email));

        $params = [
            'type' => $type ?? 'message',
            'message' => $message,
        ];
        if (filled($email)) {
            $params['email'] = $email;
        }

        return Inertia::location(route('subscribe.done', $params));
    }
}
