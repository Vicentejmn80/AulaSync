<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Nova Academy') }} - Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Custom Soft UI Styles (Embedded to ensure purple/white theme) -->
    <style>
        :root {
            --primary-color: #7928ca; /* Púrpura */
            --secondary-color: #ff0080; /* Rosa */
            --accent-color: #8B5CF6;
            --dark-color: #1F2937;
            --light-color: #F9FAFB;
            --gradient-primary: linear-gradient(310deg, #7928ca 0%, #ff0080 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary, .bg-gradient-primary {
            background: var(--gradient-primary) !important;
            border: none !important;
        }

        .card {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 27px 0 rgba(0, 0, 0, 0.05);
            background-color: #fff;
        }

        /* Estilos específicos de Soft UI Dashboard */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
    </style>
    @pwaHead
    @stack('styles')
</head>
<body>
    <!-- Navbar simplified from dashboard.blade.php -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="fas fa-robot me-2"></i>Nova Academy
            </a>
            
            <div class="ms-auto d-flex align-items-center">
                @auth
                <div class="user-profile">
                    <div class="user-avatar text-white">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="d-none d-md-block">
                        <h6 class="mb-0 text-xs font-weight-bold">{{ Auth::user()->name }}</h6>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-0 ms-2" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><a class="dropdown-item" href="#" onclick="alert('Perfil disponible pronto')"><i class="fas fa-user me-2 text-xs"></i>Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt me-2 text-xs"></i>Cerrar Sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Main Content Slot -->
    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-white py-4 mt-5 border-top">
        <div class="container text-center">
            <p class="mb-0 text-muted text-xs">© {{ date('Y') }} Nova Academy. Todos los derechos reservados.</p>
        </div>
    </footer>

    @stack('scripts')

    {{-- AI Assistant Bubble: only visible to teachers --}}
    @auth
        @if(auth()->user()->role === 'profesor')
            @include('components.ai-assistant-bubble')
        @endif
    @endauth

    @laravelPwa
    @pwaInstallButton
</body>
</html>
