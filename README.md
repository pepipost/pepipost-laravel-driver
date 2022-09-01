![pepipostlogo](https://pepipost.com/wp-content/uploads/2017/07/P_LOGO.png)

[![Packagist](https://img.shields.io/packagist/dt/pepipost/pepipost-laravel-driver.svg?style=flat-square)](https://packagist.org/packages/pepipost/pepipost-laravel-driver)
[![Packagist](https://img.shields.io/github/contributors/pepipost/pepipost-laravel-driver.svg)](https://github.com/pepipost/pepipost-laravel-driver)
[![Packagist](https://img.shields.io/packagist/l/pepipost/pepipost-laravel-driver.svg)](https://packagist.org/packages/pepipost/pepipost-laravel-driver)
[![Open Source Helpers](https://www.codetriage.com/pepipost/pepipost-laravel-driver/badges/users.svg)](https://www.codetriage.com/pepipost/pepipost-laravel-driver)
[![Twitter Follow](https://img.shields.io/twitter/follow/pepi_post.svg?style=social&label=Follow)](https://twitter.com/pepi_post)

# Laravel Driver for [Pepipost](http://www.pepipost.com/?utm_campaign=GitHubSDK&utm_medium=GithubSDK&utm_source=GithubSDK)

A Mail Driver with support for Pepipost Send Email Web API, using the original Laravel API. This library extends the original Laravel classes, so it uses exactly the same methods.

To use this package required your [Pepipost Api Key](https://app.pepipost.com). Please make it [Here](https://app.pepipost.com).


We are trying to make our libraries Community Driven- which means we need your help in building the right things in proper order we would request you to help us by sharing comments, creating new [issues](https://github.com/pepipost/laravel-pepipost-driver/issues) or [pull requests](https://github.com/pepipost/laravel-pepipost-driver/pulls).


We welcome any sort of contribution to this library.

The latest 2.0.0 version of this library provides is fully compatible with the latest Pepipost v5.1 API.

For any update of this library check [Releases](https://github.com/pepipost/laravel-pepipost-driver/releases).

# Table of Content
  
* [Installation](#installation)
* [Quick Start](#quick-start)
* [Usage of library in Project](#inproject)
* [Sample Example](#eg)
* [Announcements](#announcements)
* [Roadmap](#roadmap)
* [About](#about)
* [License](#license)

<a name="installation"></a>
# Installation

<a name="prereq"></a>

### Prerequisites

[PHP >= 8.0](https://www.php.net/manual/en/install.php)

[Composer v2.3.0](https://getcomposer.org/download/)

[Laravel >= 8.x ](https://laravel.com/docs/9.x/installation)

[Guzzle ^7.2](https://github.com/guzzle/guzzle)

A free account on Pepipost. If you don't have a one, [click here](https://app.pepipost.com) to signup.

## Usage

### Configuring laravel project

#### Step 1 - Create New Laravel project 

```bash 
laravel new testproject
```

#### Step 2 - Add the package to your composer.json and run composer update.

```json
"require": {
    "pepipost/pepipost-laravel-driver": "~2.0.0"
},
```
#### or install with composer

```bash
$ composer require pepipost/pepipost-laravel-driver
```

#### Step 3 - Configurations 

2) Add pepipost api key, endpoint in config/services.php

    ```php
    'pepipost' => [
        'api_key' => env('PEPIPOST_API_KEY'),
    ],
     ```
    ``` txt    
    Endpoint config:
    If you need to set custom endpoint, you can set any endpoint by using endpoint key.
    For example,calls to Pepipost Web API through a proxy,configure endpoint in config/services.php.

      'pepipost' => [
          'api_key' => env('PEPIPOST_API_KEY'),
          'endpoint' => 'https://api.pepipost.com/v5/mail/send',
      ],
    ```

3) Add following in .env file
      ```dotenv
      MAIL_MAILER=pepipost # Needed to send through pepipipost api
      PEPIPOST_API_KEY='YOUR_PEPIPOST_API_KEY'
      ```

#### Step 4-  Laravel Steps to create controller and view

1) Define Controller

    ```bash
    php artisan make:controller TestController
    ```
2) Update the controller
    Include following function sendMail in TestController:

      ```php
      <?php
        namespace App\Http\Controllers;
        use Illuminate\Support\Facades\Mail;
        use App\Mail\TestEmail;
        
        use Illuminate\Http\Request;
        
        class TestController extends Controller
        {
        	function sendMail(){
        		try {
        			Mail::to('mail.recipient@gmail.com')
        			->send(new TestEmail(['message' => 'Just a test message']));
        			return 'Email sent successfully';
        		}
        		catch (Exception $e) {
        			echo $e->getResponse();
        		}
        	}
        }
      ```

3) create file in resources/views/mailtemplates/test.blade.php 
    And include your email content 
    ```html
    <!DOCTYPE html>
    <html lang="en-US">
      <head>
        <meta charset="utf-8" />
      </head>
      <body>
        <h2>Test Email</h2>
        <p>{{ $test_message }}</p>
      </body>
    </html>
    ```

4) Create a new route in routes/web.php

      ```php
      Route::get('/send/email', [TestController::class, 'sendMail'])->name('sendEmail');
      ```
5) Create a mailable template

      ```bash
      php artisan make:mail TestEmail
      ```
    This command will create a new file under app/Mail/TestEmail.php 
    
6) Update your mailable template
    Update the mailable to the following code:
    ```php
    <?php
        namespace App\Mail;
        
        use Illuminate\Bus\Queueable;
        use Illuminate\Contracts\Queue\ShouldQueue;
        use Illuminate\Mail\Mailable;
        use Illuminate\Queue\SerializesModels;
        use Pepipost\PepipostLaravelDriver\Pepipost;
        
        class TestEmail extends Mailable
        {
          /**
           * Create a new message instance.
           *
           * @return void
           */
        
          use Pepipost;
        
          public $data;
          public function __construct($data)
          {
            $this->data = $data;
          }
        
          /**
           * Build the message.
           *
           * @return $this
           */
          public function build()
          {
            return $this
              ->view('mailtemplate.test')
              ->from('mail@sendingdomain.com')
              ->subject("Demo email from laravel")
              ->with([ 'test_message' => $this->data['message'] ]);
          }
        }
    ```

#### Step 5 - Testing

Host your laravel project and enter url- http://your_url.com/send/email in browser

This will send email and display Email sent successfully on browser.

#### Additional Usage

IF want to pass others parameters of Pepipost SendEmail, you have to update the mailable template.
Add parameters as per your requirement. You can use multiple to's,cc's,bcc's with this method. Please refer the official [Netcore api doc](https://cpaasdocs.netcorecloud.com/docs/pepipost-api/b3A6NDIxNTc2ODE-send-an-email) for more details on adavanced parameters.

This will be under app/Mail/TestEmail.php that we created.
```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Pepipost\PepipostLaravelDriver\Pepipost;

class TestEmail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */

    use Pepipost;

    public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            # To provide template provide empty array.
            ->view('mailtemplate.test')
            ->from('info@domain.com')
            # Not required for this example
            // ->cc('user1', 'vikram')
            ->subject("Demo email from laravel")
            # Store attachment in the "storage" path
            ->attach(storage_path('Discussions.pdf'))
            ->pepipost(
                [
                # Optional. For no options provide an empty array
                "personalizations" => [
                    [
                        // This will override the recipient specified in the mailer method "to"
                        "to" => [
                            [
                                "email" => "john@domain.com",
                                "name" => "John Doe"
                            ],
                            [
                                "email" => "emma@domain.com",
                                "name" => "Emma Watson"
                            ],
                        ],
                        // This will override the above cc_recipient specified in the mailer method, if provided.
                        "cc" => [
                            [
                                "email" => "ccrecipient1@domain.com",
                                "email" => "ccrecipient2@domain.com",
                            ]
                        ],
                        // This will override the above cc_recipient specified in the mailer method, if provided.
                        "bcc" => [
                            [
                                "email" => "bccrecipient1@domain.com",
                                "email" => "bccrecipient2@domain.com",
                            ]
                        ],
                        # X-Api header for to mail
                        "token_to" => "tracker_phone",
                        # X-Api header for cc mail
                        "token_cc" => "tracker_cc",
                        # X-Api header for cc mail
                        "token_bcc" => "tracker_bcc"
                    ],
                    [
                        # Different parameters for second recipient
                        "to" => [
                            [
                                "email" => "jenna@domain.com",
                                "name" => "Jenna Bane"
                            ]
                        ],
                        # X-Api header for to mail
                        "token_to" => "jenna_emp"
                    ]
                ],
                "settings" => [
                    "open_track" => true,
                    "click_track" => true,
                    "unsubscribe_track" => true,
                    "hepf" => false
                ],
                # For using pepipost templates instead of view email templates
                "template_id" => 1234
            ]
        );
    }
}
```

<a name="announcements"></a>
# Announcements

v2.0.0 has been released! Please see the [release notes](https://github.com/pepipost/laravel-pepipost-driver/releases/) for details.

All updates to this library are documented in our [releases](https://github.com/pepipost/laravel-pepipost-driver/releases). For any queries, feel free to reach out us at devrel@netcorecloud.com

<a name="roadmap"></a>
## Roadmap

If you are interested in the future direction of this project, please take a look at our open [issues](https://github.com/pepipost/laravel-pepipost-driver/issues) and [pull requests](https://github.com/pepipost/laravel-pepipost-driver/pulls). We would love to hear your feedback.

<a name="about"></a>
## About
pepipost-laravel library is guided and supported by the [Pepipost Developer Experience Team](https://github.com/orgs/pepipost/teams/pepis/members) .
This pepipost library is maintained and funded by Pepipost Ltd. The names and logos for pepipost gem are trademarks of Pepipost Ltd.

<a name="license"></a>
## License
[MIT](https://choosealicense.com/licenses/mit/)
