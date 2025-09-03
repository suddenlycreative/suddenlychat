@php
    $isUnread  = $message->status != Status::READ;
    $boldClass = $isUnread ? ' text--bold' : '';

    // texto bruto
    $raw = $message->message ?? '';

    // remove marcações de formatação para a PRÉVIA
    $txt = e($raw);
    $txt = preg_replace('/```[\s\S]*?```/m', ' [code] ', $txt);
    $txt = preg_replace('/`([^`\n]+)`/', '$1', $txt);
    $txt = preg_replace('/\*(?=\S)([^*]+?)\*/', '$1', $txt);
    $txt = preg_replace('/_(?=\S)([^_]+?)_/', '$1', $txt);
    $txt = preg_replace('/~(?=\S)([^~]+?)~/', '$1', $txt);

    // quebra em linhas e limpa vazios
    $lines = preg_split("/\r\n|\n|\r/", $txt);
    $lines = array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== ''));

    // detecta “linha de agente” (ex.: "Lucas Andrade Muniz:")
    $agentLine = '';
    $contentLine = '';
    if (count($lines)) {
        $first = $lines[0];
        // heurística: termina com ":" OU é “curta” (provável assinatura) e próxima linha existe
        $looksLikeAgent = preg_match('/[:：]\s*$/u', $first) || (mb_strlen($first) <= 40 && count($lines) > 1);
        if ($looksLikeAgent && count($lines) > 1) {
            $agentLine   = rtrim($first, ':： ');
            $contentLine = $lines[1];
        } else {
            $contentLine = $first;
        }
    }

    // limita conteúdo da prévia
    $previewContent = mb_strlen($contentLine) > 30 ? (mb_substr($contentLine, 0, 30) . '…') : $contentLine;
@endphp

<div class="last-message" data-conversation-id="{{ $message->conversation_id }}">
    @if ($message->media_id)
        @if ($message->message_type === Status::VIDEO_TYPE_MESSAGE)
            <p class="text text-muted{{ $boldClass }}"><i class="las la-video"></i> {{ __('Video') }}</p>
        @elseif ($message->message_type === Status::DOCUMENT_TYPE_MESSAGE)
            <p class="text text-muted{{ $boldClass }}"><i class="las la-file"></i> {{ __('Document') }}</p>
        @else
            <p class="text text-muted{{ $boldClass }}"><i class="las la-image"></i> {{ __('Photo') }}</p>
        @endif
    @else
        @if ($agentLine)
            <span class="agent-chip">{{ $agentLine }}</span>
        @endif
        <p class="text{{ $boldClass }}">{{ $previewContent }}</p>
    @endif
</div>
