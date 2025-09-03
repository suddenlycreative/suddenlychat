<?php

namespace App\Traits;

use App\Constants\Status;
use App\Lib\CurlRequest;
use App\Models\Message;
use Carbon\Carbon;
use Exception;

trait WhatsappManager
{
    public function verifyWhatsappCredentials($businessId, $token)
    {
        try {
            $apiUrl = "https://graph.facebook.com/v22.0/{$businessId}/phone_numbers";
            $header  = [
                'Authorization: Bearer ' . $token
            ];

            $response = CurlRequest::curlContent($apiUrl, $header);
            $data     = json_decode($response, true);
            if (!is_array($data) || !isset($data['data']) || isset($data['error'])) {
                throw new Exception($data['error']['message'] ?? 'Invalid WhatsApp business credentials. Please check your credentials and try again.');
            }

            return [
                'success' => true,
                'data'    => $data['data'][0] ?? null,
            ];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage() ?? "Something went wrong");
        }
    }

    public function sendWelcomeMessage($whatsappAccount, $user, $contact, $conversation)
    {
        $welcomeMessage = $whatsappAccount->welcomeMessage;
        if (!$welcomeMessage) return;

        $lockKey        = "welcome_message_sent:{$conversation->user_id}:{$conversation->contact_id}";

        if (!cache()->add($lockKey, true, 10)) {
            return;
        }

        $url = "https://graph.facebook.com/v22.0/{$whatsappAccount->phone_number_id}/messages";

        $header = [
            'Authorization: Bearer ' . $whatsappAccount->access_token
        ];
        $response = CurlRequest::curlPostContent($url, [
            'messaging_product' => 'whatsapp',
            'to'                => $contact->mobileNumber,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $welcomeMessage->message,
            ],
        ], $header);

        $data = json_decode($response, true);

        if (isset($data['error']) || !is_array($data)) {
            return [
                'success' => false,
                'message' => $data['error']['error_user_msg'] ?? $data['error']['message'],
                'data' => null,
            ];
        };

        $message                      = new Message();
        $message->user_id             = $user->id;
        $message->whatsapp_account_id = $whatsappAccount->id;
        $message->whatsapp_message_id = $data['messages'][0]['id'];
        $message->conversation_id     = $conversation->id;
        $message->type                = Status::MESSAGE_SENT;
        $message->message             = $welcomeMessage->message;
        $message->ordering            = Carbon::now();
        $message->save();

        $conversation->last_message_at = Carbon::now();
        $conversation->save();

        cache()->forget($lockKey);
    }

    public function chatbotResponse($whatsappAccount, $user, $contact, $conversation, $message = '')
    {
        $receiver = $contact->mobileNumber;
        $sender   = $user->id;

        $matchedChatbot = $whatsappAccount->chatbots()
            ->where('status', Status::ENABLE)
            ->where('keywords', 'like', "%{$message}%")
            ->first();

        if (!$matchedChatbot) {
            return;
        }

        $lockKey = "chat_message_sent:{$receiver}:{$sender}";
        if (!cache()->add($lockKey, true, 10)) {
            return;
        }

        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $whatsappAccount->access_token
        ];

        $response = CurlRequest::curlPostContent("https://graph.facebook.com/v22.0/{$whatsappAccount->phone_number_id}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $receiver,
            'type'              => 'text',
            'text'              => [
                'body' => $matchedChatbot->text,
            ],
        ], $header);

        $data = json_decode($response, true);
        if (isset($data['error'])  || !is_array($data)) {
            return [
                'success' => false,
                'message' => $data['error']['error_user_msg'] ?? $data['error']['message'],
                'data'    => null,
            ];
        };

        $message                      = new Message();
        $message->user_id             = $user->id;
        $message->whatsapp_account_id = $whatsappAccount->id;
        $message->whatsapp_message_id = $data['messages'][0]['id'];
        $message->conversation_id     = $conversation->id;
        $message->type                = Status::MESSAGE_SENT;
        $message->message             = $matchedChatbot->text;
        $message->ordering            = Carbon::now();
        $message->save();

        $conversation->last_message_at = Carbon::now();
        $conversation->save();

        cache()->forget($lockKey);
    }
}
