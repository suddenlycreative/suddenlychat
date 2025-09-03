<div class="chat-box">
    <div class="chat-box__shape">
        <img src="{{ getImage($activeTemplateTrue . 'images/chat-bg.png') }}" alt="">
    </div>
    <div class="chat-box__header">
        <div class="d-flex align-items-center gap-3">
            <div class="chat-box__item">
                <div class="chat-box__thumb">
                    <img class="avatar contact__profile"
                        src="{{ getImage($activeTemplateTrue . 'images/ch-1.png', isAvatar: true) }}" alt="image">
                </div>
                <div class="chat-box__content">
                    <p class="name contact__name"></p>
                    <p class="text contact__mobile"></p>
                </div>
            </div>
        </div>
    </div>
    <div class="msg-body">

    </div>
    <div class="chat-box__footer">
        <form class="chat-send-area no-submit-loader" id="message-form">
            @csrf
            <div class="btn-group">
                <div class="chat-media">
                    <button class="chat-media__btn" type="button"> <i class="las la-plus"></i> </button>
                    <div class="chat-media__list">
                        <label for="document" class="media-item select-media-item"
                            data-media-type="{{ Status::DOCUMENT_TYPE_MESSAGE }}">
                            <span class="icon">
                                <i class="fas fa-file-alt"></i>
                            </span>
                            <span class="title">@lang('Document')</span>
                            <input hidden class="media-input" name="document" type="file"accept="application/pdf">
                        </label>
                        <label for="video" class="media-item select-media-item"
                            data-media-type="{{ Status::VIDEO_TYPE_MESSAGE }}">
                            <span class="icon">
                                <i class="fas fa-video"></i>
                            </span>
                            <span class="title">@lang('Video')</span>
                            <input class="media-input" name="video" type="file" accept="video/*" hidden>
                        </label>
                    </div>
                </div>
                <label for="image" class="btn-item image-upload-btn select-media-item"
                    data-media-type="{{ Status::IMAGE_TYPE_MESSAGE }}">
                    <i class="fa-solid fa-image"></i>
                    <input hidden class="image-input" name="image" type="file" accept=".jpg, .jpeg, .png">
                </label>
            </div>

            <div class="image-preview-container"></div>
            <div class="input-area d-flex align-center gap-2">
                <span class="emoji-icon cursor-pointer">
                    <i class="far fa-smile"></i>
                </span>
                <div class="emoji-container"></div>
                <div class="input-group">
                    <textarea name="message" class="form--control message-input" placeholder="@lang('Type your message here')" autocomplete="off"></textarea>
                </div>
                <button class="chating-btn" type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none">
                        <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M22 2L11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>
