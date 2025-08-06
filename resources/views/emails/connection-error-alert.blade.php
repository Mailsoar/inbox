<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Connection Failures</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #dc3545;
            color: white;
            padding: 20px;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .alert-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .account-list {
            margin: 20px 0;
        }
        .account-item {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .account-email {
            font-weight: bold;
            color: #007bff;
            font-size: 16px;
        }
        .account-provider {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            word-break: break-word;
        }
        .action-required {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .action-required h3 {
            margin-top: 0;
            color: #155724;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Email Connection Failures Detected</h1>
        </div>

        <div class="alert-info">
            <strong>Connection Check Summary:</strong><br>
            Date: {{ $checkedAt->format('Y-m-d H:i:s') }}<br>
            Failed Accounts: {{ $totalFailed }}
        </div>

        <h2>Failed Email Accounts</h2>
        <p>The following email accounts have been automatically disabled due to connection failures:</p>

        <div class="account-list">
            @foreach($failedAccounts as $failed)
                <div class="account-item">
                    <div>
                        <span class="account-email">{{ $failed['account']->email }}</span>
                        <span class="account-provider">{{ strtoupper($failed['account']->provider) }}</span>
                        @if($failed['account']->account_type)
                            <span class="account-provider" style="background-color: #28a745;">{{ strtoupper($failed['account']->account_type) }}</span>
                        @endif
                    </div>
                    <div class="error-message">
                        <strong>Error:</strong> {{ $failed['error'] }}
                    </div>
                    @if($failed['account']->oauth_token)
                        <div style="margin-top: 10px; font-size: 14px; color: #6c757d;">
                            <strong>OAuth Token Present:</strong> Yes<br>
                            @if($failed['account']->oauth_expires_at)
                                <strong>Token Expires:</strong> {{ \Carbon\Carbon::parse($failed['account']->oauth_expires_at)->format('Y-m-d H:i:s') }}
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="action-required">
            <h3>Action Required</h3>
            <p>Please investigate these connection failures. Common issues include:</p>
            <ul>
                <li>Expired OAuth tokens that couldn't be refreshed</li>
                <li>Revoked application access</li>
                <li>Account password changes</li>
                <li>Account suspension or security blocks</li>
                <li>Network connectivity issues</li>
            </ul>
            <p>
                <a href="{{ url('/admin/email-accounts') }}" class="btn">View Email Accounts in Admin Panel</a>
            </p>
        </div>

        <div class="footer">
            <p>This is an automated alert from Inbox by MailSoar system.<br>
            Connection checks run every 20 minutes to ensure email account availability.</p>
        </div>
    </div>
</body>
</html>