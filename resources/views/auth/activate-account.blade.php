<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Activer votre compte | FOSER</title></head>
<body><main class="auth-shell"><section class="auth-card"><h1>Activer votre compte</h1><p>Définissez votre mot de passe pour {{ $email }}.</p><form method="POST" action="{{ route('invitation.activate', ['token' => $token]) }}">@csrf<label>Mot de passe<input type="password" name="password" required minlength="8"></label><label>Confirmer le mot de passe<input type="password" name="password_confirmation" required minlength="8"></label><button class="button button-dark" type="submit">Activer le compte</button></form>@if ($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif</section></main></body>
</html>
