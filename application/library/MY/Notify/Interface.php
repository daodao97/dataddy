<?php
namespace MY;

interface Notify_Interface
{
    public function sendMessage($title, $message, $receiver);
}
