<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Action not allowed</title>
@vite(['resources/css/app.css'])</head><body><div class="auth-wrap"><div class="auth-card text-center card card-body p-4">
<i class="bi bi-exclamation-octagon display-4 text-warning"></i><h1 class="h5 mt-2">This action can't be completed</h1>
<p class="text-secondary">{{ isset($exception) && $exception->getMessage() ? $exception->getMessage() : 'The request could not be processed.' }}</p>
<a href="{{ url()->previous() }}" class="btn btn-primary btn-sm">Go back</a></div></div></body></html>
