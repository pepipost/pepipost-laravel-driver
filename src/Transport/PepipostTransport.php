<?php
namespace Pepipost\PepipostLaravelDriver\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Arr;
use Swift_Attachment;
use Swift_Image;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;

use Pepipost\PepipostLaravelDriver\Pepipost;

class PepipostTransport extends Transport
{
    use Pepipost {
        pepiDecode as decode;
    }

    const SMTP_API_NAME = 'pepipostapi/request-body-parameter';
    const BASE_URL = 'https://emailapi.netcorecloud.net/v5.1/mail/send';

    /**
     * @var Client
     */
    private $client;
    private $attachments;
    private $numberOfRecipients;
    private $apiKey;
    private $endpoint;

    public function __construct(ClientInterface $client, string $api_key, string $endpoint = null)
    {
        $this->client = $client;
        $this->apiKey = $api_key;
        $this->endpoint = isset($endpoint) ? $endpoint : self::BASE_URL;
        $this->attachments = [];
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);
        
        $data = [
            'from' => $this->getFrom($message),
            'subject' => $message->getSubject(),
        ];
        
        if($message->getTo()) {
            $data['personalizations'] = $this->getPersonalizations($message);
        }

        if ($contents = $this->getContents($message)) {
            $data['content'] = [$contents];
        }

        if ($reply_to = $this->getReplyTo($message)) {
            $data['reply_to'] = $reply_to;
        }

        $attachments = $this->getAttachments($message);
        if (count($attachments) > 0) {
            $data['attachments'] = $attachments;
        }

        $data = $this->setParameters($message, $data);

        $payload = [
            'headers' => [
                'api_key'      => $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'json' => $data,
        ];

        $this->post($payload);
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
                if($name){
                    $fromname = $name;
                } else {
                    $fromname = explode('@', $email)[0];
                }
                return ['email' => $email, 'name' => $fromname];
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
        return [];
    }

    /**
     * Get contents.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getContents(Swift_Mime_SimpleMessage $message)
    {   
        $contentType = $message->getContentType();
        if (is_null($message->getBody())) {
            return null;
        }
        return [
            'type' => 'html',
            'value' => $message->getBody()
        ];
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getAttachments(Swift_Mime_SimpleMessage $message)
    {
        $attachments = [];
        foreach ($message->getChildren() as $attachment) {
            if ((!$attachment instanceof Swift_Attachment && !$attachment instanceof Swift_Image)
                || $attachment->getFilename() === self::SMTP_API_NAME
            ){
                continue;
            }

            $attachments[] = [
                'content' => base64_encode($attachment->getBody()),
                'name' => $attachment->getFilename()
            ];
        }

        return $this->attachments = $attachments;
    }

    /**
     * @param DataPart $dataPart
     * @return string
     */
    private function getAttachmentName(DataPart $dataPart): string
    {
        return $dataPart->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename');
    }

    /**
     * @param DataPart $dataPart
     * @return string
     */
    private function getAttachmentContentType(Datapart $dataPart): string
    {
        return $dataPart->getMediaType() . '/' . $dataPart->getMediaSubtype();
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
        $this->numberOfRecipients = 0;

        $smtp_api = [];
        # Taking as attachment since we are embedding the parameters taken from the users
        foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_Image
                || !in_array(self::SMTP_API_NAME, [$attachment->getFilename(), $attachment->getContentType()])
            ) {
                continue;
            }
            $smtp_api = self::decode($attachment->getBody());
        }
        
        if (!is_array($smtp_api)) {
            return $data;
        }

        foreach ($smtp_api as $key => $val) {
            switch ($key) {
                case 'settings':
                    $this->setSettings($data, $val);
                    continue 2;
                case 'tags':
                    Arr::set($data,'tags',$val);
                    continue 2;
                case 'template_id':
                    Arr::set($data,'template_id',$val);
                    continue 2;
                case 'personalizations':
                    $this->setPersonalizations($data, $val);
                    continue 2;
                case 'attachments':
                    $val = array_merge($this->attachments, $val);
                    break;
            }
            Arr::set($data, $key, $val);
        }

        return $data;
    }

    /**
     * @param array $data
     * @param array $personalizations
     * @return void
     */
    private function setPersonalizations(&$data, $personalizations)
    {   
        foreach ($personalizations as $index => $params) {
            foreach ($params as $key => $val) {
                Arr::set($data, 'personalizations.' . $index . '.' . $key, $val);
                if (in_array($key, ['to', 'cc', 'bcc'])) {
                    ++$this->numberOfRecipients;
                }
            }
        }
    }
        
    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array[]
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
                } else {
                    $address['name'] = explode('@', $email)[0];
                }
                $recipients[] = $address;
            }
            return $recipients;
        };

        // print_r($setter($message->getTo()));exit;

        $personalization['to'] = $setter($message->getTo());

        if ($cc = $message->getCc()) {
            $personalization['cc'] = $setter($cc);
        }

        if ($cc = $message->getBcc()) {
            $personalization['bcc'] = $setter($bcc);
        }

        return [$personalization];
    }

    private function setSettings(array &$data, array $settings): void
    {
        foreach ($settings as $index => $params) {
            Arr::set($data,'settings.'.$index,$params);
        }
    }

    /**
     * @param $payload
     * @throws GuzzleException
     * @return Response
     * 
     */
    private function post($payload)
    {   
        return $this->client->request('POST', $this->endpoint, $payload);
    }

    public function __toString()
    {
        return 'pepipost';
    }
}