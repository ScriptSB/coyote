<?php

namespace Coyote\Alert\Broadcasts;

use Coyote\Alert\Providers\ProviderInterface;
use Illuminate\Support\Facades\Mail;

/**
 * Class Email
 * @package Coyote\Alert\Broadcasts
 */
class Email extends Broadcast
{
    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $view;

    /**
     * @param $view
     * @param $email
     */
    public function __construct($view, $email)
    {
        $this->view = $view;
        $this->email = $email;
    }

    /**
     * @param ProviderInterface $alert
     */
    public function send(ProviderInterface $alert)
    {
        $data = $alert->toArray();
        $data['headline'] = $this->parse($data, $data['headline']);

        Mail::queue($this->view, $data, function ($message) use ($data) {
            $message->subject($data['headline']);
            $message->to($this->email);
            $message->from('no-reply@4programmers.net', $data['sender_name']);
        });
    }
}
