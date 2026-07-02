<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        /* Reset styles */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        /* Container */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        /* Header */
        .header {
            text-align: center;
            padding: 30px 0;
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            border-radius: 8px 8px 0 0;
        }
        .header img {
            max-width: 150px;
        }
        .header h1 {
            color: white;
            margin: 10px 0 0;
            font-weight: 300;
            font-size: 28px;
        }
        /* Content */
        .content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        /* User info box */
        .user-info {
            background-color: #f9f9f9;
            border-left: 4px solid #a777e3;
            padding: 15px;
            margin: 20px 0;
        }
        .avatar {
            border-radius: 50%;
            width: 60px;
            height: 60px;
            object-fit: cover;
            border: 3px solid #a777e3;
        }
        /* Button */
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        /* Features */
        .features {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        .feature {
            flex-basis: 30%;
            text-align: center;
            margin-bottom: 20px;
        }
        .feature-icon {
            font-size: 24px;
            color: #a777e3;
            margin-bottom: 10px;
        }
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #999;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #a777e3;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .feature {
                flex-basis: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('assets/images/site-logo.png') }}" alt="{{ config('app.name') }} Logo">
            <h1>Welcome to {{ config('app.name') }}!</h1>
        </div>
        
        <div class="content">
            <h2>Hello {{ $user->name }},</h2>
            
            <p>Thank you for joining {{ config('app.name') }}! We're excited to have you as part of our community.</p>
            
            <div class="user-info">
                <table>
                    <tr>
                        <td width="80">
                            @if($user->profile_picture)
                                <img class="avatar" src="{{ $user->profile_picture }}" alt="{{ $user->name }}">
                            @else
                                <div class="avatar" style="background-color: #e0e0e0; display: flex; align-items: center; justify-content: center;">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $user->name }}</strong><br>
                            {{ $user->email }}<br>
                            <small>Account created: {{ $user->created_at->format('F j, Y') }}</small>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p>Your account has been successfully created and is ready to use!</p>
            
            <center>
                <a href="https://suprememotors.ltd" class="button" style="color : white">Go to Website</a>
            </center>
            
            <p>If you have any questions or need assistance, please don't hesitate to contact our support team at <a href="mailto:info@suprememotors.ltd">info@suprememotors.ltd</a>.</p>
            
            <p>Welcome aboard!</p>
            
            <p>The {{ config('app.name') }} Team</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>You're receiving this email because you signed up for an account with {{ config('app.name') }}.</p>
        </div>
    </div>
</body>
</html>