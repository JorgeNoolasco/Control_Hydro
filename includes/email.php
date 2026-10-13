
<?php

declare(strict_types=1);

/**
 * Envia um e-mail HTML de recuperação de senha
 * usando a API HTTPS do Resend.
 */
function enviarRecuperacao(string $email, string $token): void
{
    $chave = ambiente('RESEND_API_KEY');
    $remetente = ambiente('MAIL_FROM');

    if (
        $chave === '' ||
        !filter_var($remetente, FILTER_VALIDATE_EMAIL) ||
        !extension_loaded('curl')
    ) {
        throw new RuntimeException('E-mail não configurado.');
    }

    // Mantém o token no fragmento da URL.
    $link = origemAplicacao() . '/redefinir_senha.php#token=' . $token;
    $linkHtml = htmlspecialchars(
        $link,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $ano = date('Y');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperação de senha - HidroControl</title>
</head>
<body style="margin:0;padding:0;background-color:#edf4fa;
             font-family:Arial,Helvetica,sans-serif;">

<table role="presentation" cellpadding="0" cellspacing="0"
       border="0" width="100%"
       style="background-color:#edf4fa;">
<tr>
<td align="center" style="padding:40px 16px;">

    <table role="presentation" cellpadding="0"
           cellspacing="0" border="0" width="100%"
           style="max-width:560px;background-color:#ffffff;
                  border-radius:14px;overflow:hidden;">

        <!-- CABEÇALHO -->
        <tr>
            <td align="center"
                style="background-color:#10385b;
                       padding:36px 24px;">

                <h1 style="margin:0;color:#ffffff;
                           font-size:30px;letter-spacing:.3px;">
                    &#128167; HidroControl
                </h1>

                <p style="color:#b7d9ef;
                          font-size:12px;
                          letter-spacing:2px;
                          margin:12px 0 0;">
                    MONITORAMENTO INTELIGENTE
                </p>
            </td>
        </tr>

        <!-- CONTEÚDO -->
        <tr>
            <td style="padding:36px 32px;">

                <h2 style="color:#18344d;
                           font-size:23px;
                           margin:0 0 24px;">
                    Redefinição de senha
                </h2>

                <p style="color:#42586e;
                          font-size:15px;
                          line-height:1.8;">
                    Olá!
                </p>

                <p style="color:#42586e;
                          font-size:15px;
                          line-height:1.8;">
                    Recebemos uma solicitação para
                    redefinir a senha da sua conta
                    no <strong>HidroControl</strong>.
                </p>

                <p style="color:#42586e;
                          font-size:15px;
                          line-height:1.8;">
                    Para criar uma nova senha e
                    recuperar o acesso, clique
                    no botão abaixo.
                </p>

                <!-- BOTÃO -->
                <table role="presentation"
                       cellpadding="0"
                       cellspacing="0"
                       border="0"
                       width="100%">
                    <tr>
                        <td align="center"
                            style="padding:25px 0;">

                            <a href="{$linkHtml}"
                               style="display:inline-block;
                                      background-color:#087abf;
                                      color:#ffffff;
                                      font-size:15px;
                                      font-weight:bold;
                                      text-decoration:none;
                                      padding:16px 28px;
                                      border-radius:8px;">
                                Redefinir minha senha
                            </a>

                        </td>
                    </tr>
                </table>

                <!-- AVISO -->
                <table role="presentation"
                       width="100%"
                       cellpadding="0"
                       cellspacing="0"
                       style="background-color:#eff7ff;
                              border-radius:8px;">
                    <tr>
                        <td style="padding:16px;
                                   color:#23577e;
                                   font-size:13px;
                                   line-height:1.6;">
                            &#9201; <strong>Atenção:</strong>
                            este link é válido por
                            apenas 30 minutos e pode
                            ser utilizado uma única vez.
                        </td>
                    </tr>
                </table>

                <p style="font-size:13px;
                          color:#718096;
                          line-height:1.7;
                          margin-top:28px;">
                    Se você não solicitou a
                    redefinição de senha,
                    pode ignorar esta mensagem.
                    Nenhuma alteração será realizada.
                </p>

                <hr style="border:0;
                           border-top:1px solid #e5edf4;
                           margin:28px 0;">

                <p style="font-size:12px;
                          color:#8192a3;
                          line-height:1.6;">
                    Este e-mail foi enviado
                    automaticamente pelo HidroControl.
                    Por segurança, nunca compartilhe
                    seu link de recuperação.
                </p>

            </td>
        </tr>

        <!-- RODAPÉ -->
        <tr>
            <td align="center"
                style="background-color:#f4f8fb;
                       padding:24px;">

                <p style="color:#18344d;
                          font-size:13px;
                          font-weight:bold;
                          margin:0;">
                    HidroControl
                </p>

                <p style="color:#8192a3;
                          font-size:12px;
                          margin:8px 0 0;">
                    &copy; {$ano} HidroControl.
                    Todos os direitos reservados.
                </p>

                <p style="color:#8192a3;
                          font-size:11px;
                          margin:8px 0 0;">
                    Segurança e monitoramento
                    em cada leitura.
                </p>
            </td>
        </tr>

    </table>

</td>
</tr>
</table>
</body>
</html>
HTML;

    // Versão texto para clientes sem suporte a HTML.
    $texto = "HidroControl - Recuperação de senha\n\n"
        . "Recebemos uma solicitação para redefinir sua senha.\n\n"
        . "Acesse o link abaixo:\n"
        . $link . "\n\n"
        . "O link expira em 30 minutos e pode ser usado uma vez.\n\n"
        . "Se não solicitou essa alteração, ignore este e-mail.";

    $curl = curl_init('https://api.resend.com/emails');

    if ($curl === false) {
        throw new RuntimeException('Falha ao iniciar envio.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $chave,
            'Content-Type: application/json',
            'User-Agent: HidroControl/1.0'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => $remetente,
            'to' => [$email],
            'subject' => 'Redefinição de senha | HidroControl',
            'html' => $html,
            'text' => $texto
        ], JSON_THROW_ON_ERROR),
    ]);

    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if (
        $resposta === false ||
        $status < 200 ||
        $status >= 300
    ) {
        throw new RuntimeException(
            'Falha no envio do e-mail.'
        );
    }
}
