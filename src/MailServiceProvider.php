<?php
namespace Pepipost\LaravelPepipostDriver;

class MailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->register(PepipostTransportServiceProvider::class);
    }
}
