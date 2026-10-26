<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Session expired</title>
@vite(['resources/css/app.css'])</head><body><div class="auth-wrap"><div class="auth-card text-center card card-body p-4">
<div class="display-3 fw-bold text-primary">419</div><h1 class="h5">Session expired</h1><p class="text-secondary">{{ (isset($exception) && $exception->getMessage() && 419 === 403) ? $exception->getMessage() : "Your session expired. Please go back, refresh and try again." }}</p>
<a href="{{ url('/') }}" class="btn btn-primary btn-sm">Go to dashboard</a></div></div></body></html>
