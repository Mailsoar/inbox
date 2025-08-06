@php
    app()->setLocale($language);
@endphp
<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.verification.title') }}</title>
    <style>
        /* Réinitialisation pour les clients email */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; }

        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f4f4f4 !important;
            font-family: Arial, sans-serif;
        }
        
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .content { padding: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    <!-- Wrapper table pour centrer le contenu -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <!-- Container principal -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;" class="container">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #2c5aa0; padding: 30px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Inbox by MailSoar</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td align="center" style="padding: 40px 30px;">
                            <h2 style="color: #333333; margin: 0 0 20px 0; font-size: 24px;">{{ __('emails.verification.title') }}</h2>
                            <p style="color: #666666; margin: 0 0 30px 0; font-size: 16px; line-height: 1.5;">{{ __('emails.verification.description') }}</p>
                            
                            <!-- Code box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <div style="background-color: #f8f9fa; border: 2px dashed #2c5aa0; border-radius: 8px; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 0 0 30px 0; padding: 20px; color: #2c5aa0; display: inline-block;">{{ $code }}</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666666; margin: 0 0 30px 0; font-size: 16px; line-height: 1.5;">{!! __('emails.verification.expires', ['duration' => __('emails.verification.expires_duration')]) !!}</p>
                            
                            <!-- Warning box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px; padding: 15px; margin: 0 0 30px 0;">
                                            <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.5;">
                                                <strong>{{ __('emails.verification.security_notice') }}</strong> {{ __('emails.verification.security_message') }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666666; margin: 0; font-size: 16px; line-height: 1.5;">{{ __('emails.verification.ignore') }}</p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f8f9fa; padding: 20px;">
                            <p style="color: #666666; margin: 0; font-size: 14px; line-height: 1.5;">
                                {{ __('emails.verification.footer') }}<br>
                                {{ __('emails.verification.copyright', ['year' => date('Y')]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>