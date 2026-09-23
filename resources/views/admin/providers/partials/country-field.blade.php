{{-- Pays du fournisseur : affiché en drapeau à côté des boîtes dans les résultats --}}
<div class="row mb-3">
    <div class="col-md-6">
        <label for="country" class="form-label">Pays</label>
        <select name="country" id="country" class="form-select @error('country') is-invalid @enderror">
            <option value="">— Non renseigné —</option>
            @foreach (\App\Models\EmailProvider::countryOptions() as $code => $label)
                <option value="{{ $code }}" {{ old('country', $current) === $code ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Drapeau affiché à côté de la boîte dans les résultats des tests.</small>
        @error('country')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 d-flex align-items-center">
        <img id="country-flag-preview" src="{{ ($c = old('country', $current)) ? '/images/flags/' . $c . '.svg' : '' }}"
             alt="" style="height: 30px; border-radius: 3px; box-shadow: 0 0 0 1px rgba(0,0,0,.1); {{ $c ? '' : 'display:none' }}">
    </div>
</div>
<script>
    document.getElementById('country').addEventListener('change', function () {
        const img = document.getElementById('country-flag-preview');
        img.style.display = this.value ? '' : 'none';
        if (this.value) img.src = '/images/flags/' + this.value + '.svg';
    });
</script>
