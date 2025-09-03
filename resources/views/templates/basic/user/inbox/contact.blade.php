<div class="body-right contact__details">
    <div class="empty-message text-center">
        <img src="{{ asset('assets/images/empty-con.png') }}" alt="empty">
    </div>
</div>

@push('script')
<script>
"use strict";
(function($) {

    // --- Util: parse WhatsApp-like markdown no FRONT (para Notas) ---
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function linkify(text) {
        return text.replace(
            /([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})|((?:https?:\/\/|www\.)[^\s<]+|[a-z0-9\-]+\.[a-z]{2,}(\/[^\s<]*)?)/gi,
            function(m, email){
                if (email) return `<a href="mailto:${m}">${m}</a>`;
                const href = /^https?:\/\//i.test(m) ? m : `https://${m}`;
                return `<a href="${href}" target="_blank" rel="noopener noreferrer">${m}</a>`;
            }
        );
    }
    function formatWhatsapp(text) {
        let t = escapeHtml(text);

        // code block ```...```
        const preHolders = [];
        t = t.replace(/```([\s\S]*?)```/gm, function(_, code){
            const key = `__PRE_BLOCK_${preHolders.length}__`;
            preHolders.push(`<pre class="msg-code"><code>${code}</code></pre>`);
            return key;
        });

        // linkificar antes de inline code/bold etc (ancoras não atrapalham)
        t = linkify(t);

        // inline code
        t = t.replace(/`([^`\n]+)`/g, '<code>$1</code>');

        // *bold*  _italic_  ~strike~
        t = t.replace(/\*(?=\S)([^*]+?)\*/g, '<strong>$1</strong>');
        t = t.replace(/_(?=\S)([^_]+?)_/g, '<em>$1</em>');
        t = t.replace(/~(?=\S)([^~]+?)~/g, '<del>$1</del>');

        // nl2br (sem afetar <pre>)
        t = t.replace(/\n/g, '<br>');
        preHolders.forEach((html, i) => { t = t.replace(`__PRE_BLOCK_${i}__`, html); });
        return t;
    }

    var route = "{{ route('user.inbox.note.store') }}";

    $(".contact__details").on('submit', ".note-wrapper__form", function(e) {
        e.preventDefault();
        const $this = $(this);
        var formData = new FormData($this[0]);

        $.ajax({
            url: route,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status == 'success') {
                    $this.trigger('reset');
                    const note = response.data.note;

                    const html = `
                    <div class="output">
                        <div>
                            <p class="text">${formatWhatsapp(note.note ?? '')}</p>
                            <span class="date">${new Date(note.created_at).toDateString()}</span>
                        </div>
                        <span class="icon deleteNote" data-id="${note.id}">
                            <i class="fas fa-trash text--danger"></i>
                        </span>
                    </div>`;

                    notify('success', response.message);
                    $(".contact__details").find('.note-wrapper__output').prepend(html);
                } else {
                    notify('error', response.message || "@lang('Something went wrong')");
                }
            }
        });
    });

    $(".contact__details").on('click', '.note-wrapper__output .deleteNote', function(e) {
        e.preventDefault();

        if (confirm("@lang('Are you sure to delete this note?')")) {
            var $this = $(this);
            var noteId = $this.data('id');
            var route = "{{ route('user.inbox.note.delete', ':id') }}".replace(':id', noteId);

            $.post(route, { _token: "{{ csrf_token() }}" }, function(data) {
                if (data.status == 'success') {
                    $this.closest('.output').remove();
                }
                notify(data.status, data.message);
            });
        }
    });

})(jQuery);
</script>
@endpush
