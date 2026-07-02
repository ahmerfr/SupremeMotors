<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration</title>
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
            padding: 20px 0;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 8px 8px 0 0;
        }
        .header img {
            max-width: 120px;
        }
        .header h1 {
            color: white;
            margin: 10px 0 0;
            font-weight: 300;
            font-size: 24px;
        }
        /* Content */
        .content {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        /* User info */
        .user-details {
            background-color: #f0f7ff;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
        .detail-row {
            margin-bottom: 10px;
            display: flex;
        }
        .detail-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }
        .detail-value {
            flex: 1;
        }
        /* Buttons */
        .button-row {
            margin: 25px 0;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
        }
        .primary-button {
            background-color: #3498db;
            color: white;
        }
        .secondary-button {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }
        /* Status */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: bold;
            background-color: #4CAF50;
            color: white;
        }
        /* Avatar */
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
        }
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .user-header-info {
            margin-left: 20px;
        }
        /* Footer */
        .footer {
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #777;
        }
        @media only screen and (max-width: 600px) {
            .detail-row {
                flex-direction: column;
            }
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
            .button {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('assets/images/site-logo.png') }}" alt="{{ config('app.name') }} Logo">
            <h1>New User Registration</h1>
        </div>
        
        <div class="content">
            <h2>Hello Admin,</h2>
            
            <p>A new user has registered on {{ config('app.name') }}.</p>
            
            <div class="user-header">
                @if($user->profile_picture)
                    <img class="avatar" src="{{ $user->profile_picture }}" alt="{{ $user->name }}">
                @else
                    <div class="avatar" style="background-color: #e0e0e0; display: flex; align-items: center; justify-content: center;">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
                
                <div class="user-header-info">
                    <h3>{{ $user->name }}</h3>
                    <p>{{ $user->email }}</p>
                    <span class="status-badge">New Account</span>
                </div>
            </div>
            
            <div class="user-details">
                <div class="detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value">{{ $user->name }}</div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value">{{ $user->email }}</div>
                </div>
                @if ($user->phone)
                    <div class="detail-row">
                        <div class="detail-label">Phone NO.:</div>
                        <div class="detail-value">{{ $user->phone }}</div>
                    </div>
                @endif
                
                <div class="detail-row">
                    <div class="detail-label">Role:</div>
                    <div class="detail-value">{{ ucfirst($user->role) }}</div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Registered via:</div>
                    <div class="detail-value"> {{ $user->email_verified_at ? 'Google OAuth' : 'Website Registeration Portal'  }}</div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Registration date:</div>
                    <div class="detail-value">{{ $user->created_at->format('F j, Y, g:i a') }}</div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        Email {{ $user->email_verified_at ? 'verified' : 'unverified' }}
                    </div>
                </div>
            </div>
            
            {{-- <div class="button-row">
                <a href="{{ route('admin.users.show', $user->id) }}" class="button primary-button">View User Profile</a>
                <a href="{{ route('admin.users.edit', $user->id) }}" class="button secondary-button">Edit User</a>
            </div> --}}
            
            <p>This is an automated message from the {{ config('app.name') }} system.</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }} Admin System. All rights reserved.</p>
            <p>This email contains confidential information and is intended only for administrative purposes.</p>
        </div>
    </div>
</body>
</html>