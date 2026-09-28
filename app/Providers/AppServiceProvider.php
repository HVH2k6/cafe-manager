<?php

namespace App\Providers;

use App\Models\QrSession;
use App\Models\User;
use App\Services\PayOSService;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PayOSService::class, fn (): PayOSService => new PayOSService(
            config('services.payos.client_id'),
            config('services.payos.api_key'),
            config('services.payos.checksum_key'),
            config('services.payos.base_url'),
            config('services.payos.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * The owner may do everything. Other users pass an ability shaped like a
         * permission key ("orders.view") when their role grants it; anything else
         * falls through to the gates and policies defined below.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isOwner()) {
                return true;
            }

            return str_contains($ability, '.') && $user->hasPermission($ability) ? true : null;
        });

        // Only the owner may manage permissions, so staff cannot grant themselves more.
        Gate::define('manage-permissions', fn (User $user): bool => $user->isOwner());

        // Staff with employee permissions may manage other staff, but never an owner account.
        Gate::define('manage-employee', fn (User $user, User $employee): bool => ! $employee->isOwner());

        // A customer's QR session, found by the random token in its URL (/s/{token}).
        Route::bind('qrSession', fn (string $token): QrSession => QrSession::where('session_token', $token)->first()
            ?? throw new HttpResponseException(response()->view('qr.unavailable', [
                'message' => 'Không tìm thấy phiên gọi món. Vui lòng quét lại mã QR trên bàn.',
                'tableNo' => null,
            ], 404)));

        $this->configureQrRateLimits();
    }

    /**
     * Anti-spam for the anonymous QR pages: per session token and per IP.
     */
    private function configureQrRateLimits(): void
    {
        $tooMany = fn (string $message): Closure => fn (Request $request): Response => $request->expectsJson()
            ? response()->json(['message' => $message], 429)
            : back()->with('error', $message);

        // Throttling runs before route model binding, so the parameter is usually still the raw token.
        $token = function (Request $request): string {
            $session = $request->route('qrSession');

            return $session instanceof QrSession ? $session->session_token : (string) $session;
        };

        RateLimiter::for('qr-start', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip())
            ->response($tooMany('Bạn thao tác quá nhanh, vui lòng thử lại sau ít phút.')));

        RateLimiter::for('qr-cart', fn (Request $request): Limit => Limit::perMinute(60)->by($token($request)));

        RateLimiter::for('qr-order', fn (Request $request): array => [
            Limit::perMinutes(10, 5)->by('session:'.$token($request))->response($tooMany('Bạn đã gửi nhiều đơn liên tiếp. Vui lòng chờ vài phút hoặc gọi nhân viên.')),
            Limit::perMinutes(10, 20)->by('ip:'.$request->ip())->response($tooMany('Có quá nhiều đơn từ thiết bị này. Vui lòng gọi nhân viên.')),
        ]);

        RateLimiter::for('qr-call', fn (Request $request): array => [
            Limit::perMinute(3)->by('session:'.$token($request))->response($tooMany('Nhân viên đã nhận được yêu cầu, vui lòng chờ trong giây lát.')),
            Limit::perMinute(10)->by('ip:'.$request->ip())->response($tooMany('Nhân viên đã nhận được yêu cầu, vui lòng chờ trong giây lát.')),
        ]);

        RateLimiter::for('qr-pay', fn (Request $request): Limit => Limit::perMinute(5)->by($token($request))
            ->response($tooMany('Vui lòng chờ một chút rồi thử lại.')));

        RateLimiter::for('qr-poll', fn (Request $request): Limit => Limit::perMinute(40)->by($token($request)));
    }
}
