<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance</title>
@vite(['resources/css/app.css'])</head><body><div class="auth-wrap"><div class="auth-card text-center card card-body p-4">
<i class="bi bi-cone-striped display-4 text-warning"></i><h1 class="h4 mt-2">We'll be back soon</h1><p class="text-secondary">{{ $message }}</p>
@auth<form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Sign out</button></form>@else<a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">Administrator sign in</a>@endauth
</div></div></body></html>
