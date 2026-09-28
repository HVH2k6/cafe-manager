<x-layouts.auth title="Đăng nhập">
    <h1 class="text-[40px] leading-tight font-bold tracking-tight">Đăng nhập</h1>
    <p class="mt-2 text-slate-600">Dành cho quản lý và nhân viên của quán.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 flex flex-col gap-5" novalidate>
        @csrf

        <div class="flex flex-col gap-2">
            <label for="email" class="text-sm font-semibold">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                placeholder="ten@cafeabc.vn"
                autocomplete="username"
                autofocus
                required
                @class([
                    'h-12 w-full rounded-lg border bg-white px-4 text-ink placeholder:text-slate-500 focus:ring-3 focus:outline-none',
                    'border-foam focus:border-latte focus:ring-latte/15' => ! $errors->has('email'),
                    'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has('email'),
                ])
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
            >
            @error('email')
                <p id="email-error" class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="password" class="text-sm font-semibold">Mật khẩu</label>
            <div class="relative">
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Nhập mật khẩu"
                    autocomplete="current-password"
                    required
                    @class([
                        'h-12 w-full rounded-lg border bg-white pr-12 pl-4 text-ink placeholder:text-slate-500 focus:ring-3 focus:outline-none',
                        'border-foam focus:border-latte focus:ring-latte/15' => ! $errors->has('password'),
                        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has('password'),
                    ])
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                >
                <button
                    type="button"
                    data-password-toggle="password"
                    class="group absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-700 hover:text-ink"
                    aria-label="Hiện mật khẩu"
                    aria-pressed="false"
                >
                    <svg class="size-5 group-aria-pressed:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg class="hidden size-5 group-aria-pressed:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.7 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-2.2 3.1M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6" />
                        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2M2 2l20 20" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p id="password-error" class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember" class="flex cursor-pointer items-center gap-2 text-sm">
                <input
                    id="remember"
                    name="remember"
                    type="checkbox"
                    value="1"
                    @checked(old('remember'))
                    class="size-5 rounded border-slate-400 accent-latte"
                >
                Ghi nhớ đăng nhập
            </label>

            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-latte-dark hover:underline">
                Quên mật khẩu?
            </a>
        </div>

        <button
            type="submit"
            class="mt-1 h-13 w-full rounded-lg bg-latte font-semibold text-white transition-colors hover:bg-latte-dark focus-visible:ring-3 focus-visible:ring-latte/30 focus-visible:outline-none"
        >
            Đăng nhập
        </button>
    </form>
</x-layouts.auth>
