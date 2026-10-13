<!-- Encerra o conteúdo aberto pelo cabeçalho e identifica a finalidade educacional. -->
</main>
<!-- O ano é calculado no servidor, dispensando atualização manual do rodapé. -->
<footer class="rodape">
    <p><strong>HidroControl</strong> &copy; <?= date('Y') ?> <span>Projeto educacional</span></p>
    <p>Dados inseridos manualmente. Os alertas usam regras simuladas.</p>
</footer>
</body>

</html>
<?php
// A sessão usa a mesma conexão PDO: confirme antes de liberar o buffer HTML.
if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) throw new RuntimeException('Falha de sessão.');
?>