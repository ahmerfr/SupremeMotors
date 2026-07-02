<!DOCTYPE html>
<html>
<head>
    <title>New Contact Form Submission</title>
</head>
<body>
    <h2>New Contact Form Submission</h2>
    <p><strong>Name:</strong> {{ $contact['name'] }}</p>
    <p><strong>Email:</strong> {{ $contact['email'] }}</p>
    <p><strong>Phone:</strong> {{ $contact['phone'] }}</p>
    <p><strong>Subject:</strong> {{ $contact['subject'] }}</p>
    <p><strong>Message:</strong></p>
    <p>{{ nl2br(e($contact['message'])) }}</p>
</body>
</html>
