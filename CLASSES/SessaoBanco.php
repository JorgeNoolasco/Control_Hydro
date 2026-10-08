<?php
declare(strict_types=1);

/**
 * Armazena sessões no banco para compartilhar CSRF e mensagens entre instâncias da Vercel.
 * O PHP chama estes métodos automaticamente após session_set_save_handler().
 * read() mantém o bloqueio da sessão até write(), destroy() ou close().
 */
class SessaoBanco implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    // Seleciona a sintaxe de upsert e a codificação de dados compatíveis com cada banco.
    private bool $postgres;

    /** Recebe o PDO compartilhado com as consultas e gravações da página. */
    public function __construct(private PDO $pdo)
    {
        $this->postgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    /** Não precisa abrir arquivos: a conexão já foi recebida no construtor. */
    public function open(string $path, string $name): bool { return true; }

    /** Desfaz uma transação que tenha ficado aberta e libera os bloqueios da sessão. */
    public function close(): bool
    {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        return true;
    }

    /** Recupera a sessão válida ou uma string vazia quando não existe ou já expirou. */
    public function read(string $id): string|false
    {
        // A transação serializa pedidos da mesma sessão, evitando reenvios simultâneos.
        $this->pdo->beginTransaction();
        // Cria a linha inicial sem sobrescrever dados de uma sessão já existente.
        $upsert = $this->postgres ? 'ON CONFLICT (id) DO NOTHING' : 'ON DUPLICATE KEY UPDATE id = id';
        $q = $this->pdo->prepare("INSERT INTO sessoes (id, dados, expira_em) VALUES (?, '', ?) $upsert");
        $q->execute([$id, time() + 7200]);
        // FOR UPDATE faz outro pedido da mesma sessão aguardar a conclusão deste.
        $q = $this->pdo->prepare('SELECT dados, expira_em FROM sessoes WHERE id = ? FOR UPDATE');
        $q->execute([$id]);
        $sessao = $q->fetch();
        if (!$sessao || (int) $sessao['expira_em'] <= time()) return '';
        // Base64 permite guardar bytes da serialização PHP em uma coluna TEXT do PostgreSQL.
        return $this->postgres ? (base64_decode($sessao['dados'], true) ?: '') : $sessao['dados'];
    }

    /** Insere/atualiza a sessão, renova a validade por duas horas e confirma a transação. */
    public function write(string $id, string $data): bool
    {
        $upsert = $this->postgres
            ? 'ON CONFLICT (id) DO UPDATE SET dados = EXCLUDED.dados, expira_em = EXCLUDED.expira_em'
            : 'ON DUPLICATE KEY UPDATE dados = VALUES(dados), expira_em = VALUES(expira_em)';
        $q = $this->pdo->prepare("INSERT INTO sessoes (id, dados, expira_em) VALUES (?, ?, ?) $upsert");
        $q->execute([$id, $this->postgres ? base64_encode($data) : $data, time() + 7200]);
        // Confirma também a leitura da usina salva pela página usando este mesmo PDO.
        if ($this->pdo->inTransaction()) $this->pdo->commit();
        return true;
    }

    /** Exclui somente a sessão informada e libera seu bloqueio no banco. */
    public function destroy(string $id): bool
    {
        $q = $this->pdo->prepare('DELETE FROM sessoes WHERE id = ?');
        $q->execute([$id]);
        if ($this->pdo->inTransaction()) $this->pdo->commit();
        return true;
    }

    /** Coleta sessões vencidas pela data armazenada e informa quantas foram apagadas. */
    public function gc(int $max_lifetime): int|false
    {
        $q = $this->pdo->prepare('DELETE FROM sessoes WHERE expira_em > 0 AND expira_em < ?');
        $q->execute([time()]);
        return $q->rowCount();
    }

    /** Usado pelo modo estrito do PHP para rejeitar IDs inexistentes ou expirados. */
    public function validateId(string $id): bool
    {
        $q = $this->pdo->prepare('SELECT 1 FROM sessoes WHERE id = ? AND expira_em > ?');
        $q->execute([$id, time()]);
        return (bool) $q->fetchColumn();
    }

    /** Renova a sessão mesmo quando o PHP detecta que os dados não foram alterados. */
    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
