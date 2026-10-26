<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access denied</title>
@vite(['resources/css/app.css'])</head><body><div class="auth-wrap"><div class="auth-card text-center card card-body p-4">
<div class="display-3 fw-bold text-primary">403</div><h1 class="h5">Access denied</h1><p class="text-secondary">{{ (isset($exception) && $exception->getMessage() && 403 === 403) ? $exception->getMessage() : "You don't have permission to view this page." }}</p>
<a href="{{ url('/') }}" class="btn btn-primary btn-sm">Go to dashboard</a></div></div></body></html>
