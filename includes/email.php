<?php
declare(strict_types=1);

/** Transporte HTTPS síncrono e com timeout; nenhuma dependência de mail() no serverless. */
function enviarRecuperacao(string $email, string $token): void
{
    $chave = ambiente('RESEND_API_KEY');
    $remetente = ambiente('MAIL_FROM');
    if ($chave === '' || !filter_var($remetente, FILTER_VALIDATE_EMAIL) || !extension_loaded('curl')) throw new RuntimeException('E-mail não configurado.');
    // Fragmentos não são enviados ao servidor HTTP, evitando tokens nos access logs.
    $link = origemAplicacao() . '/redefinir_senha.php#token=' . $token;
    $curl = curl_init('https://api.resend.com/emails');
    curl_setopt_array($curl, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $chave, 'Content-Type: application/json', 'User-Agent: HidroControl/1.0'],
        CURLOPT_POSTFIELDS => json_encode(['from' => $remetente, 'to' => [$email], 'subject' => 'Redefina sua senha — HidroControl',
            'text' => "Use o link abaixo em até 30 minutos para redefinir sua senha.\n\n$link\n\nSe não solicitou esta alteração, ignore esta mensagem."], JSON_THROW_ON_ERROR),
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    unset($curl);
    if ($resposta === false || $status < 200 || $status >= 300) throw new RuntimeException('Falha no envio.');
}
