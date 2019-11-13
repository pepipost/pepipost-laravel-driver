<?php
namespace Pepipost\PepipostLaravelDriver;

class MailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->register(PepipostTransportServiceProvider::class);
    }
}
