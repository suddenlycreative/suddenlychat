<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAccount;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Contact;
use App\Models\Conversation;
use App\Constants\Status;
use App\Events\ReceiveMessage;
use App\Lib\WhatsApp\WhatsAppLib;
use App\Models\User;
use libphonenumber\PhoneNumberUtil;
use App\Traits\WhatsappManager;
use Carbon\Carbon;
use Exception;

class WebhookController extends Controller
{
    use WhatsappManager;

    public function webhookConnect(Request $request)
    {
        $systemWebhookToken = gs('webhook_verify_token');

        if ($systemWebhookToken && $systemWebhookToken != $request->hub_verify_token) {
            return response('Invalid token', 401);
        }

        return response($request->hub_challenge)->header('Content-type', 'plain/text'); // meta need a specific type of response
    }

    public function webhookResponse(Request $request)
    {
        $entry = $request->input('entry', []);
        if (!is_array($entry)) return;

        $receiverPhoneNumber = null;
        $senderPhoneNumber   = null;
        $senderId            = null;
        $messageStatus       = null;
        $messageId           = null;
        $messageText         = null;
        $mediaId             = null;
        $mediaType           = null;
        $mediaMimeType       = null;
        $messageType         = 'text';
        $messageCaption      = null;
        $profileName         = null;

        $whatsappAccount = WhatsappAccount::where('whatsapp_business_account_id', $entry[0]['id'])->first();

        if (!$whatsappAccount) return;

        $user = User::active()->find($whatsappAccount->user_id);

        if (!$user) return;

        foreach ($entry as $entryItem) {

            foreach ($entryItem['changes'] as $change) {

                if (!is_array($change) || !isset($change['value'])) continue;
                
                if(isset($change['field']) && $change['field'] == 'message_template_status_update') continue;

                $metaValue = $change['value'];
                if (!is_array($metaValue)) continue;

                $profileName = $metaValue['contacts'][0]['profile']['name'] ?? null;
                $metaData    = $metaValue['metadata'] ?? [];
                $metaMessage = $metaValue['messages'] ?? null;

                if (isset($metaData['phone_number_id'])) {
                    $receiverPhoneNumberId = $metaData['phone_number_id'];
                }

                if (isset($metaData['display_phone_number'])) {
                    $receiverPhoneNumber = $metaData['display_phone_number'];
                }

                if (isset($metaMessage[0]['from'])) {
                    $senderPhoneNumber = $metaMessage[0]['from'];
                }

                if (isset($metaMessage[0]['id'])) {
                    $senderId = $metaMessage[0]['id'];
                }

                if (isset($change['value']['statuses'][0]['id'])) {
                    $messageId = $change['value']['statuses'][0]['id'];
                }

                if (isset($change['value']['statuses'][0]['status'])) {
                    $messageStatus = $change['value']['statuses'][0]['status'];
                }

                if (isset($metaMessage[0]['text']['body'])) {
                    $messageText = $metaMessage[0]['text']['body'];
                }
                if (isset($metaMessage[0]['type'])) {
                    $messageType = $metaMessage[0]['type'];
                }

                // Handle media messages
                if (isset($metaMessage[0]['type']) && $metaMessage[0]['type'] !== 'text') {
                    $mediaType = $metaMessage[0]['type'];

                    if (isset($metaMessage[0][$mediaType]['id'])) {
                        $mediaId = $metaMessage[0][$mediaType]['id'];
                    }
                    if (isset($metaMessage[0][$mediaType]['mime_type'])) {
                        $mediaMimeType = $metaMessage[0][$mediaType]['mime_type'];
                    }
                    if (isset($metaMessage[0][$mediaType]['caption'])) {
                        $messageCaption = $metaMessage[0][$mediaType]['caption'];
                    }
                }
            }
        }

        if ($messageId && $messageStatus) {

            $wMessage = Message::where('whatsapp_message_id', $messageId)->first();

            if ($wMessage) {
                $wMessage->status = messageStatus($messageStatus);
                $wMessage->save();

                $isNewMessage = false;
                if ($wMessage->status == Status::SENT || $wMessage->status == Status::FAILED) {
                    $isNewMessage = true;
                }

                $message = $wMessage;
                $html = view('Template::user.inbox.single_message', compact('message'))->render();

                event(new ReceiveMessage($whatsappAccount->id, [
                    'html'           => $html,
                    'messageId'      => $message->id,
                    'message'        => $message,
                    'statusHtml'     => $message->statusBadge,
                    'newMessage'     => $isNewMessage,
                    'mediaPath'      => getFilePath('conversation'),
                    'conversationId' => $wMessage->conversation_id,
                    'unseenMessage'  => $wMessage->conversation->unseenMessages()->count() < 10 ? $wMessage->conversation->unseenMessages()->count() : '9+',
                ]));

                return response()->json(['status' => 'received'], 200);
            }
        }

        if (($messageText || $mediaId) && $senderPhoneNumber && $senderId) {
            // Save the incoming message first
            $receiverPhoneNumber = preg_replace('/\D/', '', $receiverPhoneNumber);
            $phoneUtil           = PhoneNumberUtil::getInstance();
            $parseNumber         = $phoneUtil->parse('+' . $senderPhoneNumber, '');
            $countryCode         = $parseNumber->getCountryCode();
            $nationalNumber      = $parseNumber->getNationalNumber();
            $newContact          = false;

            $contact = Contact::where('mobile_code', $countryCode)
                ->where('mobile', $nationalNumber)
                ->where('user_id', $user->id)
                ->with('conversation')
                ->first();

            if (!$contact) {
                $newContact           = true;
                $contact              = new Contact();
                $contact->firstname   = $profileName;
                $contact->mobile_code = $countryCode;
                $contact->mobile      = $nationalNumber;
                $contact->user_id     = $user->id;
                $contact->save();
            }
            
            $conversation = Conversation::where('contact_id', $contact->id)->where('user_id', $user->id)->where('whatsapp_account_id', $whatsappAccount->id)->first();
            if (!$conversation) {
                $newContact   = true;
                $conversation = $this->createConversation($contact, $whatsappAccount);
            }

            $messageExists = Message::where('whatsapp_message_id', $senderId)->exists();

            if (!$messageExists) {
                // Save the incoming message
                $message                      = new Message();
                $message->whatsapp_account_id = $whatsappAccount->id;
                $message->whatsapp_message_id = $senderId;
                $message->user_id             = $user->id ?? 0;
                $message->conversation_id     = $conversation->id;
                $message->message             = $messageText;
                $message->type                = Status::MESSAGE_RECEIVED;
                $message->message_type        = $this->getIntMessageType($messageType);
                $message->media_id            = $mediaId;
                $message->media_type          = $mediaType;
                $message->media_caption       = $messageCaption;
                $message->mime_type           = $mediaMimeType;
                $message->ordering            = Carbon::now();
                $message->save();

                $conversation->last_message_at = Carbon::now();
                $conversation->save();

                // If it's a media message, fetch and store the media
                if ($mediaId) {
                    $accessToken = $whatsappAccount->access_token;
                    try {
                        $whatsappLib = new WhatsAppLib();
                        $mediaUrl = $whatsappLib->getMediaUrl($mediaId, $accessToken);

                        if ($mediaUrl) {
                            $mediaPath           = $whatsappLib->storedMediaToLocal($mediaUrl['url'], $mediaId, $accessToken, $user->id);
                            $message->media_url  = $mediaUrl;
                            $message->media_path = $mediaPath;

                            $message->save();
                        }
                    } catch (Exception $ex) {
                    }
                }

                $html                        = view('Template::user.inbox.single_message', compact('message'))->render();
                $lastConversationMessageHtml = view("Template::user.inbox.conversation_last_message", compact('message'))->render();

                event(new ReceiveMessage($whatsappAccount->id, [
                    'html'            => $html,
                    'message'         => $message,
                    'newMessage'      => true,
                    'newContact'      => $newContact,
                    'lastMessageHtml' => $lastConversationMessageHtml,
                    'unseenMessage'   => $conversation->unseenMessages()->count() < 10 ? $conversation->unseenMessages()->count() : '9+',
                    'lastMessageAt'   => showDateTime(Carbon::now()),
                    'conversationId'  => $conversation->id,
                    'mediaPath'       => getFilePath('conversation')
                ]));
            }

            $this->chatbotResponse($whatsappAccount, $user, $contact, $conversation, $messageText);
            $messagesInConversation = Message::where('conversation_id', $conversation->id)->where('type', Status::MESSAGE_RECEIVED)->count();

            if ($messagesInConversation == 1) {
                $this->sendWelcomeMessage($whatsappAccount, $user, $contact, $conversation);
            }
        }

        return response()->json(['status' => 'received'], 200);
    }


    private function getIntMessageType($messageType)
    {
        return [
            "text"     => Status::TEXT_TYPE_MESSAGE,
            "image"    => Status::IMAGE_TYPE_MESSAGE,
            "video"    => Status::VIDEO_TYPE_MESSAGE,
            "document" => Status::DOCUMENT_TYPE_MESSAGE,
        ][$messageType];
    }

    private function createConversation($contact, $whatsappAccount)
    {

        $conversation                      = new Conversation();
        $conversation->contact_id          = $contact->id;
        $conversation->whatsapp_account_id = $whatsappAccount->id;
        $conversation->user_id             = $whatsappAccount->user_id;
        $conversation->save();

        return $conversation;
    }

}
