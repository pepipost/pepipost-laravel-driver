<?php
namespace Pepipost\PepipostLaravelDriver\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Arr;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Email;
use Pepipost\PepipostLaravelDriver\Pepipost;

class PepipostTransport extends AbstractTransport
{
    use Pepipost {
        pepiDecode as decode;
    }

    const SMTP_API_NAME = 'pepipostapi';
    const REQUEST_BODY_PARAMETER = 'pepipostapi/request-body-parameter';
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
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        
        $data = [
            'from' => $this->getFrom($email),
            'subject' => $email->getSubject(),
        ];
        
        if($email->getTo()) {
            $data['personalizations'] = $this->getPersonalizations($email);
        }

        if ($contents = $this->getContents($email)) {
            $data['content'] = [$contents];
        }

        if ($reply_to = $this->getReplyTo($email)) {
            $data['reply_to'] = $reply_to;
        }

        $attachments = $this->getAttachments($email);
        if (count($attachments) > 0) {
            $data['attachments'] = $attachments;
        }

        $data = $this->setParameters($email, $data);

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
     * @param Email $email
     * @return array
     */
    private function getFrom(Email $email): array
    {
        if ($email->getFrom()) {
            foreach ($email->getFrom() as $from) {
                $fromadd = $from->getAddress();
                if($from->getName()){
                    $fromname = $from->getName();
                } else {
                    $fromname = explode('@', $from->getAddress())[0];
                }
                return ['email' => $fromadd, 'name' => $fromname];
            }
        }
        return [];
    }

    /**
     * Get ReplyTo Addresses.
     *
     * @param Email $email
     * @return string
     */
    private function getReplyTo(Email $email): string
    {
        if ($email->getReplyTo()) {
            foreach ($email->getReplyTo() as $emailid => $name) {
                return $emailid;
            }
        }
        return '';
    }

    /**
     * Get contents.
     *
     * @param Email $email
     * @return array
     */
    private function getContents(Email $email): array
    {
        return [
            'type' => 'html',
            'value' => $email->getHtmlBody()
        ];
    }

    /**
     * @param Email $email
     * @return array
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $filename = $this->getAttachmentName($attachment);
            if ($filename === self::REQUEST_BODY_PARAMETER) {
                continue;
            }

            $attachments[] = [
                'content' => base64_encode($attachment->getBody()),
                'name' => $filename
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
     * @param Email $email
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function setParameters(Email $email, array $data): array
    {   
        //$this->numberOfRecipients = 0;
        $smtp_api = [];
        # Taking as attachment since we are embedding the parameters taken from the users
        foreach ($email->getAttachments() as $attachment) {
            $name = $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename');
            if ($name === self::REQUEST_BODY_PARAMETER) {
                $smtp_api = self::decode($attachment->getBody());
            }
        }

        if (count($smtp_api) < 1) {
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
     * @param Address[] $addresses
     * @return array
     */
    private function setAddress(array $addresses, string $type=null): array
    {
        $recipients = [];
        foreach ($addresses as $address) {
            $recipient = ['email' => $address->getAddress()];
            if (!$type){
                if ($address->getName() !== '') {
                    $recipient['name'] = $address->getName();
                } else {
                    $recipient['name'] = explode('@',$address->getAddress())[0];
                }
            }
            $recipients[] = $recipient;
        }
        return $recipients;
    }

    /**
     * @param array $data
     * @param array $personalizations
     * @return void
     */
    private function setPersonalizations(array &$data, array $personalizations): void
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
     * @param Email $email
     * @return array[]
     */
    private function getPersonalizations(Email $email): array
    {
        $personalization['to'] = $this->setAddress($email->getTo());

        if (count($email->getCc()) > 0) {
            $personalization['cc'] = $this->setAddress($email->getCc(), 'cc');
        }

        if (count($email->getBcc()) > 0) {
            $personalization['bcc'] = $this->setAddress($email->getBcc(), 'bcc');
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
     * @return Response
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