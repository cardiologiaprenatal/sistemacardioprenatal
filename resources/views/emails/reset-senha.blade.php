<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha. Cardioprenatal</title>
</head>
<body style="margin:0;padding:0;background:#fdf6f6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf6f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid #ead9d9;overflow:hidden;">
                    <tr>
                        <td style="padding:16px 28px;background:#ffffff;border-bottom:1px solid #ead9d9;">
                            <img src="{{ asset('images/logo-cardioprenatal.jpg') }}" alt="CardioPrenatal" width="140" height="140" style="display:block;width:140px;height:auto;max-width:140px;object-fit:contain;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;background:#7a2e2e;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;font-weight:normal;font-family:Georgia,'Times New Roman',serif;">Redefinir senha</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;color:#2c2424;font-size:16px;line-height:1.6;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 12px;">Olá{{ $user->name ? ', '.$user->name : '' }}.</p>
                            <p style="margin:0 0 20px;">Recebemos um pedido para redefinir a senha da sua conta no Cardioprenatal, para o rastreio de cardiopatias congênitas em gestantes diabéticas. O link abaixo vale por {{ $expiresMinutes }} minutos.</p>
                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $url }}" style="display:inline-block;padding:12px 22px;background:#7f0c1a;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;">
                                    Escolher nova senha
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;color:#8a7a7a;word-break:break-all;">Se o botão não funcionar, copie este endereço:<br>{{ $url }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px;color:#8a7a7a;font-size:12px;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
                            Se você não pediu esta alteração, ignore este e-mail. Sua senha permanece a mesma.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
