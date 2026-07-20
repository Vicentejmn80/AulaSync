<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configura tu Perfil - Nova Academy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4F46E5;
            --secondary: #10B981;
            --accent: #8B5CF6;
            --dark: #1F2937;
            --light: #F9FAFB;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        .onboarding-container {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .progress-bar {
            height: 5px;
            background: #E5E7EB;
            position: relative;
        }
        
        .progress {
            height: 100%;
            background: var(--primary);
            transition: width 0.5s ease;
            width: 0%;
        }
        
        .onboarding-content {
            padding: 3rem;
        }
        
        .step {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .step.active {
            display: block;
        }
        
        .step-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            line-height: 40px;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .step-title {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .step-description {
            color: #6B7280;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 2rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .select-wrapper {
            position: relative;
        }
        
        .select-wrapper select {
            appearance: none;
            padding-right: 3rem;
        }
        
        .select-arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #6B7280;
        }
        
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .checkbox-item {
            position: relative;
        }
        
        .checkbox-item input {
            display: none;
        }
        
        .checkbox-item label {
            display: block;
            padding: 1rem;
            background: var(--light);
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .checkbox-item input:checked + label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 3rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #4338CA;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--light);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background: #E5E7EB;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .subject-tag {
            display: inline-block;
            background: var(--light);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin: 0.25rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .subject-tag.selected {
            background: var(--primary);
            color: white;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
        }
        
        .time-slot {
            padding: 1rem;
            background: var(--light);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .time-slot.selected {
            background: var(--primary);
            color: white;
        }
        
        @media (max-width: 768px) {
            .onboarding-content {
                padding: 2rem;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .time-slots {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="progress-bar">
            <div class="progress" id="progress"></div>
        </div>
        
 <div class="onboarding-content">
    <form method="POST" action="{{ route('onboarding.save') }}" id="onboardingForm">
        @csrf
        
        <!-- Paso 1: Información Básica -->
        <div class="step active" id="step1">
            <div class="step-header">
                <div class="step-number">1</div>
                <h2 class="step-title">Cuéntanos sobre ti</h2>
                <p class="step-description">Esta información nos ayudará a personalizar tus planificaciones</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nivel Educativo *</label>
                <div class="select-wrapper">
                    <select class="form-control" name="nivel_educativo" required>
                        <option value="">Selecciona un nivel</option>
                        <option value="preescolar">Preescolar</option>
                        <option value="primaria">Primaria</option>
                        <option value="secundaria">Secundaria</option>
                        <option value="bachillerato">Bachillerato</option>
                        <option value="universidad">Universidad</option>
                        <option value="adultos">Educación de Adultos</option>
                    </select>
                    <i class="fas fa-chevron-down select-arrow"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Materia(s) que enseñas *</label>
                <div class="checkbox-grid">
                    @foreach(['matematicas' => 'Matemáticas', 'ciencias' => 'Ciencias', 'lenguaje' => 'Lenguaje', 
                             'historia' => 'Historia', 'ingles' => 'Inglés', 'arte' => 'Arte', 
                             'musica' => 'Música', 'educacion_fisica' => 'Educación Física',
                             'tecnologia' => 'Tecnología', 'filosofia' => 'Filosofía'] as $value => $label)
                    <div class="checkbox-item">
                        <input type="checkbox" id="materia_{{ $value }}" name="materias[]" value="{{ $value }}">
                        <label for="materia_{{ $value }}">{{ $label }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Cursos/Grados *</label>
                <div class="checkbox-grid" id="gradesGrid">
                    <!-- Se llenará dinámicamente según el nivel -->
                </div>
            </div>
            
            <div class="buttons">
                <button type="button" class="btn btn-secondary" disabled>Anterior</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(2)">Siguiente <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>
        
        <!-- Paso 2: Horarios -->
        <div class="step" id="step2">
            <div class="step-header">
                <div class="step-number">2</div>
                <h2 class="step-title">Configura tus horarios</h2>
                <p class="step-description">¿Cuándo y cuánto tiempo tienes clases?</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Clases por semana</label>
                <input type="number" class="form-control" name="clases_semana" min="1" max="20" value="5">
            </div>
            
            <div class="form-group">
                <label class="form-label">Duración de cada clase (minutos)</label>
                <select class="form-control" name="duracion_clase">
                    <option value="45">45 minutos</option>
                    <option value="60" selected>60 minutos</option>
                    <option value="90">90 minutos</option>
                    <option value="120">120 minutos</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Días de clase preferidos</label>
                <div class="checkbox-grid">
                    @foreach(['lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles', 
                             'jueves' => 'Jueves', 'viernes' => 'Viernes', 'sabado' => 'Sábado'] as $value => $label)
                    <div class="checkbox-item">
                        <input type="checkbox" id="dia_{{ $value }}" name="dias[]" value="{{ $value }}" {{ in_array($value, ['lunes', 'martes', 'miercoles', 'jueves', 'viernes']) ? 'checked' : '' }}>
                        <label for="dia_{{ $value }}">{{ $label }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Horarios preferidos</label>
                <div class="checkbox-grid">
                    @foreach(['8-10' => '8:00 - 10:00', '10-12' => '10:00 - 12:00', 
                             '14-16' => '14:00 - 16:00', '16-18' => '16:00 - 18:00', 
                             '18-20' => '18:00 - 20:00'] as $value => $label)
                    <div class="checkbox-item">
                        <input type="checkbox" id="horario_{{ $value }}" name="horarios[]" value="{{ $value }}">
                        <label for="horario_{{ $value }}">{{ $label }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div class="buttons">
                <button type="button" class="btn btn-secondary" onclick="prevStep(1)"><i class="fas fa-arrow-left"></i> Anterior</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(3)">Siguiente <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>
        
        <!-- Paso 3: Estilo de Enseñanza -->
        <div class="step" id="step3">
            <div class="step-header">
                <div class="step-number">3</div>
                <h2 class="step-title">Tu estilo de enseñanza</h2>
                <p class="step-description">¿Cómo prefieres estructurar tus clases?</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Formato de planificación preferido</label>
                <select class="form-control" name="formato_planificacion">
                    <option value="inicio_desarrollo_cierre" selected>Inicio, Desarrollo, Cierre</option>
                    <option value="objetivos_actividades">Objetivos y Actividades</option>
                    <option value="preguntas_guia">Preguntas Guía</option>
                    <option value="proyecto_based">Basado en Proyectos</option>
                    <option value="flipped">Aula Invertida</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Incluir siempre en las planificaciones</label>
                <div class="checkbox-grid">
                    @foreach(['objetivos' => 'Objetivos de aprendizaje', 'materiales' => 'Lista de materiales',
                             'evaluacion' => 'Rúbrica de evaluación', 'adaptaciones' => 'Adaptaciones para NEE',
                             'tareas' => 'Tareas para casa', 'recursos' => 'Recursos digitales'] as $value => $label)
                    <div class="checkbox-item">
                        <input type="checkbox" id="incluir_{{ $value }}" name="incluir[]" value="{{ $value }}" checked>
                        <label for="incluir_{{ $value }}">{{ $label }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tono de las planificaciones</label>
                <div class="checkbox-grid">
                    <div class="checkbox-item">
                        <input type="radio" id="tono_formal" name="tono" value="formal">
                        <label for="tono_formal">Formal</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="radio" id="tono_amigable" name="tono" value="amigable" checked>
                        <label for="tono_amigable">Amigable</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="radio" id="tono_motivador" name="tono" value="motivador">
                        <label for="tono_motivador">Motivador</label>
                    </div>
                </div>
            </div>
            
            <div class="buttons">
                <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Anterior</button>
                <button type="submit" class="btn btn-primary">Completar <i class="fas fa-check"></i></button>
            </div>
        </div>
    </form>
</div>

    <script>
    let currentStep = 1;
    const totalSteps = 3;
    
    // Actualizar barra de progreso
    function updateProgress() {
        const progress = (currentStep / totalSteps) * 100;
        document.getElementById('progress').style.width = `${progress}%`;
    }
    
    // Cambiar de paso
    function changeStep(step) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
        currentStep = step;
        updateProgress();
    }
    
    function nextStep(step) {
        if (validateStep(currentStep)) {
            changeStep(step);
        }
    }
    
    function prevStep(step) {
        changeStep(step);
    }
    
    // Validar paso actual
    function validateStep(step) {
        if (step === 1) {
            const nivel = document.querySelector('[name="nivel_educativo"]').value;
            if (!nivel) {
                alert('Por favor selecciona un nivel educativo');
                return false;
            }
            
            const materias = document.querySelectorAll('[name="materias[]"]:checked');
            if (materias.length === 0) {
                alert('Por favor selecciona al menos una materia');
                return false;
            }
            
            const cursos = document.querySelectorAll('[name="cursos[]"]:checked');
            if (cursos.length === 0) {
                alert('Por favor selecciona al menos un curso/grado');
                return false;
            }
        }
        return true;
    }
    
    // Configurar grados según nivel educativo
    document.addEventListener('DOMContentLoaded', function() {
        const nivelSelect = document.querySelector('[name="nivel_educativo"]');
        const gradesGrid = document.getElementById('gradesGrid');
        
        function updateGrades() {
            const nivel = nivelSelect.value;
            gradesGrid.innerHTML = '';
            
            let grades = [];
            
            switch(nivel) {
                case 'preescolar':
                    grades = ['Pre-kínder', 'Kínder'];
                    break;
                case 'primaria':
                    grades = ['1°', '2°', '3°', '4°', '5°', '6°'];
                    break;
                case 'secundaria':
                    grades = ['7°', '8°', '9°'];
                    break;
                case 'bachillerato':
                    grades = ['10°', '11°', '12°'];
                    break;
                case 'universidad':
                    grades = ['Semestre 1', 'Semestre 2', 'Semestre 3', 'Semestre 4', 'Semestre 5+'];
                    break;
                default:
                    grades = [];
            }
            
            grades.forEach(grade => {
                const div = document.createElement('div');
                div.className = 'checkbox-item';
                div.innerHTML = `
                    <input type="checkbox" id="curso_${grade.replace(/[^a-zA-Z0-9]/g, '_')}" name="cursos[]" value="${grade}">
                    <label for="curso_${grade.replace(/[^a-zA-Z0-9]/g, '_')}">${grade}</label>
                `;
                gradesGrid.appendChild(div);
            });
        }
        
        nivelSelect.addEventListener('change', updateGrades);
        
        // Actualizar grados al cargar si ya hay un valor
        if (nivelSelect.value) {
            updateGrades();
        }
    });
    
    // Validar formulario completo al enviar
    document.getElementById('onboardingForm').addEventListener('submit', function(e) {
        // Validar paso 3
        const tono = document.querySelector('[name="tono"]:checked');
        if (!tono) {
            e.preventDefault();
            alert('Por favor selecciona un tono para las planificaciones');
            return false;
        }
        
        // Mostrar loading
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        submitBtn.disabled = true;
        
        return true;
    });
</script>