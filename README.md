# Meu MSN — chat retrô em PHP + MySQL

Aplicação inicial de chat com visual inspirado no MSN Messenger clássico, cadastro/login, contatos, mensagens, status e foto de perfil.

## Requisitos
- PHP 8.1 ou superior (recomendado PHP 8.2+)
- MySQL 5.7+/MariaDB compatível
- Extensões PHP PDO MySQL, fileinfo e GD não são obrigatórias (a validação usa getimagesize)
- HTTPS recomendado/necessário para produção

## Instalação em hospedagem compartilhada
1. Crie um banco MySQL e um usuário no painel do provedor.
2. Abra o phpMyAdmin, selecione o banco e importe `schema.sql`.
3. Edite `config.php` e informe `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS`.
4. Envie os arquivos e a pasta `assets` para `public_html` (ou uma pasta do domínio).
5. Garanta que o PHP possa criar a pasta `uploads` na primeira troca de foto. Se necessário, crie `uploads` manualmente com permissão gravável pelo PHP (normalmente 755 ou conforme o provedor).
6. Acesse o endereço no navegador e crie uma conta de teste. Use HTTPS no site.

## Recursos incluídos
- Cadastro e login com `password_hash`/`password_verify`
- Sessão PHP e token CSRF para ações de alteração
- Adição de contato pelo nome de usuário (adiciona nos dois lados)
- Conversa individual com atualização periódica de mensagens
- Lista de contatos e indicação de online/offline
- Mensagem de status e upload de avatar JPG/PNG/WebP até 2 MB
- Layout responsivo para computador e celular

## Importante: escopo
Este é um MVP funcional, não uma réplica integral do MSN original. Não inclui chamadas de voz/vídeo, transferência de arquivos entre usuários, notificações push, criptografia ponta a ponta, recuperação de senha por e-mail, bloqueio/denúncia, grupos, moderação ou painel administrativo. Mensagens ficam armazenadas no banco de dados. O estado online é atualizado quando o usuário faz requisições ao chat; fechar o navegador pode levar até 45 segundos para aparecer offline.

Antes de abrir ao público, adicione recuperação de senha, limites de tentativas de login, rate limiting, política de privacidade/termos, backups e monitoramento. Não use esta versão para dados sensíveis. Configure HTTPS no painel do provedor e mantenha PHP/MySQL atualizados.

## Solução de problemas
- **Erro de conexão:** confira os dados MySQL e se o banco foi criado.
- **Erro 500:** consulte o log de erros PHP no painel do provedor.
- **Não envia foto:** confira o limite de upload do PHP e a permissão da pasta `uploads`.
- **Caracteres acentuados:** mantenha banco e tabelas em `utf8mb4`.
