<?php
namespace Pepipost\LaravelPepipostDriver;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Mail\TransportManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Pepipost\LaravelPepipostDriver\Transport\PepipostTransport;

class PepipostTransportServiceProvider extends ServiceProvider
{
    /**
     * Register the Swift Transport instance.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving(TransportManager::class, function(TransportManager $manager) {
            $this->extendTransportManager($manager);
        });
    }

    public function extendTransportManager(TransportManager $manager)
    {
        $manager->extend('pepipost', function() {
            $config = $this->app['config']->get('services.pepipost', array());
            $client = new HttpClient(Arr::get($config, 'guzzle', []));
            $endpoint = isset($config['endpoint']) ? $config['endpoint'] : null;

            return new PepipostTransport($client, $config['api_key'], $endpoint);
        });
    }
}
