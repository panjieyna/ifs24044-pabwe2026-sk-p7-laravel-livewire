@if($errors->any())
<div role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><p class="font-semibold">Periksa kembali data Anda.</p><ul class="mt-2 list-disc pl-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
