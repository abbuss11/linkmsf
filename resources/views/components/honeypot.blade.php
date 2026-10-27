{{-- Anti-spam: must stay empty, and the form must not be posted too fast. See ProtectAgainstSpam. --}}
<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
    <label for="field-{{ \App\Http\Middleware\ProtectAgainstSpam::TRAP_FIELD }}">Ne pas remplir</label>
    <input type="text" id="field-{{ \App\Http\Middleware\ProtectAgainstSpam::TRAP_FIELD }}" name="{{ \App\Http\Middleware\ProtectAgainstSpam::TRAP_FIELD }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Http\Middleware\ProtectAgainstSpam::TIME_FIELD }}" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->getTimestamp()) }}">
@error('form')
    <p class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>
@enderror
