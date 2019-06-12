<?php
namespace Pepipost\PepipostLaravelDriver\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Transport\Transport;
use Swift_Attachment;
use Swift_Image;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;

class PepipostTransport extends Transport
{
    

    const SMTP_API_NAME = 'pepipostapi';
    const MAXIMUM_FILE_SIZE = 20480000;
    const BASE_URL = 'https://api.pepipost.com/v2/sendEmail';

    /**
     * @var Client
     */
    private $client;
    private $attachments;
    private $numberOfRecipients;
    private $apiKey;
    private $endpoint;

    public function __construct(ClientInterface $client, $api_key, $endpoint = null)
    {
        $this->client = $client;
        $this->apiKey = $api_key;
        $this->endpoint = isset($endpoint) ? $endpoint : self::BASE_URL;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message,&$failedRecipients = null)
    {
        
        $data = [
            'from'             => $this->getFrom($message),
            'subject'          => $message->getSubject(),
        ];
	if($message->getTo()){
                $data['personalizations'] = $this->getTo($message);
        }
	
        if ($contents = $this->getContents($message)) {
            $data['content'] = $contents;
        }

        if ($reply_to = $this->getReplyTo($message)) {
            $data['replyToId'] = $reply_to;
        }

        $attachments = $this->getAttachments($message);
        if (count($attachments) > 0) {
            $data['attachments'] = $attachments;
        }

       $data = $this->setParameters($message, $data);

        $payload = [
            'headers' => [
		'api_key'      => $this->apiKey,
                'Content-Type' => 'application/json',
		'user-agent'   => 'pepi-laravel-lib v1',
            ],
            'json' => $data,
        ];

        $response = $this->post($payload);

        return $response;
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getPersonalizations(Swift_Mime_SimpleMessage $message)
    {
        $setter = function (array $addresses) {
            $recipients = [];
            foreach ($addresses as $email => $name) {
                $address = [];
                $address['email'] = $email;
                if ($name) {
                    $address['name'] = $name;
                }
                $recipients[] = $address;
            }
            return $recipients;
        };
	$personalization= $this->getTo($message);
		
        return $personalization;
    }


     /**
     * Get From Addresses.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getTo(Swift_Mime_SimpleMessage $message)
    {

	$this->numberOfRecipients=0;
        if ($message->getTo()) {
	    $toarray = [];
            foreach ($message->getTo() as $email => $name) {
		$recipient = [];
		$recipient['recipient'] = $email;
		 if ($cc = $message->getCc()) {
          		 $recipient['recipient_cc'] = $this->getCC($message);
       		 }
		 if ($bcc = $message->getBcc()) {
          		 $recipient['recipient_bcc'] = $this->getBCC($message);
		}
                $toarray[] = $recipient;
		++$this->numberOfRecipients;
        	}
	
   }
        return $toarray;
}
      /**
     * Get From Addresses.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getCC(Swift_Mime_SimpleMessage $message)
    {
        $ccarray = array();
        if ($message->getCc()) {
            foreach ($message->getCc() as $email => $name) {
                $ccarray[] = $email;
            }
        }
        return $ccarray;
    }
    
    /**
     * Get From Addresses.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getBCC(Swift_Mime_SimpleMessage $message)
    {
        $bccarray = array();
        if ($message->getBcc()) {
            foreach ($message->getBcc() as $email => $name) {
                $bccarray[] = $email;
            }
        }
        return $bccarray;
    }


    /**
     * Get From Addresses.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getFrom(Swift_Mime_SimpleMessage $message)
    {
        if ($message->getFrom()) {
            foreach ($message->getFrom() as $email => $name) {
                return ['fromEmail' => $email, 'fromName' => $name];
            }
        }
        return [];
    }

    /**
     * Get ReplyTo Addresses.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getReplyTo(Swift_Mime_SimpleMessage $message)
    {
        if ($message->getReplyTo()) {
            foreach ($message->getReplyTo() as $email => $name) {
                return $email;
            }
        }
        return null;
    }

    /**
     * Get contents.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
   private function getContents(Swift_Mime_SimpleMessage $message)
    {
        return $message->getBody();
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getAttachments(Swift_Mime_SimpleMessage $message)
    {
       $attachments = [];
       foreach ($message->getChildren() as $attachment) {
        $attachment = $message->getChildren();   
	 if ((!$attachment instanceof Swift_Attachment && !$attachment instanceof Swift_Image)
		|| $attachment->getFilename() === self::SMTP_API_NAME
                || !strlen($attachment->getBody()) > self::MAXIMUM_FILE_SIZE
            ) {
                continue;
            }
            $attachments[] = [
                'fileContent'     => base64_encode($attachment->getBody()),
                'fileName'    => $attachment->getFilename(),
            ];
       }
        return $this->attachments = $attachments;
    }

    /**
     * Set Request Body Parameters
     *
     * @param Swift_Mime_SimpleMessage $message
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function setParameters(Swift_Mime_SimpleMessage $message, $data)
    {
       //$this->numberOfRecipients = 0;
       $smtp_api = [];
       foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_Image || !in_array(self::SMTP_API_NAME, [$attachment->getFilename(), $attachment->getContentType()])) {
                continue;
            }
            $smtp_api = $attachment->getBody();
        }
        foreach ($smtp_api as $key => $val) {
            switch ($key) {

                case 'settings':
                    $this->setSettings($data, $val);
                    continue 2;
		case 'tags':
		    array_set($data,'tags',$val);
		    continue 2;
		case 'templateId':
		    array_set($data,'templateId',$val);
		    continue 2;	
                case 'personalizations':		     
                    $this->setPersonalizations($data, $val);
                    continue 2;

                case 'attachments':
                    $val = array_merge($this->attachments, $val);
                    break;
                    }
                   

           array_set($data, $key, $val);
        }
        return $data;
    }

    private function setPersonalizations(&$data, $personalizations)
    {

        foreach ($personalizations as $index => $params) {
	    	
	    if($this->numberOfRecipients <= 0)
	    {
		array_set($data,'personalizations'.'.'.$index  , $params);
		continue;
	    } 
	    $count=0;
	    while($count<$this->numberOfRecipients)
	    {
                if (in_array($params, ['attributes','x-apiheader','x-apiheader_cc'])&& !in_array($params, ['recipient','recipient_cc'])) {
		      array_set($data, 'personalizations.'.$count . '.' . $index  , $params);	
                } else {
			array_set($data, 'personalizations.'.$count . '.' . $index  , $params);
                }
		$count++;
       	 }
	}
    }

    private function setSettings(&$data, $settings)
    {
        foreach ($settings as $index => $params) {
        	array_set($data,'settings.'.$index,$params);   
	}
    }

    /**
     * @param $payload
     * @return Response
     */
    private function post($payload)
    {
        return $this->client->post($this->endpoint, $payload);
    }
}

