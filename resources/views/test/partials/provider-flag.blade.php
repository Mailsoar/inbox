@php
    /**
     * Drapeau du pays du fournisseur, tel que renseigné dans admin/providers.
     *
     * Image SVG (flag-icons) plutôt qu'emoji : Windows n'affiche pas les
     * emoji drapeaux et les remplace par les lettres « US » ou « FR ».
     */
    $country = \App\Models\EmailProvider::countryForLabel($provider);
    $countryName = $country ? \Locale::getDisplayRegion('-' . strtoupper($country), app()->getLocale()) : null;
@endphp

@if ($country && is_file(public_path("images/flags/{$country}.svg")))
    <img src="/images/flags/{{ $country }}.svg" alt="{{ $countryName }}" title="{{ $countryName }}"
         class="w-5 shrink-0" style="height: 15px; border-radius: 2px; box-shadow: 0 0 0 1px rgb(0 0 0 / .1)" loading="lazy">
@endif
