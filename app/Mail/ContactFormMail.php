<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contact;

    /**
     * Create a new message instance.
     */
    public function __construct($contact)
    {
        $this->contact = $contact;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->replyTo($this->contact['email'], $this->contact['name'])
                    ->to('info@suprememotors.ltd')
                    ->subject('New Contact Form Submission: ' . $this->contact['subject'])
                    ->view('emails.contact_form')
                    ->with('contact', $this->contact);
    }
}
