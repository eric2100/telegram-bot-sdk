<?php

namespace Telegram\Bot\Objects;

use Illuminate\Support\Collection;
use Telegram\Bot\Objects\Payments\PreCheckoutQuery;
use Telegram\Bot\Objects\Payments\ShippingQuery;

/**
 * Class Update.
 *
 * @link https://core.telegram.org/bots/api#update
 *
 * @property int $updateId               The update's unique identifier. Update identifiers start from a certain positive number and increase sequentially.
 * @property Message|null $message                (Optional). New incoming message of any kind - text, photo, sticker, etc.
 * @property EditedMessage|null $editedMessage          (Optional). New version of a message that is known to the bot and was edited.
 * @property Message|null $channelPost            (Optional). New incoming channel post of any kind — text, photo, sticker, etc.
 * @property EditedMessage|null $editedChannelPost      (Optional). New version of a channel post that is known to the bot and was edited sticker, etc.
 * @property InlineQuery|null $inlineQuery            (Optional). New incoming inline query.
 * @property ChosenInlineResult|null $chosenInlineResult     (Optional). A result of an inline query that was chosen by the user and sent to their chat partner.
 * @property CallbackQuery|null $callbackQuery          (Optional). Incoming callback query.
 * @property ShippingQuery|null $shippingQuery          (Optional). New incoming shipping query. Only for invoices with flexible price
 * @property PreCheckoutQuery|null $preCheckoutQuery       (Optional). New incoming pre-checkout query. Contains full information about checkout
 * @property Poll|null $poll                   (Optional). New poll state. Bots receive only updates about stopped polls and polls, which are sent by the bot
 * @property PollAnswer|null $pollAnswer             (Optional). A user changed their answer in a non-anonymous poll. Bots receive new votes only in polls that were sent by the bot itself.
 * @property ChatMemberUpdated|null $myChatMember           (Optional). The bot's chat member status was updated in a chat. For private chats, this update is received only when the bot is blocked or unblocked by the user.
 * @property ChatMemberUpdated|null $chatMember             (Optional). A chat member's status was updated in a chat. The bot must be an administrator in the chat and must explicitly specify “chat_member” in the list of allowed_updates to receive these updates.
 * @property ChatJoinRequest|null $chatJoinRequest        (Optional). A request to join the chat has been sent. The bot must have the can_invite_users administrator right in the chat to receive these updates.
 */
class Update extends BaseObject
{
    /** @var string|null Cached type of thr Update () */
    protected ?string $updateType = null;

    /**
     * @var string[]
     */
    protected const TYPES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ];

    /**
     * {@inheritdoc}
     * @return array{message: string, edited_message: string, channel_post: string, edited_channel_post: string, inline_query: string, chosen_inline_result: string, callback_query: string, shipping_query: string, pre_checkout_query: string, poll: string, poll_answer: string, my_chat_member: string, chat_member: string, chat_join_request: string}
     */
    public function relations(): array
    {
        return [
            'message' => Message::class,
            'edited_message' => EditedMessage::class,
            'channel_post' => Message::class,
            'edited_channel_post' => EditedMessage::class,
            'inline_query' => InlineQuery::class,
            'chosen_inline_result' => ChosenInlineResult::class,
            'callback_query' => CallbackQuery::class,
            'shipping_query' => ShippingQuery::class,
            'pre_checkout_query' => PreCheckoutQuery::class,
            'poll' => Poll::class,
            'poll_answer' => PollAnswer::class,
            'my_chat_member' => ChatMemberUpdated::class,
            'chat_member' => ChatMemberUpdated::class,
            'chat_join_request' => ChatJoinRequest::class,
        ];
    }

    /**
     * @deprecated Will be removed in SDK v4
     * Get recent message.
     */
    public function recentMessage(): self
    {
        return new self($this->last());
    }

    /**
     * Determine if the update is of given type.
     *
     * @param string $type
     */
    public function isType(string $type): bool
    {
        if ($this->has(strtolower($type))) {
            return true;
        }

        return $this->detectType() === $type;
    }

    /**
     * Update type.
     */
    public function objectType(): ?string
    {
        if ($this->updateType === null) {
            $isWebAppData = (bool)$this->getMessage()->get('web_app_data');
            $updateType = $this->except('update_id')->keys()->first();

            $this->updateType = $isWebAppData ? 'web_app_data' : $updateType;
        }

        return $this->updateType;
    }

    /**
     * Detect type based on properties.
     *
     * @return string|null
     * @deprecated Will be removed in v4.0, please use {@see \Telegram\Bot\Objects\Update::objectType} instead.
     *
     */
    public function detectType(): ?string
    {
        return $this->keys()
            ->intersect(static::TYPES)
            ->pop();
    }

    /**
     * Get the message contained in the Update.
     */
    public function getMessage(): Collection
    {
        switch ($this->detectType()) {
            case 'message':
                return $this->message;
            case 'edited_message':
                return $this->editedMessage;
            case 'channel_post':
                return $this->channelPost;
            case 'edited_channel_post':
                return $this->editedChannelPost;
            case 'inline_query':
                return $this->inlineQuery;
            case 'chosen_inline_result':
                return $this->chosenInlineResult;
            case 'callback_query':
                $callbackQuery = $this->callbackQuery;
                if ($callbackQuery->has('message')) {
                    return $callbackQuery->message;
                }

                break;
            case 'shipping_query':
                return $this->shippingQuery;
            case 'pre_checkout_query':
                return $this->preCheckoutQuery;
            case 'poll':
                return $this->poll;
        }

        return collect();
    }

    /**
     * Borrowed from {@see \Telegram\Bot\Objects\Update::getMessage()} from SDK v4.
     * Get the message contained in the Update.
     *
     * @return Message|InlineQuery|ChosenInlineResult|CallbackQuery|ShippingQuery|PreCheckoutQuery|Poll|PollAnswer
     */
    public function getRelatedObject()
    {
        return $this->{$this->objectType()};
    }

    /**
     * Get chat object (if exists).
     */
    public function getChat(): Collection
    {
        if ($this->has('my_chat_member')) { // message is not available in such case
            return $this->myChatMember->chat;
        }

        $message = $this->getMessage();

        return $message->has('chat') ? $message->get('chat') : collect();
    }

    /**
     * @return bool
     * @deprecated This method will be removed in SDK v4
     * Is there a command entity in this update object.
     *
     */
    public function hasCommand(): bool
    {
        return (bool)$this->getMessage()->get('entities', collect())->contains('type', 'bot_command');
    }
}
