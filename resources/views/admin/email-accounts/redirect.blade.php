<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={{ $url }}">
    <title>Redirection...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h3>{{ $message ?? 'Redirection en cours...' }}</h3>
        <p>Si vous n'êtes pas redirigé automatiquement, <a href="{{ $url }}">cliquez ici</a>.</p>
    </div>
    
    <script>
        // Forcer la redirection avec JavaScript
        window.location.href = "{{ $url }}";
    </script>
</body>
</html>