<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SolicitudPagareMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $nombreEntidad;
    public $reporte;

    public function __construct($nombreEntidad, $reporte)
    {
        log::info('ENVIANDO MAIL');

      //  $this->pathToFile = public_path() . '/' . $pathToFile;
        $this->reporte = storage_path('app/archivos/'.$reporte);
        log::info($this->reporte);
        $this->nombreEntidad = $nombreEntidad;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->from(env('MAIL_FROM_ADDRESS'));
        $mail->subject('Pagarés pendientes de entrega - ' . $this->nombreEntidad);
        $mail->view('mail.solicitud_pagares');
        $mail->attach($this->reporte);
        return $mail;
    }
}
