<?php
namespace Pepipost\PepipostLaravelDriver;

use Illuminate\Mail\Mailable;
use Pepipost\PepipostLaravelDriver\Transport\PepipostTransport;
use Swift_Message;

trait Pepipost
{
    /**
     * @param null|array $params
     * @return $this
    */
    public function pepipost($params)
    {
        if (($this instanceof Mailable || $this instanceof MailMessage) && $this->mailDriver() === "pepipost") {
            $this->withSwiftMessage(function (Swift_Message $message) use ($params) {
                $message->embed(new \Swift_Image(static::pepiEncode($params), PepipostTransport::SMTP_API_NAME));
            });
        }
        return $this;
    }

    /**
     * @return string
     */
    private function mailDriver()
    {
        return function_exists('config') ? config('mail.default', config('mail.driver')) : env('MAIL_MAILER', env('MAIL_DRIVER'));
    }

    /**
     * @param array $params
     * @return string
     */
    public static function pepiEncode($params)
    {
        if (is_string($params)) {
            return $params;
        }
        return json_encode($params);
    }

    /**
     * @param string $strParams
     * @return array
     */
    public static function pepiDecode($strParams)
    {
        if (!is_string($strParams)) {
            return (array)$strParams;
        }
        $params = json_decode($strParams, true);
        return is_array($params) ? $params : [];
    }
}