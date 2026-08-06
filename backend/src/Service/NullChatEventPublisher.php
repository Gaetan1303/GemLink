<?php
namespace App\Service; use App\Entity\ChatMessage;
final class NullChatEventPublisher implements ChatEventPublisherInterface { public function publish(ChatMessage $message): void {} }
