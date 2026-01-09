{{-- resources/views/emails/reset_code.blade.php --}}
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Recuperação de senha</title>
  <style>
    body { font-family: Arial, sans-serif; color:#222 }
    .box { max-width:520px; margin:0 auto; padding:24px; border:1px solid #eee; border-radius:8px; }
    .code { font-size:24px; font-weight:700; letter-spacing:4px; }
    .muted { color:#666; font-size:12px; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Recuperação de senha</h2>
    <p>Olá, {{ $name }}!</p>
    <p>Use o código abaixo para redefinir sua senha. Ele é válido por <strong>5 minutos</strong>.</p>
    <p class="code">{{ $code }}</p>
    <p class="muted">Se você não solicitou, pode ignorar este e-mail.</p>
  </div>
</body>
</html>
