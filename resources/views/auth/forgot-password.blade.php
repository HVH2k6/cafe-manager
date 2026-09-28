<x-layouts.auth title="Quên mật khẩu">
    <h1 class="text-[40px] leading-tight font-bold tracking-tight">Quên mật khẩu?</h1>
    <p class="mt-2 text-slate-600">
        Vui lòng liên hệ quản lý của quán để được cấp lại mật khẩu cho tài khoản của bạn.
    </p>

    <a
        href="{{ route('login') }}"
        class="mt-8 flex h-13 w-full items-center justify-center rounded-lg bg-latte font-semibold text-white transition-colors hover:bg-latte-dark"
    >
        Quay lại đăng nhập
    </a>
</x-layouts.auth>
