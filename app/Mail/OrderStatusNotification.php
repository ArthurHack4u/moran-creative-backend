<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $statusLabel;

    public function __construct(Order $order, $statusLabel)
    {
        $this->order = $order;
        $this->statusLabel = $statusLabel;
    }

    public function build()
    {
        return $this->subject("Actualización de tu pedido en MakerLab: {$this->statusLabel}")
                    ->view('emails.order_status');
    }
}