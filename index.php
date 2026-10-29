<?php
require __DIR__ . '/config.php';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_action'])) {
    try {
        if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Sessão expirada. Atualize a página.');
        if ($_POST['auth_action'] === 'register') {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $display = trim((string)($_POST['display_name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if (!preg_match('/^[a-z0-9_]{3,24}$/', $username)) throw new RuntimeException('O usuário deve ter 3–24 caracteres: letras, números ou _.');
            if (mb_strlen($display) < 2 || mb_strlen($display) > 40) throw new RuntimeException('O nome de exibição deve ter de 2 a 40 caracteres.');
            if (strlen($password) < 8) throw new RuntimeException('Use uma senha com pelo menos 8 caracteres.');
            $stmt = db()->prepare('INSERT INTO users (username, display_name, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$username, $display, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            db()->prepare('UPDATE users SET online=1, last_seen=NOW() WHERE id=?')->execute([$_SESSION['user_id']]);
            header('Location: index.php'); exit;
        }
        if ($_POST['auth_action'] === 'login') {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username=?');
            $stmt->execute([$username]); $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) throw new RuntimeException('Usuário ou senha incorretos.');
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            db()->prepare('UPDATE users SET online=1, last_seen=NOW() WHERE id=?')->execute([$_SESSION['user_id']]);
            header('Location: index.php'); exit;
        }
    } catch (Throwable $e) {
        $notice = $e instanceof PDOException && str_contains($e->getMessage(), 'Duplicate')
            ? 'Esse nome de usuário já está em uso.' : $e->getMessage();
    }
}
if (isset($_GET['logout'])) {
    if (logged_in()) db()->prepare('UPDATE users SET online=0, last_seen=NOW() WHERE id=?')->execute([$_SESSION['user_id']]);
    $_SESSION = [];
    session_destroy();
    header('Location: index.php'); exit;
}
$me = null;
if (logged_in()) {
    $stmt = db()->prepare('SELECT id, username, display_name, avatar, status_message FROM users WHERE id=?');
    $stmt->execute([$_SESSION['user_id']]); $me = $stmt->fetch();
    if (!$me) { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(APP_NAME) ?></title><link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (!$me): ?>
<div class="auth-wrap">
  <div class="brand"><div class="butterfly">✿</div><div><h1>Meu MSN</h1><p>Conecte-se com quem importa.</p></div></div>
  <div class="auth-card">
    <div class="tabs"><button class="tab active" data-tab="login">Entrar</button><button class="tab" data-tab="register">Criar conta</button></div>
    <?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <form method="post" class="auth-form" id="login">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="auth_action" value="login">
      <label>Nome de usuário<input name="username" autocomplete="username" required maxlength="24" placeholder="seu_usuario"></label>
      <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
      <button class="primary" type="submit">Entrar no Messenger</button>
    </form>
    <form method="post" class="auth-form hidden" id="register">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="auth_action" value="register">
      <label>Como quer ser chamado?<input name="display_name" required minlength="2" maxlength="40" placeholder="Seu nome"></label>
      <label>Nome de usuário<input name="username" autocomplete="username" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" placeholder="seu_usuario"></label>
      <label>Senha (mínimo 8 caracteres)<input type="password" name="password" autocomplete="new-password" required minlength="8"></label>
      <button class="primary" type="submit">Criar minha conta</button>
    </form>
    <p class="smallprint">Nunca reutilize uma senha importante neste site.</p>
  </div>
  <p class="copyright">Um toque de nostalgia, feito para a web de hoje.</p>
</div>
<script>
document.querySelectorAll('.tab').forEach(b=>b.addEventListener('click',()=>{
 document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active',x===b));
 document.querySelectorAll('.auth-form').forEach(f=>f.classList.toggle('hidden',f.id!==b.dataset.tab));
}));
</script>
<?php else: ?>
<div class="app-shell">
<header class="topbar"><div class="mini-brand"><span class="butterfly">✿</span><strong>Meu MSN</strong></div><div class="top-actions"><span class="online-dot"></span><span>Conectado</span><a href="?logout=1">Sair</a></div></header>
<div class="app-layout">
<aside class="sidebar">
  <div class="profile">
    <div class="avatar big" id="myAvatar"><?= $me['avatar'] ? '<img src="'.h($me['avatar']).'" alt="Foto de perfil">' : '<span>'.h(mb_strtoupper(mb_substr($me['display_name'],0,1))).'</span>' ?></div>
    <div class="profile-info"><strong><?= h($me['display_name']) ?></strong><small>@<?= h($me['username']) ?></small><div class="status-line"><span class="online-dot"></span> Disponível</div></div>
  </div>
  <form id="statusForm" class="status-form"><input id="statusMessage" maxlength="120" value="<?= h($me['status_message']) ?>" aria-label="Mensagem de status"><button title="Salvar status">✓</button></form>
  <div class="side-heading">CONTATOS <span id="contactCount">0</span></div>
  <div id="contacts" class="contacts"><div class="empty-contacts">Adicione alguém para começar a conversar.</div></div>
  <div class="add-contact"><label for="addUsername">Adicionar contato</label><form id="addForm"><input id="addUsername" placeholder="nome_de_usuario" maxlength="24" required><button class="primary" type="submit">Adicionar</button></form><p id="addMessage" class="hint"></p></div>
  <div class="profile-upload"><label for="avatarFile">Sua foto (máx. 2 MB)</label><input type="file" id="avatarFile" accept="image/png,image/jpeg,image/webp"></div>
</aside>
<main class="chat-area">
  <section class="welcome" id="welcome"><div class="welcome-logo">✿</div><h2>Olá, <?= h($me['display_name']) ?>!</h2><p>Escolha um contato ou adicione alguém para começar.</p><div class="feature-chips"><span>💬 Mensagens</span><span>🟢 Status online</span><span>📱 Mobile</span></div></section>
  <section class="conversation hidden" id="conversation">
    <header class="chat-header"><div class="avatar" id="chatAvatar">?</div><div class="chat-person"><strong id="chatName">Contato</strong><small id="chatStatus">Offline</small></div><button id="closeChat" class="mobile-back" aria-label="Voltar aos contatos">←</button></header>
    <div class="messages" id="messages" aria-live="polite"></div>
    <form id="sendForm" class="send-form"><textarea id="messageInput" maxlength="2000" rows="1" placeholder="Digite uma mensagem..." required></textarea><button class="primary" type="submit">Enviar ➤</button></form>
    <div class="chat-foot">Enter para enviar · Shift+Enter para quebrar linha</div>
  </section>
</main>
</div>
</div>
<script>
window.MSN_CONFIG = {csrf: <?= json_encode(csrf_token()) ?>, me: <?= (int)$me['id'] ?>};
</script><script src="assets/app.js"></script>
<?php endif; ?>
</body></html>
