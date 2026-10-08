<?php
declare(strict_types=1);

// Compartilha o token CSRF e a mensagem de sucesso entre instâncias da Vercel.
class SessaoBanco implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private bool $postgres;

    public function __construct(private PDO $pdo)
    {
        $this->postgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    public function open(string $path, string $name): bool { return true; }

    public function close(): bool
    {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        return true;
    }

    public function read(string $id): string|false
    {
        // A transação serializa pedidos da mesma sessão, evitando reenvios simultâneos.
        $this->pdo->beginTransaction();
        $upsert = $this->postgres ? 'ON CONFLICT (id) DO NOTHING' : 'ON DUPLICATE KEY UPDATE id = id';
        $q = $this->pdo->prepare("INSERT INTO sessoes (id, dados, expira_em) VALUES (?, '', ?) $upsert");
        $q->execute([$id, time() + 7200]);
        $q = $this->pdo->prepare('SELECT dados, expira_em FROM sessoes WHERE id = ? FOR UPDATE');
        $q->execute([$id]);
        $sessao = $q->fetch();
        if (!$sessao || (int) $sessao['expira_em'] <= time()) return '';
        return $this->postgres ? (base64_decode($sessao['dados'], true) ?: '') : $sessao['dados'];
    }

    public function write(string $id, string $data): bool
    {
        $upsert = $this->postgres
            ? 'ON CONFLICT (id) DO UPDATE SET dados = EXCLUDED.dados, expira_em = EXCLUDED.expira_em'
            : 'ON DUPLICATE KEY UPDATE dados = VALUES(dados), expira_em = VALUES(expira_em)';
        $q = $this->pdo->prepare("INSERT INTO sessoes (id, dados, expira_em) VALUES (?, ?, ?) $upsert");
        $q->execute([$id, $this->postgres ? base64_encode($data) : $data, time() + 7200]);
        if ($this->pdo->inTransaction()) $this->pdo->commit();
        return true;
    }

    public function destroy(string $id): bool
    {
        $q = $this->pdo->prepare('DELETE FROM sessoes WHERE id = ?');
        $q->execute([$id]);
        if ($this->pdo->inTransaction()) $this->pdo->commit();
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $q = $this->pdo->prepare('DELETE FROM sessoes WHERE expira_em > 0 AND expira_em < ?');
        $q->execute([time()]);
        return $q->rowCount();
    }

    public function validateId(string $id): bool
    {
        $q = $this->pdo->prepare('SELECT 1 FROM sessoes WHERE id = ? AND expira_em > ?');
        $q->execute([$id, time()]);
        return (bool) $q->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
