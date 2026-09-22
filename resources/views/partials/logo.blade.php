@php($class = $class ?? 'h-8 w-auto')

{{--
    Le logo est un JPEG sur fond blanc : « multiply » le fond avec le crème de
    la page, comme le fait la maquette, pour éviter le rectangle blanc.
--}}
<img src="/images/mailsoar-logo.jpg"
     alt="MailSoar"
     class="{{ $class }} block"
     style="mix-blend-mode: multiply">
