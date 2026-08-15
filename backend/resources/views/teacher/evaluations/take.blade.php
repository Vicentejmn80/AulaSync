<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $evaluation->title }}</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: radial-gradient(circle at top right, rgba(108,99,255,.2), transparent 30%), #F4F6FA; color: #1E293B; margin: 0; }
        .box { max-width: 860px; margin: 24px auto; background: rgba(255,255,255,.95); padding: 28px; border-radius: 24px; box-shadow: 0 14px 36px rgba(15,23,42,.1); border: 1px solid rgba(99,102,241,.18); }
        h1 { margin-top: 0; margin-bottom: 8px; font-size: 30px; }
        .lead { color: #475569; margin-bottom: 10px; }
        .meta { display:flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        .pill { background: #EEF2FF; color: #4338CA; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 700; }
        .q { margin: 18px 0; border: 1px solid #E2E8F0; border-radius: 14px; padding: 14px; background: #fff; }
        input, textarea { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 10px; border: 1px solid #CBD5E1; }
        button { background: #4F46E5; color: #fff; border: 0; border-radius: 999px; padding: 11px 18px; font-weight: 800; cursor: pointer; }
        button:disabled { opacity: .6; cursor:not-allowed; }
        .ok { color: #0F766E; font-weight: 700; }
        .err { color: #B45309; font-weight: 700; }
    </style>
</head>
<body>
<div class="box" x-data="takeApp()" x-cloak>
    <h1>{{ $evaluation->title }}</h1>
    <p class="lead">{{ $evaluation->instructions }}</p>
    <div class="meta">
        <span class="pill">Evaluación digital</span>
        <span class="pill">{{ $evaluation->questions->count() }} preguntas</span>
        <span class="pill">{{ $evaluation->total_points }} puntos</span>
    </div>
    <template x-if="!done">
        <form @submit.prevent="submit()">
            <label>Tu nombre</label>
            <input x-model="name" required>
            @foreach($evaluation->questions as $question)
                <div class="q">
                    <strong>{{ $loop->iteration }}.</strong> {{ $question->text }}
                    @if(in_array($question->type, ['multiple_choice', 'true_false']) && is_array($question->options))
                        @foreach($question->options as $option)
                            <label style="display:block;margin-top:6px;">
                                <input type="radio" name="q{{ $question->id }}" value="{{ $option }}" @change="answers[{{ $question->id }}] = '{{ addslashes($option) }}'" style="width:auto;margin-right:6px;">
                                {{ $option }}
                            </label>
                        @endforeach
                    @else
                        <textarea rows="3" x-model="answers[{{ $question->id }}]"></textarea>
                    @endif
                </div>
            @endforeach
            <button type="submit" :disabled="sending"><span x-text="sending ? 'Enviando...' : 'Enviar evaluación'"></span></button>
        </form>
    </template>
    <p class="ok" x-show="done" x-text="result"></p>
    <p class="err" x-show="error" x-text="error"></p>
</div>
<script>
function takeApp() {
    return {
        name: '',
        answers: {},
        done: false,
        result: '',
        error: '',
        sending: false,
        async submit() {
            this.error = '';
            this.sending = true;
            try {
                const res = await fetch('{{ route('evaluations.take.submit', $evaluation->public_token) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ student_name: this.name, answers: this.answers }),
                });
                const data = await res.json();
                this.done = true;
                this.result = data.success ? `Tu evaluación fue enviada correctamente. Puntaje automático: ${data.score}/${data.total}` : 'No se pudo enviar.';
                if (!data.success) this.error = data.error || 'Hubo un problema al enviar.';
            } catch (e) {
                this.error = 'Error de red. Intenta nuevamente.';
            } finally {
                this.sending = false;
            }
        }
    };
}
</script>
</body>
</html>
