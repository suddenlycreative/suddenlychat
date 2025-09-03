@php
    // ===== Parser WhatsApp inline (sem helper externo) =====
    $textRaw = $message->media_caption ?: ($message->message ?? '');
    // 1) Escapa HTML
    $escaped = e($textRaw);

    // 2) Linkify (URLs e e-mails)
    $escaped = preg_replace_callback(
        '/([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})|((?:https?:\/\/|www\.)[^\s<]+|[a-z0-9\-]+\.[a-z]{2,}(\/[^\s<]*)?)/i',
        function ($m) {
            if (!empty($m[1])) {
                $email = $m[1];
                return '<a href="mailto:' . $email . '">' . $email . '</a>';
            }
            $url = $m[0];
            $href = preg_match('/^https?:\/\//i', $url) ? $url : 'https://' . $url;
            return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        },
        $escaped
    );

    // 3) Code block ```...```
    $placeholders = [];
    $escaped = preg_replace_callback('/```([\s\S]*?)```/m', function($mm) use (&$placeholders){
        $key = '__PRE_BLOCK__'.count($placeholders).'__';
        $placeholders[$key] = '<pre class="msg-code"><code>'.$mm[1].'</code></pre>';
        return $key;
    }, $escaped);

    // 4) Inline code `...`
    $escaped = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $escaped);

    // 5) *bold* _italic_ ~strike~
    $escaped = preg_replace('/\*(?=\S)([^*]+?)\*/', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/_(?=\S)([^_]+?)_/', '<em>$1</em>', $escaped);
    $escaped = preg_replace('/~(?=\S)([^~]+?)~/', '<del>$1</del>', $escaped);

    // 6) nl2br preservando <pre>
    $escaped = preg_replace('/\r\n?/', "\n", $escaped);
    $escaped = str_replace("\n", "<br>", $escaped);
    foreach ($placeholders as $k => $v) {
        $escaped = str_replace($k, $v, $escaped);
    }

    $isSent     = @$message->type == Status::MESSAGE_SENT;
    $hasMedia   = @$message->media_id;
    $isImage    = @$message->message_type == Status::IMAGE_TYPE_MESSAGE;
    $isVideo    = @$message->message_type == Status::VIDEO_TYPE_MESSAGE;
    $isDocument = @$message->message_type == Status::DOCUMENT_TYPE_MESSAGE;
@endphp

<div class="single-message {{ $isSent ? 'message--right' : 'message--left' }}" data-message-id="{{ $message->id }}">
    <div class="message-content">
        @if ($message->template_id)
            <p class="message-text">@lang('Template Message')</p>
        @else
            @if (!empty($textRaw))
                <p class="message-text">{!! $escaped !!}</p>
            @endif

            @if ($hasMedia)
                @if ($isImage)
                    <a href="{{ route('user.inbox.media.download', $message->media_id) }}">
                        <img class="message-image" src="{{ getImage(getFilePath('conversation') . '/' . @$message->media_path) }}" alt="image">
                    </a>
                @elseif ($isVideo)
                    <div class="text-dark d-flex align-items-center justify-content-between">
                        <a href="{{ route('user.inbox.media.download', $message->media_id) }}" class="text--primary download-document">
                            <img class="message-image" src="{{ asset('assets/images/video_preview.png') }}" alt="image">
                        </a>
                    </div>
                @elseif ($isDocument)
                    <div class="text-dark d-flex justify-content-between flex-column">
                        <a href="{{ route('user.inbox.media.download', $message->media_id) }}" class="text--primary download-document">
                            <img class="message-image" src="{{ asset('assets/images/document_preview.png') }}" alt="image">
                        </a>
                        {{ @$message->media_filename ?? 'Document' }}
                    </div>
                @endif
            @endif
        @endif
    </div>

    <div class="d-flex align-items-center justify-content-between">
        <span class="message-time">{{ showDateTime(@$message->created_at, 'h:i A') }}</span>
        @if ($isSent)
            <span class="message-status">@php echo $message->statusBadge @endphp</span>
        @endif
    </div>
</div>
