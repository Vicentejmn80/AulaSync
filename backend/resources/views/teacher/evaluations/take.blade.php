<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $evaluation->title }}</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #F8F6F0; color: #2D2D3A; margin: 0; }
        .box { max-width: 720px; margin: 32px auto; background: #fff; padding: 28px; border-radius: 20px; box-shadow: 0 10px 28px rgba(0,0,0,.06); }
        h1 { margin-top: 0; }
        .q { margin: 18px 0; }
        input, textarea { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 10px; border: 1px solid #E8E6F0; }
        button { background: #6C63FF; color: #fff; border: 0; border-radius: 999px; padding: 10px 16px; font-weight: 800; cursor: pointer; }
        .ok { color: #159A79; font-weight: 700; }
    </style>
</head>
<body>
<div class="box" x-data="takeApp()" x-cloak>
    <h1>{{ $evaluation->title }}</h1>
    <p>{{ $evaluation->instructions }}</p>
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
                                <input type="radio" name="q{{ $question->id }}" value="{{ $option }}" @change="answers[{{ $question->id }}] = '{{ addslashes($option) }}'" style="width:auto">
                                {{ $option }}
                            </label>
                        @endforeach
                    @else
                        <textarea rows="3" x-model="answers[{{ $question->id }}]"></textarea>
                    @endif
                </div>
            @endforeach
            <button type="submit">Enviar</button>
        </form>
    </template>
    <p class="ok" x-show="done" x-text="result"></p>
</div>
<script>
function takeApp() {
    return {
        name: '',
        answers: {},
        done: false,
        result: '',
        async submit() {
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
            this.result = data.success ? `Enviado. Puntaje automático: ${data.score}/${data.total}` : 'No se pudo enviar.';
        }
    };
}
</script>
</body>
</html>
