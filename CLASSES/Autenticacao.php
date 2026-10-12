<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/seguranca.php';

/** Serviços de segurança independentes de HTTP; todas as consultas usam parâmetros. */
final class Autenticacao
{
    public const ACOES = [
        'login_sucesso',
        'login_falha',
        'login_bloqueado',
        'logout',
        'sessao_expirada',
        'leitura_cadastrada',
        'leitura_editada',
        'leitura_excluida',
        'usuario_criado',
        'permissoes_alteradas',
        'usuario_atualizado',
        'perfil_atualizado',
        'admin_provisionado',
        'recuperacao_solicitada',
        'recuperacao_bloqueada',
        'email_falha',
        'senha_redefinida',
        'confirmacao_falha'
    ];
    // Hash de uma senha aleatória descartada: equaliza o custo para contas inexistentes.
    private const HASH_INEXISTENTE = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(private PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') throw new RuntimeException('Autenticação requer PostgreSQL.');
    }

    private function consulta(string $sql, array $params = []): PDOStatement
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($params);
        return $q;
    }

    public function usuario(string $id): ?array
    {
        return $this->consulta('SELECT id, nome, email, perfil, ativo, versao_sessao FROM usuarios WHERE id = ?', [uuidValido($id)])->fetch() ?: null;
    }

    /** Revalida a autorização dentro da transação de escrita; a versão vem da sessão do servidor. */
    public function autorizar(array $ator, string $permissao): array
    {
        $u = $this->consulta('SELECT id, nome, email, perfil, ativo, versao_sessao FROM usuarios WHERE id = ? FOR SHARE', [uuidValido($ator['id'])])->fetch();
        if (!$u || !$u['ativo'] || (int) $u['versao_sessao'] !== (int) $ator['versao_sessao']) throw new ErroHttp(401, 'Entre novamente para continuar.');
        if (!pode($u, $permissao)) throw new ErroHttp(403, 'Você não tem permissão para esta operação.');
        return $u;
    }

    public function auditar(string $acao, ?string $usuario = null, ?string $recurso = null, array $detalhes = []): void
    {
        if (!in_array($acao, self::ACOES, true)) throw new LogicException('Ação de auditoria inválida.');
        // Lista fechada: jamais registra POST, senha, token, cookie, IP ou e-mail em texto claro.
        $detalhes = array_intersect_key($detalhes, array_flip(['perfil_anterior', 'perfil_novo', 'ativo']));
        $this->consulta(
            'INSERT INTO logs_auditoria (usuario_id, acao, recurso_id, ip_hash, detalhes) VALUES (?, ?, ?, ?, CAST(? AS jsonb))',
            [$usuario, $acao, $recurso, identificadorPrivado('ip', ipCliente()), json_encode($detalhes, JSON_THROW_ON_ERROR)]
        );
    }

    /** Locks transacionais funcionam com o transaction pooler e serializam contagens concorrentes. */
    private function limitar(string $email, string $ip, string $finalidade, int $maxConta = 5): array
    {
        $conta = identificadorPrivado('email', strtolower(trim($email)));
        $rede = identificadorPrivado('ip', $ip);
        $locks = [$finalidade . ':conta:' . $conta, $finalidade . ':ip:' . $rede];
        sort($locks);
        foreach ($locks as $lock) $this->consulta('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$lock]);
        $params = [$finalidade, $conta];
        $nConta = (int) $this->consulta("SELECT count(*) FROM tentativas_login WHERE finalidade = ? AND identificador_hash = ? AND sucesso = false AND criado_em > clock_timestamp() - interval '15 minutes'", $params)->fetchColumn();
        $nIp = (int) $this->consulta("SELECT count(*) FROM tentativas_login WHERE finalidade = ? AND ip_hash = ? AND sucesso = false AND criado_em > clock_timestamp() - interval '15 minutes'", [$finalidade, $rede])->fetchColumn();
        return [$nConta >= $maxConta || $nIp >= 30, $conta, $rede];
    }

    private function tentativa(string $conta, string $ip, string $finalidade, bool $sucesso): void
    {
        $this->consulta('INSERT INTO tentativas_login (identificador_hash, ip_hash, finalidade, sucesso) VALUES (?, ?, ?, CAST(? AS boolean))', [$conta, $ip, $finalidade, $sucesso ? 'true' : 'false']);
    }

    public function login(string $email, string $senha, string $ip, string $finalidade = 'login'): array
    {
        if (!in_array($finalidade, ['login', 'confirmacao'], true)) throw new LogicException('Finalidade inválida.');
        return transacao($this->pdo, function () use ($email, $senha, $ip, $finalidade) {
            [$bloqueado, $conta, $rede] = $this->limitar($email, $ip, $finalidade);
            if ($bloqueado) {
                $this->auditar('login_bloqueado');
                return ['usuario' => null, 'bloqueado' => true];
            }
            $u = $this->consulta('SELECT * FROM usuarios WHERE email = ? FOR UPDATE', [strtolower(trim($email))])->fetch();
            $correta = password_verify($senha, $u['senha_hash'] ?? self::HASH_INEXISTENTE);
            $sucesso = $u && $u['ativo'] && $correta;
            $this->tentativa($conta, $rede, $finalidade, (bool) $sucesso);
            if (!$sucesso) {
                $this->auditar($finalidade === 'login' ? 'login_falha' : 'confirmacao_falha', $u['id'] ?? null);
                return ['usuario' => null, 'bloqueado' => false];
            }
            if (password_needs_rehash($u['senha_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $this->consulta('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [hashSenha($senha), $u['id']]);
            }
            if ($finalidade === 'login') $this->auditar('login_sucesso', $u['id']);
            unset($u['senha_hash']);
            return ['usuario' => $u, 'bloqueado' => false];
        });
    }

    /** O callback de transporte permite testar a recuperação sem nenhuma chamada externa. */
    public function recuperar(string $email, string $ip, callable $enviar): void
    {
        transacao($this->pdo, function () use ($email, $ip, $enviar) {
            [$bloqueado, $conta, $rede] = $this->limitar($email, $ip, 'recuperacao', 3);
            if ($bloqueado) {
                $this->auditar('recuperacao_bloqueada');
                return;
            }
            $this->tentativa($conta, $rede, 'recuperacao', false);
            $u = $this->consulta('SELECT id, email, ativo FROM usuarios WHERE email = ? FOR UPDATE', [strtolower(trim($email))])->fetch();
            $this->auditar('recuperacao_solicitada', $u['id'] ?? null);
            $token = bin2hex(random_bytes(32));
            if (!$u || !$u['ativo']) return;
            $hash = hash('sha256', $token);
            $this->consulta('UPDATE tokens_redefinicao_senha SET usado_em = clock_timestamp() WHERE usuario_id = ? AND usado_em IS NULL', [$u['id']]);
            $this->consulta("INSERT INTO tokens_redefinicao_senha (usuario_id, token_hash, expira_em) VALUES (?, ?, clock_timestamp() + interval '30 minutes')", [$u['id'], $hash]);
            try {
                $enviar($u['email'], $token);
            } catch (Throwable $ex) {
                // Não propaga respostas do provedor ou URLs sensíveis para logs/resposta pública.
                $this->consulta('UPDATE tokens_redefinicao_senha SET usado_em = clock_timestamp() WHERE token_hash = ?', [$hash]);
                $this->auditar('email_falha', $u['id']);
                error_log('HidroControl: envio de recuperação indisponível.');
            }
        });
    }

    public function redefinir(string $token, string $senha): bool
    {
        validarNovaSenha($senha);
        if (!preg_match('/^[0-9a-f]{64}$/D', $token)) return false;
        return transacao($this->pdo, function () use ($token, $senha) {
            $hash = hash('sha256', $token);
            $id = $this->consulta('SELECT usuario_id FROM tokens_redefinicao_senha WHERE token_hash = ?', [$hash])->fetchColumn();
            if (!$id) return false;
            // Sempre trava usuário antes do token, como recuperar(), evitando inversão de locks.
            $u = $this->consulta('SELECT ativo FROM usuarios WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$u || !$u['ativo']) return false;
            $t = $this->consulta('SELECT id FROM tokens_redefinicao_senha WHERE token_hash = ? AND usado_em IS NULL AND expira_em > clock_timestamp() FOR UPDATE', [$hash])->fetchColumn();
            if (!$t) return false;
            $this->consulta('UPDATE usuarios SET senha_hash = ?, versao_sessao = versao_sessao + 1, atualizado_em = clock_timestamp() WHERE id = ?', [hashSenha($senha), $id]);
            $this->consulta('UPDATE tokens_redefinicao_senha SET usado_em = clock_timestamp() WHERE usuario_id = ? AND usado_em IS NULL', [$id]);
            $this->auditar('senha_redefinida', $id);
            return true;
        });
    }

    private function travaAdministracao(): void
    {
        $this->consulta("SELECT pg_advisory_xact_lock(hashtextextended('hidrocontrol:administracao', 0))");
    }

    private function inserirUsuario(string $nome, string $email, string $senha, string $perfil): string
    {
        $nome = nomeValido($nome);
        $email = emailValido($email);
        if (!isset(PERFIS[$perfil])) throw new InvalidArgumentException('Perfil inválido.');
        return $this->consulta('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?, ?, ?, ?) RETURNING id', [$nome, $email, hashSenha($senha), $perfil])->fetchColumn();
    }

    public function provisionar(string $nome, string $email, string $senha): string
    {
        if (PHP_SAPI !== 'cli') throw new ErroHttp(404, 'Página não encontrada.');
        return transacao($this->pdo, function () use ($nome, $email, $senha) {
            $this->travaAdministracao();
            if ((int) $this->consulta('SELECT count(*) FROM usuarios')->fetchColumn() !== 0) throw new InvalidArgumentException('Já existem usuários. Use o gerenciamento autenticado.');
            $id = $this->inserirUsuario($nome, $email, $senha, 'administrador');
            $this->auditar('admin_provisionado', $id);
            return $id;
        });
    }

    public function criarUsuario(array $ator, string $nome, string $email, string $senha, string $perfil): string
    {
        return transacao($this->pdo, function () use ($ator, $nome, $email, $senha, $perfil) {
            $this->travaAdministracao();
            $this->autorizar($ator, 'administrar');
            $id = $this->inserirUsuario($nome, $email, $senha, $perfil);
            $this->auditar('usuario_criado', $ator['id'], $id, ['perfil_novo' => $perfil]);
            return $id;
        });
    }

    public function alterarUsuario(array $ator, string $id, string $perfil, bool $ativo): void
    {
        uuidValido($id);
        if (!isset(PERFIS[$perfil])) throw new InvalidArgumentException('Perfil inválido.');
        transacao($this->pdo, function () use ($ator, $id, $perfil, $ativo) {
            $this->travaAdministracao();
            $this->autorizar($ator, 'administrar');
            $u = $this->consulta('SELECT perfil, ativo FROM usuarios WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$u) throw new ErroHttp(404, 'Usuário não encontrado.');
            if ($u['perfil'] === 'administrador' && $u['ativo'] && ($perfil !== 'administrador' || !$ativo)) {
                if ((int) $this->consulta("SELECT count(*) FROM usuarios WHERE perfil = 'administrador' AND ativo = true")->fetchColumn() <= 1) {
                    throw new InvalidArgumentException('Mantenha pelo menos um administrador ativo.');
                }
            }
            $this->consulta('UPDATE usuarios SET perfil = ?, ativo = CAST(? AS boolean), versao_sessao = versao_sessao + 1, atualizado_em = clock_timestamp() WHERE id = ?', [$perfil, $ativo ? 'true' : 'false', $id]);
            $this->consulta('UPDATE tokens_redefinicao_senha SET usado_em = clock_timestamp() WHERE usuario_id = ? AND usado_em IS NULL', [$id]);
            $this->auditar(
                $u['perfil'] !== $perfil ? 'permissoes_alteradas' : 'usuario_atualizado',
                $ator['id'],
                $id,
                ['perfil_anterior' => $u['perfil'], 'perfil_novo' => $perfil, 'ativo' => $ativo]
            );
        });
    }

    public function atualizarPerfil(array $ator, array $entrada): void
    {
        if (array_diff(array_keys($entrada), ['nome', 'csrf'])) throw new ErroHttp(403, 'Apenas o nome pode ser alterado pelo perfil.');
        $nome = nomeValido(textoEntrada($entrada, 'nome', 120));
        transacao($this->pdo, function () use ($ator, $nome) {
            $this->autorizar($ator, 'consultar');
            $this->consulta('UPDATE usuarios SET nome = ?, atualizado_em = clock_timestamp() WHERE id = ?', [$nome, $ator['id']]);
            $this->auditar('perfil_atualizado', $ator['id']);
        });
    }
}
