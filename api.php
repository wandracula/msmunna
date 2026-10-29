<?php
require __DIR__ . '/config.php';
require_login();
$me = (int)$_SESSION['user_id'];
$action = (string)($_GET['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') verify_csrf();

try {
  if ($action === 'contacts') {
    db()->prepare('UPDATE users SET online=1,last_seen=NOW() WHERE id=?')->execute([$me]);
    $stmt = db()->prepare("SELECT u.id,u.username,u.display_name,u.avatar,u.status_message,
      IF(u.last_seen >= DATE_SUB(NOW(), INTERVAL 45 SECOND),1,0) AS online,
      (SELECT body FROM messages m WHERE (m.sender_id=u.id AND m.receiver_id=?)
        OR (m.sender_id=? AND m.receiver_id=u.id) ORDER BY m.id DESC LIMIT 1) AS last_message,
      (SELECT created_at FROM messages m WHERE (m.sender_id=u.id AND m.receiver_id=?)
        OR (m.sender_id=? AND m.receiver_id=u.id) ORDER BY m.id DESC LIMIT 1) AS last_message_at
      FROM contacts c JOIN users u ON u.id=c.contact_id WHERE c.user_id=?
      ORDER BY online DESC, u.display_name ASC");
    $stmt->execute([$me,$me,$me,$me,$me]);
    json_response(['ok'=>true,'contacts'=>$stmt->fetchAll()]);
  }
  if ($action === 'add_contact') {
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    if (!preg_match('/^[a-z0-9_]{3,24}$/', $username)) json_response(['ok'=>false,'error'=>'Digite um nome de usuário válido.'],400);
    $stmt=db()->prepare('SELECT id FROM users WHERE username=?'); $stmt->execute([$username]); $u=$stmt->fetch();
    if (!$u) json_response(['ok'=>false,'error'=>'Não encontramos esse usuário.'],404);
    $id=(int)$u['id']; if ($id===$me) json_response(['ok'=>false,'error'=>'Você não pode adicionar a si mesmo.'],400);
    $stmt=db()->prepare('INSERT IGNORE INTO contacts (user_id,contact_id) VALUES (?,?),(?,?)');
    $stmt->execute([$me,$id,$id,$me]);
    json_response(['ok'=>true,'message'=>'Contato adicionado!']);
  }
  if ($action === 'messages') {
    $other=(int)($_GET['with'] ?? 0); $after=max(0,(int)($_GET['after'] ?? 0));
    $stmt=db()->prepare('SELECT id FROM contacts WHERE user_id=? AND contact_id=?'); $stmt->execute([$me,$other]);
    if (!$stmt->fetch()) json_response(['ok'=>false,'error'=>'Esse usuário não está nos seus contatos.'],403);
    $stmt=db()->prepare("SELECT m.id,m.sender_id,m.receiver_id,m.body,DATE_FORMAT(m.created_at,'%H:%i') AS time,
      u.display_name AS sender_name FROM messages m JOIN users u ON u.id=m.sender_id
      WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)) AND m.id>?
      ORDER BY m.id ASC LIMIT 100");
    $stmt->execute([$me,$other,$other,$me,$after]);
    $user=db()->prepare('SELECT display_name,avatar,status_message,IF(last_seen>=DATE_SUB(NOW(),INTERVAL 45 SECOND),1,0) online FROM users WHERE id=?');
    $user->execute([$other]);
    json_response(['ok'=>true,'messages'=>$stmt->fetchAll(),'contact'=>$user->fetch()]);
  }
  if ($action === 'send') {
    $other=(int)($_POST['to'] ?? 0); $body=trim((string)($_POST['body'] ?? ''));
    if ($body==='' || mb_strlen($body)>2000) json_response(['ok'=>false,'error'=>'A mensagem deve ter entre 1 e 2000 caracteres.'],400);
    $stmt=db()->prepare('SELECT 1 FROM contacts WHERE user_id=? AND contact_id=?'); $stmt->execute([$me,$other]);
    if (!$stmt->fetch()) json_response(['ok'=>false,'error'=>'Adicione esse contato primeiro.'],403);
    $stmt=db()->prepare('INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)');
    $stmt->execute([$me,$other,$body]);
    json_response(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
  }
  if ($action === 'status') {
    $status=trim((string)($_POST['status'] ?? ''));
    if (mb_strlen($status)>120) json_response(['ok'=>false,'error'=>'O status pode ter até 120 caracteres.'],400);
    db()->prepare('UPDATE users SET status_message=? WHERE id=?')->execute([$status,$me]);
    json_response(['ok'=>true]);
  }
  if ($action === 'avatar') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'Escolha uma imagem válida.'],400);
    $file=$_FILES['avatar'];
    if ($file['size'] > MAX_AVATAR_BYTES) json_response(['ok'=>false,'error'=>'A imagem deve ter no máximo 2 MB.'],400);
    $info=@getimagesize($file['tmp_name']);
    if (!$info || !in_array($info['mime'],['image/jpeg','image/png','image/webp'],true)) json_response(['ok'=>false,'error'=>'Use uma imagem JPG, PNG ou WebP.'],400);
    $dir=__DIR__.'/uploads';
    if (!is_dir($dir) && !mkdir($dir,0755,true)) json_response(['ok'=>false,'error'=>'Não foi possível criar a pasta de imagens.'],500);
    if (!file_exists($dir.'/.htaccess')) file_put_contents($dir.'/.htaccess',"Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n");
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']];
    $name='avatar_'.$me.'_'.bin2hex(random_bytes(6)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) json_response(['ok'=>false,'error'=>'Falha ao salvar a imagem.'],500);
    $url='uploads/'.$name;
    $stmt=db()->prepare('SELECT avatar FROM users WHERE id=?'); $stmt->execute([$me]); $old=$stmt->fetchColumn();
    db()->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$url,$me]);
    if ($old && str_starts_with($old,'uploads/avatar_'.$me.'_')) { $oldPath=__DIR__.'/'.$old; if (is_file($oldPath)) @unlink($oldPath); }
    json_response(['ok'=>true,'avatar'=>$url]);
  }
  json_response(['ok'=>false,'error'=>'Ação não reconhecida.'],404);
} catch (Throwable $e) {
  error_log('MSN chat error: '.$e->getMessage());
  json_response(['ok'=>false,'error'=>'Ocorreu um erro no servidor. Confira a configuração e tente novamente.'],500);
}
