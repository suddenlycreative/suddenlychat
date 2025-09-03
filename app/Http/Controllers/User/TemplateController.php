<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use Carbon\Carbon;
use App\Models\Template;
use Illuminate\Http\Request;
use App\Traits\WhatsappManager;
use Illuminate\Validation\Rule;
use App\Models\TemplateCategory;
use App\Models\TemplateLanguage;
use App\Http\Controllers\Controller;
use App\Lib\CurlRequest;
use App\Lib\WhatsApp\WhatsAppLib;
use App\Models\WhatsappAccount;
use Exception;
use Illuminate\Support\Facades\File;

class TemplateController extends Controller
{
    use WhatsappManager;

    public function index()
    {
        $pageTitle          = "Manage Template";
        $user               = getParentUser();
        $templateCategories = TemplateCategory::get();
        $templates          = Template::where('user_id', getParentUser()->id)
            ->where('whatsapp_account_id', getWhatsappAccountId($user))
            ->searchable(['name', 'whatsapp_template_id'])
            ->filter(['status', 'category_id'])
            ->orderBy('id', 'desc')
            ->paginate(getPaginate());

        return view('Template::user.template.index', compact('pageTitle', 'templates', 'templateCategories'));
    }

    public function createTemplate()
    {
        $user      = getParentUser();
        $templates = $user->templates()->where('created_at', '>=', Carbon::now()->subHour())->count();

        if ($templates >= 100) {
            $notify[] = ['error', 'You can create only 100 templates per hour.'];
            return back()->withNotify($notify);
        }

        $pageTitle          = "Create Template";
        $templateCategories = TemplateCategory::get();
        $templateLanguages  = TemplateLanguage::get();
        $preMadeTemplates   = json_decode(file_get_contents(resource_path("views/" . str_replace(".", "/", activeTemplate()) . "user/template/pre_made_template.json")));

        return view('Template::user.template.create', compact('pageTitle', 'templateCategories', 'templateLanguages', 'preMadeTemplates'));
    }

    public function storeTemplate(Request $request)
    {
        $this->validation($request);

        $user = getParentUser();

        if (!featureAccessLimitCheck($user->template_limit)) {
            $notify = 'Your template limit is over. Please upgrade your plan';
            return responseManager('limit', $notify);
        }

        $whatsappAccount = WhatsappAccount::where('user_id', $user->id)->where('id', $request->whatsapp_account_id)->first();
      
        if (!$whatsappAccount) {
            return responseManager('invalid', 'The selected whatsapp account is invalid');
        }

        $templateExists = Template::where('user_id', $user->id)
            ->where('name', $request->name)
            ->where('language_id', $request->language_id)
            ->exists();

        if ($templateExists) {
            $notify = 'Sorry! The template name already exists';
            return responseManager('exists', $notify);
        }

        $category     = TemplateCategory::find($request->category_id);

        if (!$category) {
            $notify = 'The template category is not found';
            return responseManager('not_found', $notify);
        }

        $language = TemplateLanguage::find($request->language_id);

        if (!$language) {
            $notify = 'The template language is not found';
            return responseManager('not_found', $notify);
        }

        $header          = $request->header ?? [];
        $headerFormat    = $request->header_format ?? null;
        $headerMediaName = null;

        $whatsappManager = new WhatsAppLib();

        try {
            if ($request->hasFile('header.handle')) {

                $headerMediaName = fileUploader($header['handle'], getFilePath('templateHeader'), getFileSize('templateHeader'));

                if (!$headerMediaName) {
                    return responseManager('error', 'Couldn\'t upload your image');
                }

                $fileData         = [];
                $fileData['name'] = $headerMediaName;
                $fileData['path'] = getFilePath('templateHeader') . '/' . $headerMediaName;
                $fileData['size'] = filesize($fileData['path']);
                $fileData['type'] = mime_content_type($fileData['path']);

                $sessionId        = $whatsappManager->getSessionId($whatsappAccount->meta_app_id, $fileData, $whatsappAccount->access_token);
                $header['handle'] = $whatsappManager->getMediaHandle($sessionId['id'], $whatsappAccount->access_token, $fileData['path'], $fileData['type']);
            }
        } catch (\Exception $e) {
            return responseManager('error', $e->getMessage());
        }
        
        
        $whatsappTemplateData = [
            'name'     => $request->name,
            'language' => $language->code,
            'category' => $category->name,
        ];

        $whatsappTemplateData['components'] = [];

        if (!empty($headerFormat)) {
            if ($category->name !== 'AUTHENTICATION') {
                $whatsappTemplateData['components'][] = $this->templateHeader($headerFormat, $header);
            }
        }
        if (!empty($request->template_body)) {
            $whatsappTemplateData['components'][] = $this->templateBody($request, $category->name);
        }

        if (!empty($request->footer)) {
            $whatsappTemplateData['components'][] = $this->templateFooter($request, $category->name);
        }

        $templateButtons = $this->templateButtons($request, $category->name);

        if (!empty($templateButtons)) {
            $whatsappTemplateData['components'][] = [
                "type" => 'BUTTONS',
                "buttons" => $templateButtons,
            ];
        }

        try {
            $whatsappData = $whatsappManager->submitTemplate($whatsappAccount->whatsapp_business_account_id, $whatsappAccount->access_token, $whatsappTemplateData);
        } catch (\Exception $e) {
            return responseManager('error', $e->getMessage());
        }

        $template                              = new Template();
        $template->user_id                     = $user->id;
        $template->whatsapp_account_id         = $whatsappAccount->id;
        $template->whatsapp_template_id        = $whatsappData['id'];
        $template->name                        = $request->name;
        $template->category_id                 = $request->category_id;
        $template->language_id                 = $request->language_id;
        $template->body                        = $request->template_body;
        $template->header                      = $request->header_format ? $header : null;
        $template->header_format               = $headerFormat ?? null;
        $template->header_media                = $headerMediaName ?? null;
        $template->footer                      = $request->footer ?? null;
        $template->status                      = metaTemplateStatus($whatsappData['status']);
        $template->buttons                     = count($templateButtons) ? $templateButtons : [];
        $template->code_expiration_minutes     = $request->code_expiration_minutes ?? null;
        $template->add_security_recommendation = $request->add_security_recommendation ? Status::YES : Status::NO;
        $template->save();

        decrementFeature($user, 'template_limit');

        $notify =  'Your template has been submitted for approval';
        return responseManager('template_created', $notify,'success');
    }

