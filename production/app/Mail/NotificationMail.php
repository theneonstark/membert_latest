<?php
 
namespace App\Mail;
 
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailable;

class NotificationMail extends Mailable
{
    public $view, $data;
 
    /**
     * Create a new message instance.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function __construct($view,  $data)
    {
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: $this->view,
            with: $this->data,
        );
    }
}