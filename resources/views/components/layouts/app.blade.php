<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Nova Academy') }}</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Soft UI Professional Styles -->
    <style>
        :root {
            --primary-color: #7928ca;
            --secondary-color: #ff0080;
            --gradient-primary: linear-gradient(310deg, #7928ca 0%, #ff0080 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }
        
        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary, .bg-gradient-primary {
            background: var(--gradient-primary) !important;
            border: none !important;
            color: white !important;
        }

        .card {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 27px 0 rgba(0, 0, 0, 0.05);
        }
        
        /* Utility Classes */
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .text-primary-gradient { background: var(--gradient-primary); }
    </style>
    @pwaHead
    @stack('styles')
</head>
<body>
    {{ $slot }}

    <!-- Scripts Directos -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    @laravelPwa
    @pwaInstallButton
</body>
</html>