    public function validation($request)
    {
        $request->validate([
            'name'                => 'required|string|max:512|regex:/^[a-zA-Z0-9_-]+$/',
            'category_id'         => 'required|exists:template_categories,id',
            'language_id'         => 'required|exists:template_languages,id',
            'header_format'       => ['nullable', Rule::in(templateHeaderTypes())],
            'template_body'       => 'required|string|max:1024',
            'footer'              => 'nullable|string|max:60',
            'whatsapp_account_id' => 'required',
            'buttons'             => 'nullable|array',
            'buttons.*.text'      => 'required|string',
        ], [
            'name.regex' => 'The template name must only contain letters, numbers, underscores, and hyphens',
        ]);
    }

    private function templateButtons($request, $category)
    {
        $formateButtons = [];

        foreach ($request->buttons ?? [] as $button) {

            $buttonType  = $button['type'];
            $text        = $button['text'];

            if ($category == 'AUTHENTICATION' && $buttonType !== 'OTP') {
                continue;
            }
            $requiredFields = [
                'QUICK_REPLY'  => 'quick_reply',
                'PHONE_NUMBER' => 'phone_number',
                'URL'          => 'url',
                'OTP'          => 'otp_type',
            ];
            if ($buttonType == 'PHONE_NUMBER' || $buttonType == 'URL' || $buttonType == 'OTP') {
                $field = $requiredFields[$buttonType];
                $formateButtons[] = [
                    'type' => $buttonType,
                    'text' => $text,
                    $field => $button[$field],
                ];
            } else {
                $formateButtons[] = [
                    'type' => $buttonType,
                    'text' => $text,
                ];
            }
        }

        return $formateButtons;
    }

    private function templateHeader($headerFormat, $header)
    {
        $templateHeader = [
            "type"   => 'HEADER',
            "format" => $headerFormat,
        ];

        if ($headerFormat === 'TEXT' && !empty($header['text'])) {
            $templateHeader['text'] = $header['text'];

            if (preg_match('/\{\{\d+\}\}/', $header['text']) && !empty($header['example'])) {
                $templateHeader['example'] = $header['example'];
            }
        } elseif (in_array($headerFormat, ['IMAGE', 'VIDEO', 'DOCUMENT']) && !empty($header['handle'])) {
            $templateHeader['example'] = [
                "header_handle" => $header['handle'],
            ];
        }

        return $templateHeader;
    }

