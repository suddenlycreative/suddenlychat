@extends('admin.layouts.app')
@section('panel')
    <form method="POST">
        <x-admin.ui.card>
            <x-admin.ui.card.body>
                @csrf
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label> @lang('Pusher App ID')</label>
                        <input type="text" class="form-control" placeholder="@lang('Pusher App ID')" name="pusher_app_id"
                            value="{{ config('app.PUSHER_APP_ID') }}" required>
                    </div>
                    <div class="form-group col-sm-6">
                        <label> @lang('Pusher App Key')</label>
                        <input type="text" class="form-control" placeholder="@lang('Pusher App Key')" name="pusher_app_key"
                            value="{{ config('app.PUSHER_APP_KEY') }}" required>
                    </div>
                    <div class="form-group col-sm-6">
                        <label> @lang('Pusher App Secret')</label>
                        <input type="text" class="form-control" placeholder="@lang('Pusher App Secret')" name="pusher_app_secret"
                            value="{{ config('app.PUSHER_APP_SECRET') }}" required>
                    </div>
                    <div class="form-group col-sm-6">
                        <label> @lang('Pusher App Cluster')</label>
                        <input type="text" class="form-control" placeholder="@lang('Pusher App Cluster')" name="pusher_app_cluster"
                            value="{{ config('app.PUSHER_APP_CLUSTER') }}" required>
                    </div>
                    <div class="col-12">
                        <x-admin.ui.btn.submit />
                    </div>
                </div>

            </x-admin.ui.card.body>
        </x-admin.ui.card>
    </form>



    {{-- Videos Modal --}}
    <x-admin.ui.modal id="videoModal">
        <x-admin.ui.modal.header>
            <div class="d-flex gap-1 flex-wrap align-items-center plan">
                <h4 class="modal-title fw-bold">
                    @lang('Pusher Setup Video Tutorial')
                </h4>
            </div>
            <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close">
                <i class="las la-times"></i>
            </button>
        </x-admin.ui.modal.header>
        <x-admin.ui.modal.body>
            <iframe width="100%" height="400"
                src="https://www.youtube.com/embed/E6aJOH2Rv7E?si=N62WIhpBK7rY93Zf&autoplay=1" class="rounded"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin" allowfullscreen>
            </iframe>

        </x-admin.ui.modal.body>
    </x-admin.ui.modal>

    {{-- Config Modal --}}
    <x-admin.ui.modal id="configurationModal">
        <x-admin.ui.modal.header>
            <div class="d-flex gap-1 flex-wrap align-items-center plan">
                <h4 class="modal-title fw-bold">
                    @lang('Pusher Setup')
                </h4>
            </div>
            <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close">
                <i class="las la-times"></i>
            </button>
        </x-admin.ui.modal.header>
        <x-admin.ui.modal.body>
            <div class="mb-3 p-2">
                <ul>
                    <li class="mb-3">
                        @lang('Go to')
                        <a href="https://pusher.com" target="_blank">
                            <i class="la la-external-link"></i>@lang('pusher.com')
                        </a>
                        @lang('and create a free account if you don’t already have one.')
                    </li>
                    <li class="mb-3">
                        @lang('After logging in, create a new app. During the app creation process, choose the appropriate cluster (e.g., mt1) based on your region.')
                    </li>
                    <li class="mb-3">
                        @lang('Once the app is created, you will be provided with credentials including:')
                        <ul class="mt-2">
                            <li class="fs-14">@lang('App ID')</li>
                            <li class="fs-14">@lang('Key')</li>
                            <li class="fs-14">@lang('Secret')</li>
                            <li class="fs-14">@lang('Cluster')</li>
                        </ul>
                    </li>
                    <li class="mb-3">
                        @lang('Navigate to the') <strong>@lang('Pusher Configuration')</strong> @lang('section of the admin panel and paste the copied values into their respective fields:')
                        <ul class="mt-2">
                            <li class="fs-14"><strong>@lang('App ID')</strong> – @lang('Enter your Pusher App ID')</li>
                            <li class="fs-14"><strong>@lang('Key')</strong> – @lang('Enter your Pusher Key')</li>
                            <li class="fs-14"><strong>@lang('Secret')</strong> – @lang('Enter your Pusher Secret')</li>
                            <li class="fs-14"><strong>@lang('Cluster')</strong> – @lang('Enter your selected Cluster (e.g., mt1)')</li>
                        </ul>
                    </li>
                    <li class="mb-3">
                        @lang('Save the configuration. Your application is now ready to use Pusher for real-time features such as notifications, chat, and more.')
                    </li>
                </ul>
            </div>
        </x-admin.ui.modal.body>

    </x-admin.ui.modal>
@endsection

@push('breadcrumb-plugins')
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline--primary video">
            <i class="las la-play"></i> @lang('Video Guide')
        </button>
        <button type="button" class="btn btn-outline--success configuration">
            <i class="las la-info"></i> @lang('Configuration')
        </button>
    </div>
@endpush

@push('script')
    <script>
        "use strict";
        (function($) {
            const $videoModal = $('#videoModal');
            $('.video').on('click', function() {
                $videoModal.modal('show');
            });

            const $configurationModal = $('#configurationModal');
            $('.configuration').on('click', function() {
                $configurationModal.modal('show');
            });

        })(jQuery);
    </script>
@endpush
