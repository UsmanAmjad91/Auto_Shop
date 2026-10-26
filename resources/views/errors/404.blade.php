<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Not found</title>
@vite(['resources/css/app.css'])</head><body><div class="auth-wrap"><div class="auth-card text-center card card-body p-4">
<div class="display-3 fw-bold text-primary">404</div><h1 class="h5">Not found</h1><p class="text-secondary">{{ (isset($exception) && $exception->getMessage() && 404 === 403) ? $exception->getMessage() : "The page or record you're looking for doesn't exist." }}</p>
<a href="{{ url('/') }}" class="btn btn-primary btn-sm">Go to dashboard</a></div></div></body></html>