    private function templateBody($request, $category)
    {
        $bodyText = $request->template_body ?? '';
    
        $result = [
            "type" => 'BODY',
        ];
    
        if ($category && $category === 'AUTHENTICATION' && $request->add_security_recommendation) {
            $result['add_security_recommendation'] = true;
        } else {
            $result['text'] = $bodyText;
    
            if ($bodyText && isset($request->body['example']['body_text'])) {
                $bodyExamples = $request->body['example']['body_text'];
    
                if (!empty($bodyExamples) && is_array($bodyExamples)) {
                    if (!isset($bodyExamples[0]) || !is_array($bodyExamples[0])) {
                        $bodyExamples = [array_values($bodyExamples)];
                    }
                }
    
                $result['example'] = [
                    'body_text' => $bodyExamples
                ];
            }
        }
    
        return $result;
    }




    public function templateFooter($request, $category)
    {
        if ($category === 'AUTHENTICATION' && $request->code_expiration_minutes) {
            return [
                "type" => "FOOTER",
                "code_expiration_minutes" => (int) $request->code_expiration_minutes,
            ];
        }

        if (!empty($request->footer)) {
            return [
                "type" => "FOOTER",
                "text" => $request->footer,
            ];
        }

        return null;
    }

    public function getTemplates(Request $request)
    {
        $user = getParentUser();
        $whatsappAccount = WhatsappAccount::where('user_id', $user->id)->find($request->whatsapp_account_id);
        if (!$whatsappAccount) {
            return responseManager('invalid', 'The selected whatsapp account is invalid');
        }

        $templates = Template::where('user_id', $user->id)
            ->where('whatsapp_account_id', $whatsappAccount->id)
            ->approved()
            ->get() ?? [];
        return responseManager(
            'templates',
            'Template list',
            'success',
            [
                'templates' => $templates
            ]
        );
    }


    public function deleteTemplate($templateId)
    {
        $template = Template::where('user_id', getParentUser()->id)->whereHas('whatsappAccount')->where('id', $templateId)->firstOrFail();

        if($template->campaigns()->count()) {
            return responseManager('error', 'Unable to delete template,This template is used in campaign');
        }

        if($template->messages()->count()) {
            return responseManager('error', 'Unable to delete template,This template is used in message');
        }

        $businessAccountId = $template->whatsappAccount->whatsapp_business_account_id;
        $accessToken       = $template->whatsappAccount->access_token;

        try {
            $header = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $response = CurlRequest::curlDeleteContent("https://graph.facebook.com/v22.0/{$businessAccountId}/message_templates?name={$template->name}", null, $header);
            $data = json_decode($response, true);

            if (!is_array($data) || isset($data['error'])) {
                $notify[] = ['error', @$data['error']['error_user_msg'] ?? "Something went to wrong"];
                return back()->withNotify($notify);
            }

            if($template->header_media) {
                $headerMedia = getFilePath('templateHeader').'/'.$template->header_media;
                if($headerMedia && File::exists($headerMedia)) {
                    File::delete($headerMedia);
                }
            }
            $template->delete();

            $notify[] = ['success', 'Template deleted successfully'];
            return back()->withNotify($notify);

        } catch (Exception $ex) {
            $notify[] = ['error', $ex->getMessage() ?? "Something went to wrong"];
            return back()->withNotify($notify);
        }
    }

    public function checkTemplateStatus($templateId)
    {
        $template = Template::where('user_id', getParentUser()->id)->where('id', $templateId)->whereHas('whatsappAccount')->firstOrFail();
        $account  = $template->whatsappAccount;

        $businessAccountId = $account->whatsapp_business_account_id;
        $accessToken       = $account->access_token;
        
        try {
            $header = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $response = CurlRequest::curlContent("https://graph.facebook.com/v22.0/{$businessAccountId}/message_templates?name={$template->name}", $header);
            $data     = json_decode($response, true);
            if (!is_array($data) || isset($data['error']) || !isset($data['data'][0]['status'])) {
                $notify[] = ['error', @$data['error']['error_user_msg'] ?? "Something went to wrong"];
                return back()->withNotify($notify);
            }

            $template->status = metaTemplateStatus($data['data'][0]['status']);
            $template->save();

            $notify[] = ['success', 'Template status has been updated'];
            return back()->withNotify($notify);
        } catch (Exception $ex) {
            $notify[] = ['error', $ex->getMessage() ?? "Something went to wrong"];
            return back()->withNotify($notify);
        }
    }
}
