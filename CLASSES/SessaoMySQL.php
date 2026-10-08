<?php
declare(strict_types=1);

// Compartilha o token CSRF e a mensagem de sucesso entre instâncias da Vercel.
class SessaoMySQL implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $pdo) {}

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
        $q = $this->pdo->prepare("INSERT INTO sessoes (id, dados, expira_em) VALUES (?, '', 0) ON DUPLICATE KEY UPDATE id = id");
        $q->execute([$id]);
        $q = $this->pdo->prepare('SELECT dados, expira_em FROM sessoes WHERE id = ? FOR UPDATE');
        $q->execute([$id]);
        $sessao = $q->fetch();
        return $sessao && (int) $sessao['expira_em'] > time() ? $sessao['dados'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $q = $this->pdo->prepare('UPDATE sessoes SET dados = ?, expira_em = ? WHERE id = ?');
        $q->execute([$data, time() + 7200, $id]);
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
