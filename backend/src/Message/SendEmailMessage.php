<?php

// src/Message/SendEmailMessage.php
namespace App\Message;

class SendEmailMessage
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $template,
        private array $templateData = [],
        private ?string $replyTo = null
    ) {}

    public function getTo(): string
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }
}