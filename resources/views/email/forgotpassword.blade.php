<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: Poppins, Helvetica, sans-serif;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            }
            .header-logo {
                color: #ffffff;
                text-align: center;
            }
            .header {
                color: #444444;
                text-align: center;
                padding: 10px 0;
            }
            .content {
                padding: 20px;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #f48f42;
                color: #ffffff;
                text-decoration: none;
                border-radius: 4px;
            }
            .logo {
                width: 200px;
                height: auto;
            }
            .footer {
                color: #8a8a8a;
                text-align: center;
                padding: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header-logo">
                <img alt="Logo" src="https://i.imgur.com/li6qPnV.png" class="logo">
            </div>
            <div class="content">
                <div class="header">Forgot Password | <b>Suma Honda</b></div>
                <br>
                <p style="margin: 0;">Hello <b>{{ strtoupper(trim($users->user_id)) }} ({{ strtoupper(trim($users->role_id)) }})</b>,</p>
                <p style="margin-top: 20px;">Kami menerima permintaan untuk mengatur ulang kata sandi anda pada alamat email <b>{{ trim($users->email) }}</b>.</p>
                <p style="margin-top: 20px;">Jika anda tidak mengajukan permintaan ini, anda dapat mengabaikan email ini.</p>
                <p style="margin-top: 20px;">Untuk mereset password anda, klik tombol di bawah ini:</p>
                <p style="text-align: center; margin-top: 30px;">
                    <a href="{{ trim($users->link) }}" style="display: inline-block; padding: 10px 20px; background-color: #d9214e; color: #ffffff; text-decoration: none; border-radius: 4px;">Reset Password</a>
                </p>
                <p style="margin-top: 20px;">Jika tombol di atas tidak bekerja, anda dapat meng-copy dan paste link url di bawah ini ke dalam browser anda.</p>
                <p><a href="{{ trim($users->link) }}">{{ trim($users->link) }}</a></p>
                <p style="margin-top: 30px;">Thank you,</p>
                <p>IT Programmer - Suma Honda</p>
            </div>
            <div class="footer">
                <p><b>© 2023 PT. Kharisma Suma Jaya Sakti</b></p>
            </div>
        </div>

    </body>
</html>
