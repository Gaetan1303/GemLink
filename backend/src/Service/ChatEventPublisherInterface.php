<?php
namespace App\Service; use App\Entity\ChatMessage;
interface ChatEventPublisherInterface { public function publish(ChatMessage $message): void; }
