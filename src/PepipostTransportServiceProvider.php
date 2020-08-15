<?php
namespace Pepipost\PepipostLaravelDriver;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Pepipost\PepipostLaravelDriver\Transport\PepipostTransport;

class PepipostTransportServiceProvider extends ServiceProvider
{
    /**
     * Register the Swift Transport instance.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving(MailManager::class, function ($mail_manager) {
            /** @var $mail_manager MailManager */
            $mail_manager->extend("pepipost", function($config){
                $client = new HttpClient(Arr::get($config, 'guzzle', []));
                $endpoint = isset($config['endpoint']) ? $config['endpoint'] : null;

                return new PepipostTransport($client, $config['api_key'], $endpoint);

            });
        });
    }
}
